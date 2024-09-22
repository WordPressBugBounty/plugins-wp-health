<?php
namespace WPUmbrella\Services\Backup;

use ActionScheduler;
use ActionScheduler_Store;

class BackupRunner
{
    const GROUP = 'wp-umbrella';

    const ACTION_BACKUP_FILES = 'wp_umbrella_backup_files_batch';

    const ACTION_BACKUP_DATABASE = 'wp_umbrella_backup_database_batch';

    public function hasScheduledBatchInProcess(): bool
    {
        try {
            return
				$this->as_has_scheduled_action(self::ACTION_BACKUP_FILES, [], self::GROUP) ||
				$this->as_has_scheduled_action(self::ACTION_BACKUP_DATABASE, [], self::GROUP) ||
				$this->hasRunningBatchInProcess();
        } catch (\Exception $e) {
            return false;
        }

    }

    public function as_has_scheduled_action( $hook, $args = null, $group = '' ) {

        $query_args = array(
            'hook'     => $hook,
            'status'   => array( ActionScheduler_Store::STATUS_RUNNING, ActionScheduler_Store::STATUS_PENDING ),
            'group'    => $group,
            'orderby'  => 'none',
        );

        if ( null !== $args ) {
            $query_args['args'] = $args;
        }

        $store = ActionScheduler::store();
        if (!method_exists($store, 'query_action')) {
            $store = wp_umbrella_get_service('StoreRetrocompatibility');
        }


        $action_id = $store->query_action( $query_args );

        return $action_id !== null;
    }

    public function hasRunningBatchInProcess(): bool
    {
        return (
            $this->hasRunningHookInProcess(self::ACTION_BACKUP_FILES)
            || $this->hasRunningHookInProcess(self::ACTION_BACKUP_DATABASE)
        );
    }

    public function hasRunningHookInProcess($hook, $args = []): bool
    {
        if (!ActionScheduler::is_initialized(__FUNCTION__)) {
            return false;
        }

        $query_args = [
            'hook' => $hook,
            'status' => [ActionScheduler_Store::STATUS_RUNNING],
            'group' => self::GROUP,
            'orderby' => 'none',
        ];

        if (null !== $args) {
            $query_args['args'] = $args;
        }

        $store = ActionScheduler::store();
        if (!method_exists($store, 'query_action')) {
            $store = wp_umbrella_get_service('StoreRetrocompatibility');
        }

        $action_id = $store->query_action($query_args);

        return $action_id !== null;
    }

    public function scheduledBatchFiles()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_FILES, [], self::GROUP);
    }

    public function scheduledBatchDatabase()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_DATABASE, [], self::GROUP);
    }

    public function unscheduledBatch()
    {
        as_unschedule_action(self::ACTION_BACKUP_FILES, [], self::GROUP);
        as_unschedule_action(self::ACTION_BACKUP_DATABASE, [], self::GROUP);
    }
}
