<?php
namespace WPUmbrella\Controller\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Models\AbstractController;

class Scan extends AbstractController
{
    public function executeGet($params)
    {
        $data = wp_umbrella_get_service('BackupScan')->getData();

        return $this->returnResponse($data);
    }
}
