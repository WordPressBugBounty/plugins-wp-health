<?php
namespace WPUmbrella\Services\Scheduler;

use WPUmbrella\Core\UmbrellaDateTime;
use WPUmbrella\Core\Scheduler\MemoryLimit;
use WPUmbrella\Core\Scheduler\TimeLimit;
use WPUmbrella\Models\Backup\BackupTask;
use WPUmbrella\Services\Api\Backup;

class SnapshotData implements Scheduler
{
    use TimeLimit;
    use MemoryLimit;

    public function isAllowed(): bool
    {
        return !$this->memoryExceeded();
    }

    public function execute()
    {
        try {
            delete_transient('wp_umbrella_snapshot_lock');

            wp_remote_post(
                admin_url('admin-ajax.php'),
                [
                    'timeout' => 30,
                    'blocking' => false,
                    'sslverify' => wp_umbrella_should_verify_ssl(),
                    'user-agent' => 'WPUmbrella',
                    'body' => wp_umbrella_snapshot_request_body(),
                ]
            );
        } catch (\Exception $e) {
            //No need to do anything
        }
    }
}
