<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;

class CheckBackupCapabilities extends AbstractController
{
    protected function checkNinjaFirewall()
    {
        if (!function_exists('nfw_get_option')) {
            return false;
        }

        $options = nfw_get_option('nfw_options');

        if (isset($options['post_b64']) && !empty($options['post_b64'])) {
            return true;
        }

        return false;
    }

    protected function canCreateDatabaseFolder()
    {
        $baseDirectory = wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource();
        $databaseFolder = $baseDirectory . DIRECTORY_SEPARATOR . 'umb_database';

        if (!is_dir($databaseFolder)) {
            $capability = mkdir($databaseFolder, 0755, true);

            if ($capability) {
                rmdir($databaseFolder);
            }

            return $capability;
        }

        return true;
    }

    public function executeGet($params)
    {
        global $wpdb;

        return $this->returnResponse([
            'ninja_firewall_options' => [
                'post_b64' => $this->checkNinjaFirewall(),
            ],
            'can_create_database_folder' => $this->canCreateDatabaseFolder(),
        ]);
    }
}
