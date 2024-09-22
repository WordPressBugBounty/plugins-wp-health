<?php
namespace WPUmbrella\Actions\Queue;


use ActionScheduler;
use ActionScheduler_Store;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\Backup\V2\BackupManageProcess;

class ByPassActionSchedulerV2 implements ExecuteHooks
{
    const HOOK = 'wp_umbrella_create_additional_runner';
    const ACTION = 'wp_umbrella_create_additional_runner_action';
    const NONCE = 'wp_umbrella_create_additional_runner_nonce';

    public function hooks()
    {
        // add_action('action_scheduler_run_queue', [$this, 'onRunningQueue'], 0);
        // add_action('wp_ajax_nopriv_' . self::HOOK, [$this, 'createAdditionalRunner'], 0);
		add_filter('action_scheduler_retention_period', [$this, 'purge'] );
    }


	public function purge() {
		return WEEK_IN_SECONDS;
	}

    public function onRunningQueue()
    {
        /** @var BackupManageProcess $runner */
        $runner = wp_umbrella_get_service('BackupManageProcess');

        if ($runner->isBackupInProgress()) {
            return;
        }

        $args = [
            'group' => BackupManageProcess::GROUP,
            'per_page' => 1,
            'status' => ActionScheduler_Store::STATUS_PENDING,
            'claimed' => false
        ];

        $actionsIds = as_get_scheduled_actions($args, 'ids');

        if (empty($actionsIds)) {
            return;
        }

        $args = [
            'timeout' => 300,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => false,
            'headers' => [],
            'body' => [
                'action' => self::HOOK,
                self::NONCE => wp_create_nonce(self::ACTION),
            ],
            'cookies' => [],
        ];

        $url = admin_url('admin-ajax.php');

        wp_remote_post($url, $args);
    }

    /**
     * Handle requests initiated by eg_request_additional_runners() and start a queue runner if the request is valid.
     */
    public function createAdditionalRunner()
    {
        if (
            array_key_exists(self::NONCE, $_POST)
            && wp_verify_nonce($_POST[self::NONCE], self::ACTION)
        ) {
            $args = [
                'group' => BackupRunner::GROUP,
                'per_page' => 1,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'claimed' => false
            ];

            $actionsIds = as_get_scheduled_actions($args, 'ids');
            $actionId = current($actionsIds);

            $runner = wp_umbrella_get_service('BackupManageProcess');

            if (!$runner->isBackupInProgress()) {
                ActionScheduler::runner()->process_action($actionId, 'WP Cron');
            }
        }

        wp_die();
    }
}
