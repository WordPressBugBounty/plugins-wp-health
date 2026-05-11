<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;
use WPUmbrella\Actions\ActivityLog\Framework\WPUmbrellaContext;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures post and media events.
 *
 * Event keys emitted:
 * - post.created       (LOW)
 * - post.published     (MEDIUM)
 * - post.updated       (LOW)
 * - post.trashed       (LOW)
 * - post.restored      (LOW)
 * - post.deleted       (MEDIUM)
 * - media.uploaded     (LOW)
 * - media.deleted      (LOW)
 *
 * Implementation notes:
 * - Post lifecycle is captured via transition_post_status which models all
 *   create/publish/trash/restore in a single dispatch.
 * - Auto saves and revisions are filtered explicitly here, on top of the
 *   defensive postType filter in NoiseFilter (revision, auto-draft).
 * - nav_menu_item posts are excluded so menu activity is reported once
 *   through the dedicated menu sensor instead of twice.
 */
class ContentSensor extends AbstractSensor
{
    /**
     * Post types that are not considered editorial content. Filtered before
     * dispatch to avoid double counting with sensors that own those events.
     *
     * @var array
     */
    protected static $ignoredPostTypes = [
        'revision',
        'auto-draft',
        'oembed_cache',
        'nav_menu_item',
        'customize_changeset',
        'wp_block',
        'wp_navigation',
    ];

    /**
     * @return void
     */
    public function register()
    {
        add_action('transition_post_status', [$this, 'onTransitionPostStatus'], 10, 3);
        add_action('before_delete_post', [$this, 'onBeforeDeletePost'], 10, 1);
        add_action('deleted_post', [$this, 'onPostDeleted'], 10, 2);
        add_action('add_attachment', [$this, 'onAttachmentAdded'], 10, 1);
        add_action('delete_attachment', [$this, 'onAttachmentDeleted'], 10, 2);
    }

