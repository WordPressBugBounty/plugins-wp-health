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
                    'sslverify' => false,
                    'user-agent' => 'WPUmbrella',
                    'headers' => [
                        'X-Umbrella' => wp_umbrella_get_api_key(),
                    ],
                    'body' => [
                        'action' => 'wp_umbrella_snapshot_data',
                        'nonce' => wp_create_nonce('wp_umbrella_snapshot_data'),
                        'api_key' => wp_umbrella_get_api_key(),
                    ]
                ]
            );
        } catch (\Exception $e) {
            //No need to do anything
        }
    }
}
