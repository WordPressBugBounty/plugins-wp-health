<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Directory;

class Directories extends AbstractController
{
    public function executeGet($params)
    {
        $source = isset($params['source']) ? $params['source'] : null;

        $path = Directory::joinPaths(wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource(), $source);

        $data = wp_umbrella_get_service('DirectoryListing')->getData($path);

        return $this->returnResponse($data);
    }
}