    public function onTransitionPostStatus($newStatus, $oldStatus, $post)
    {
        if (!is_object($post) || !isset($post->ID)) {
            return;
        }

        if ($newStatus === $oldStatus) {
            return;
        }

        if ($newStatus === 'auto-draft' || $newStatus === 'inherit') {
            return;
        }

        if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post)) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post)) {
            return;
        }

        $postType = isset($post->post_type) ? (string) $post->post_type : 'post';

        if (in_array($postType, self::$ignoredPostTypes, true)) {
            return;
        }

        if ($postType === 'attachment') {
            return;
        }

        list($eventKey, $severity) = $this->resolveEvent($newStatus, $oldStatus);

        $this->recordEvent($eventKey, $severity, [
            'postId' => (int) $post->ID,
            'postTitle' => isset($post->post_title) ? (string) $post->post_title : null,
            'postType' => $postType,
            'postStatus' => $newStatus,
            'previousStatus' => $oldStatus,
            'postAuthorId' => isset($post->post_author) ? (int) $post->post_author : null,
        ]);
    }

    /**
     * Snapshot a post before hard deletion so onPostDeleted can emit a
     * meaningful payload (post is gone from the DB by then).
     *
     * @var array<int, array{title: string, type: string, status: string, authorId: int}>
     */
    protected $deletionCache = [];

    public function onBeforeDeletePost($postId)
    {
        if (WPUmbrellaContext::isInUserDeletion()) {
            return;
        }

        $postId = (int) $postId;

        if ($postId <= 0 || !function_exists('get_post')) {
            return;
        }

        $post = get_post($postId);

        if (!is_object($post)) {
            return;
        }

        $postType = isset($post->post_type) ? (string) $post->post_type : '';

        if (in_array($postType, self::$ignoredPostTypes, true) || $postType === 'attachment') {
            return;
        }

        $this->deletionCache[$postId] = [
            'title' => isset($post->post_title) ? (string) $post->post_title : '',
            'type' => $postType,
            'status' => isset($post->post_status) ? (string) $post->post_status : '',
            'authorId' => isset($post->post_author) ? (int) $post->post_author : 0,
        ];
    }

    public function onPostDeleted($postId, $post = null)
    {
        if (WPUmbrellaContext::isInUserDeletion()) {
            return;
        }

        $postId = (int) $postId;
        $snapshot = isset($this->deletionCache[$postId]) ? $this->deletionCache[$postId] : null;
        unset($this->deletionCache[$postId]);

        if ($snapshot === null && is_object($post)) {
            $type = isset($post->post_type) ? (string) $post->post_type : '';

            if (in_array($type, self::$ignoredPostTypes, true) || $type === 'attachment') {
                return;
            }

            $snapshot = [
                'title' => isset($post->post_title) ? (string) $post->post_title : '',
                'type' => $type,
                'status' => isset($post->post_status) ? (string) $post->post_status : '',
                'authorId' => isset($post->post_author) ? (int) $post->post_author : 0,
            ];
        }

        if ($snapshot === null) {
            return;
        }

        $this->recordEvent('post.deleted', 'MEDIUM', [
            'postId' => $postId,
            'postTitle' => $snapshot['title'],
            'postType' => $snapshot['type'],
            'postStatus' => $snapshot['status'],
            'postAuthorId' => $snapshot['authorId'] > 0 ? $snapshot['authorId'] : null,
        ]);
    }

    public function onAttachmentAdded($postId)
    {
        $info = $this->getAttachmentInfo($postId);

        $this->recordEvent('media.uploaded', 'LOW', [
            'attachmentId' => (int) $postId,
            'fileName' => $info !== null ? $info['fileName'] : null,
            'mimeType' => $info !== null ? $info['mimeType'] : null,
            'attachmentTitle' => $info !== null ? $info['title'] : null,
        ]);
    }

    public function onAttachmentDeleted($postId, $post = null)
    {
        if (WPUmbrellaContext::isInUserDeletion()) {
            return;
        }

        $info = $this->getAttachmentInfo($postId);

        if ($info === null && is_object($post)) {
            $info = [
                'fileName' => isset($post->post_name) ? (string) $post->post_name : '',
                'mimeType' => isset($post->post_mime_type) ? (string) $post->post_mime_type : '',
                'title' => isset($post->post_title) ? (string) $post->post_title : '',
            ];
        }

        $this->recordEvent('media.deleted', 'LOW', [
            'attachmentId' => (int) $postId,
            'fileName' => $info !== null ? $info['fileName'] : null,
            'mimeType' => $info !== null ? $info['mimeType'] : null,
            'attachmentTitle' => $info !== null ? $info['title'] : null,
        ]);
    }

    /**
     * @param string $newStatus
     * @param string $oldStatus
     *
     * @return array{0: string, 1: string} [eventKey, severity]
     */
    protected function resolveEvent($newStatus, $oldStatus)
    {
        if ($oldStatus === 'new' || $oldStatus === 'auto-draft') {
            if ($newStatus === 'publish') {
                return ['post.published', 'MEDIUM'];
            }

            return ['post.created', 'LOW'];
        }

        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            return ['post.published', 'MEDIUM'];
        }

        if ($newStatus === 'trash') {
            return ['post.trashed', 'LOW'];
        }

        if ($oldStatus === 'trash' && $newStatus !== 'trash') {
            return ['post.restored', 'LOW'];
        }

        return ['post.updated', 'LOW'];
    }

    /**
     * @param int $attachmentId
     *
     * @return array{fileName: string, mimeType: string, title: string}|null
     */
    protected function getAttachmentInfo($attachmentId)
    {
        $attachmentId = (int) $attachmentId;

        if ($attachmentId <= 0 || !function_exists('get_post')) {
            return null;
        }

        $post = get_post($attachmentId);

        if (!is_object($post)) {
            return null;
        }

        $fileName = '';

        if (function_exists('get_attached_file')) {
            $path = get_attached_file($attachmentId);

            if (is_string($path) && $path !== '') {
                $fileName = basename($path);
            }
        }

        return [
            'fileName' => $fileName,
            'mimeType' => isset($post->post_mime_type) ? (string) $post->post_mime_type : '',
            'title' => isset($post->post_title) ? (string) $post->post_title : '',
        ];
    }
}
