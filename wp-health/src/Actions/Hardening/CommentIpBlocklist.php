<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;
use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\Hardening\AttackerIps\CommunityIpFilter;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class CommentIpBlocklist implements ExecuteHooks
{
    const BLOCK_EVENT_KEY = 'umbrella.protection.comment_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_comment_block_bucket';

    const BLOCK_WINDOW = 1800;

    protected $filter;

    public function __construct(?CommunityIpFilter $filter = null)
    {
        $this->filter = $filter !== null ? $filter : new CommunityIpFilter();
    }

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('login_ip_blocklist')) {
            return;
        }

        add_filter('pre_comment_approved', [$this, 'enforce'], 20, 2);
    }

    public function enforce($approved, $commentdata = [])
    {
        // A comment already refused upstream keeps its verdict: turning a
        // WP_Error into spam would soften someone else's rejection.
        if ($approved === 'spam' || $approved === 'trash' || is_wp_error($approved)) {
            return $approved;
        }

        $ip = $this->resolveIp($commentdata);

        if ($ip === null || !$this->filter->isBlocked($ip)) {
            return $approved;
        }

        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'comment_ip_blocklist',
            'outcome' => 'spammed',
            'postId' => isset($commentdata['comment_post_ID']) ? (string) (int) $commentdata['comment_post_ID'] : null,
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);

        return 'spam';
    }

    protected function resolveIp($commentdata)
    {
        if (is_array($commentdata) && !empty($commentdata['comment_author_IP'])) {
            return (string) $commentdata['comment_author_IP'];
        }

        $resolved = ClientIpResolver::resolve();

        return is_string($resolved) && $resolved !== '' ? $resolved : null;
    }
}
