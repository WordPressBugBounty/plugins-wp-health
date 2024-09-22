<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class SetBackupVersion extends AbstractController
{
    /**
     * We use GET method to set the backup version of the plugin because
     * too many hosts block POST or PUT requests unnecessarily.
     */
    public function executeGet($params)
    {
        $version = isset($params['version']) ? $params['version'] : 'v4';

        update_option('wp_umbrella_backup_version', $version);

        return $this->returnResponse(['success' => true]);
    }
}
