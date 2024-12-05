<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Hooks\ActivationHook;

class BackupRouter implements ExecuteHooks, ActivationHook
{
    public function hooks()
    {
        add_action('init', [$this, 'clonerEndpoint']);
        add_action('template_redirect', [$this, 'handleClonerRequest']);
    }

    public function activate()
    {
        $this->clonerEndpoint();
        flush_rewrite_rules();
    }

    public function clonerEndpoint()
    {
        add_rewrite_endpoint('umbrella-backup', EP_ROOT);
    }

    public function handleClonerRequest()
    {
        global $wp_query;

        if (!isset($wp_query->query_vars['umbrella-backup'])) {
            return;
        }

        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();

        $filename = 'cloner.php';

        $filePath = $source . $filename;
        if (!file_exists($filePath)) {
            wp_safe_redirect(home_url());
            return;
        }

        include_once $filePath;
    }
}
