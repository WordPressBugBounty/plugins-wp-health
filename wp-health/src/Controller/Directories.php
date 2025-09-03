<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Directory;
use WPUmbrella\Helpers\Host;

class Directories extends AbstractController
{
    public function executeGet($params)
    {
        $source = isset($params['source']) ? $params['source'] : null;

        $defaultSource = wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource();

        $host = wp_umbrella_get_service('HostResolver')->getCurrentHost();
        try {
            switch ($host) {
                case Host::FLYWHEEL:
                    if ($source !== null && !empty($source)) {
                        $source = str_replace('/www', '', $source);
                    }
                    break;
            }
        } catch (\Exception $e) {
            //Do nothing
        }

        $path = Directory::joinPaths(wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource(), $source);

        $data = wp_umbrella_get_service('DirectoryListing')->getData($path);

        return $this->returnResponse($data);
    }
}
