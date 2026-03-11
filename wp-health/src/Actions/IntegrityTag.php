<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooksFrontend;

class IntegrityTag implements ExecuteHooksFrontend
{
    public function hooks()
    {
        add_action('wp_footer', [$this, 'renderTag'], WP_UMBRELLA_MAX_PRIORITY_HOOK);
    }

    public function renderTag()
    {
        $projectId = wp_umbrella_get_project_id();

        if (empty($projectId)) {
            return;
        }

        $hash = md5((string) $projectId);

        echo '<!-- wpu:' . esc_html($hash) . ' -->' . "\n";
    }
}
