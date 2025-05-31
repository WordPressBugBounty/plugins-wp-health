<?php
namespace WPUmbrella\Core;

use WPUmbrella\Helpers\Controller;

abstract class Controllers
{
    public static function getControllers()
    {
        return [
            '/v1/patchstack/license-activation' => [
                'route' => '/patchstack/license-activation',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Third\Patchstack\PatchstackLicenseActivation::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        // Some hosting providers don't like "POST" method
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Third\Patchstack\PatchstackLicenseActivation::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/patchstack/license-deactivation' => [
                'route' => '/patchstack/license-deactivation',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Third\Patchstack\PatchstackLicenseDeactivation::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        // Some hosting providers don't like "POST" method
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Third\Patchstack\PatchstackLicenseDeactivation::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/cleanup-safe-update' => [
                'route' => '/cleanup-safe-update',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\CleanupSafeUpdate::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/set-backup-version' => [
                'route' => '/set-backup-version',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Options\SetBackupVersion::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/database-tables' => [
                'route' => '/database-tables',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\BackupV4\DatabaseTables::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/prepare-backup-data' => [
                'route' => '/prepare-backup-data',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\BackupV4\PrepareBackupData::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/check-backup-capabilities' => [
                'route' => '/check-backup-capabilities',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\BackupV4\CheckBackupCapabilities::class,
                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/upload-module' => [
                'route' => '/upload-module',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\BackupV4\UploadModule::class,

                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/move-backup-module' => [
                'route' => '/move-backup-module',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\BackupV4\MoveBackupModule::class,

                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/cleanup-module' => [
                'route' => '/cleanup-module',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\BackupV4\CleanupModule::class,

                        'options' => [
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/danger-room' => [
                'route' => '/danger-room',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\DangerRoom::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/database-optimization' => [
                'route' => '/database-optimization',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\DatabaseOptimization::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\DatabaseOptimization::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/check-method' => [
                'route' => '/check-method',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\CheckMethod::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\CheckMethod::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/validation-application-token' => [
                'route' => '/validation-application-token',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Options\ValidateApplicationToken::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    // Some hosting providers don't like "POST" method
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Options\ValidateApplicationToken::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/regenerate-secret-token' => [
                'route' => '/regenerate-secret-token',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Options\RegenerateSecretToken::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_ONLY_API_TOKEN,
                        ]
                    ],
                    // Some hosting providers don't like "POST" method
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Options\RegenerateSecretToken::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_ONLY_API_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/migrate-api-key' => [
                'route' => '/migrate-api-key',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Options\MigrateApiKey::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/white-label' => [
                'route' => '/white-label',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Options\WhiteLabel::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/issues-monitoring' => [
                'route' => '/issues-monitoring',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Options\IssuesMonitoring::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/plugins' => [
                'route' => '/plugins',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Plugin\Data::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/plugins/single' => [
                'route' => '/plugins/single',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Plugin\DataSingle::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/update-plugins' => [
                'route' => '/update-plugins',
                'hook' => 'wp_loaded',
                'priority' => WP_UMBRELLA_MAX_PRIORITY_HOOK,
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\UpdateMultiple::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/update-plugin' => [
                'route' => '/update-plugin',
                'hook' => 'wp_loaded',
                'priority' => WP_UMBRELLA_MAX_PRIORITY_HOOK,
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\Update::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/plugin-directory-exist' => [
                'route' => '/plugin-directory-exist',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Plugin\DirectoryExist::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/restore-from-safe-backup' => [
                'route' => '/restore-from-safe-backup',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Plugin\MoveOldPlugin::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/plugin-upgrade-database' => [
                'route' => '/plugin-upgrade-database',
                'hook' => 'wp_loaded',
                'priority' => WP_UMBRELLA_MAX_PRIORITY_HOOK,
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\PluginUpgradeDatabase::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/delete-plugin' => [
                'route' => '/delete-plugin',
                'methods' => [
                    [
                        // Thanks some hostings
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Plugin\Delete::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'DELETE',
                        'class' => \WPUmbrella\Controller\Plugin\Delete::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/install-plugin' => [
                'route' => '/install-plugin',
                'hook' => 'wp_loaded',
                'priority' => WP_UMBRELLA_MAX_PRIORITY_HOOK,
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\Install::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/activate-plugin' => [
                'route' => '/activate-plugin',
                'hook' => 'wp_loaded',
                'priority' => WP_UMBRELLA_MAX_PRIORITY_HOOK,
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\Activate::class,
                        'options' => [
                            'prevent_active' => false,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/deactivate-plugin' => [
                'route' => '/deactivate-plugin',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\Deactivate::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/rollback-plugin' => [
                'route' => '/rollback-plugin',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Plugin\Rollback::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/themes' => [
                'route' => '/themes',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Theme\Data::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'DELETE',
                        'class' => \WPUmbrella\Controller\Theme\Delete::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/delete-theme' => [
                'route' => '/delete-theme',
                'methods' => [
                    [
                        // Thanks some hostings
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Theme\Delete::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/snapshot' => [
                'route' => '/snapshot',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Snapshot::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/update-theme' => [
                'route' => '/update-theme',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Theme\Update::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/informations' => [
                'route' => '/informations',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\UmbrellaInformations::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/scan' => [
                'route' => '/scan',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Scan::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/directories' => [
                'route' => '/directories',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Directories::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/tables' => [
                'route' => '/tables',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Tables::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/wordpress-data' => [
                'route' => '/wordpress-data',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\WordPressInfo::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/wordpress-sizes' => [
                'route' => '/wordpress-sizes',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\WordPressSize::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/users' => [
                'route' => '/users',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\User\Data::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/backups' => [
                'route' => '/backups',
                'version' => 'v1',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Backup\Init::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Backup\CurrentProcess::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ],
                    [
                        'method' => 'DELETE',
                        'class' => \WPUmbrella\Controller\Backup\DeleteProcess::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ]
                ]
            ],
            '/v1/backups/run' => [
                'route' => '/backups/run',
                'version' => 'v1',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Backup\RunProcess::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/backups/prepare-batch-database' => [
                'route' => '/backups/prepare-batch-database',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Backup\PrepareBatchDatabase::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ],
                ]
            ],
            '/v1/backups/check-batch-database' => [
                'route' => '/backups/check-batch-database',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Backup\CheckBatchDatabase::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ],
                ]
            ],
            '/v1/backups/scan' => [
                'route' => '/backups/scan',
                'methods' => [

                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Backup\Scan::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ],

                ]
            ],

            '/v1/clear-cache' => [
                'route' => '/clear-cache',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\ClearCache::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],

            '/v1/cores' => [
                'route' => '/cores',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Core\Data::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/update-core' => [
                'route' => '/update-core',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Core\Update::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                ]
            ],
            '/v1/restores/cleanup' => [
                'route' => '/restores',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Restore\V2\RestoreCleanup::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ]
                ]
            ],
            '/v1/restores/check' => [
                'route' => '/restores/check',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Restore\V2\RestoreCheck::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ]
                ]
            ],
            '/v1/restores/data' => [
                'route' => '/restores/data',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Restore\V2\GetData::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ]
                ]
            ],
            '/v1/restores/prepare-restore-files' => [
                'route' => '/restores/prepare-restore-files',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Restore\V2\PrepareRestoreFiles::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN
                        ]
                    ]
                ]
            ],
            '/v1/restores/download' => [
                'route' => '/restores/download',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Restore\V2\RestoreDownload::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/restores/unzip' => [
                'route' => '/restores/unzip',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Restore\V2\RestoreUnzip::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/restores/database' => [
                'route' => '/restores/database',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Restore\V2\RestoreDatabase::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/maintenance' => [
                'route' => '/maintenance',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\MaintenanceMode::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'DELETE',
                        'class' => \WPUmbrella\Controller\MaintenanceMode::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/delete-maintenance' => [
                'route' => '/delete-maintenance',
                'methods' => [
                    [
                        // Thanks some hostings
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\MaintenanceMode::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/languages' => [
                'route' => ''
            ],
            '/v1/options/project' => [
                'route' => '/options/project',
                'methods' => [
                    [
                        'method' => 'PUT',
                        'class' => \WPUmbrella\Controller\Options\OptionProjectId::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/login' => [
                'route' => '/login',
                'methods' => [
                    [
                        'method' => 'GET',
                        'class' => \WPUmbrella\Controller\Login::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\Login::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
            '/v1/umbrella-nonce-login' => [
                'route' => '/umbrella-nonce-login',
                'methods' => [
                    [
                        'method' => 'POST',
                        'class' => \WPUmbrella\Controller\UmbrellaNonceLogin::class,
                        'options' => [
                            'prevent_active' => true,
                            'permission' => Controller::PERMISSION_WITH_SECRET_TOKEN,
                        ]
                    ]
                ]
            ],
        ];
    }
}
