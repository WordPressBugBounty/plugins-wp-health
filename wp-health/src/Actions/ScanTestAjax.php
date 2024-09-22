<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;

class ScanTestAjax implements ExecuteHooks
{
    public function hooks()
    {
        add_action('wp_ajax_nopriv_umbrella_scantest', [$this, 'handle']);
        add_action('wp_ajaxv_umbrella_scantest', [$this, 'handle']);
    }

    public function handle()
    {
        die('SCANTESTOK');
    }
}
