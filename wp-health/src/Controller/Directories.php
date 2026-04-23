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

        $path = $this->resolveSourcePath($defaultSource, $source, $host);

        if ($path === null) {
            return $this->returnResponse([
                'directories' => [],
                'files' => [],
                'base_path' => $defaultSource,
            ]);
        }

        $data = wp_umbrella_get_service('DirectoryListing')->getData($path);

        $data['base_path'] = $defaultSource;

        return $this->returnResponse($data);
    }

    protected function resolveSourcePath($defaultSource, $source, $host)
    {
        $shouldResolveAbsolute = apply_filters(
            'wp_umbrella_directories_resolve_absolute_path',
            $host === Host::PRESSABLE
        );

        if (!$shouldResolveAbsolute) {
            return Directory::joinPaths($defaultSource, (string) $source);
        }

        $candidate = ($source !== null && $source !== '' && $source[0] === '/')
            ? $source
            : Directory::joinPaths($defaultSource, (string) $source);

        $real = @realpath($candidate);
        $rootReal = @realpath($defaultSource);

        if ($real === false || $rootReal === false) {
            return null;
        }

        $rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($real . DIRECTORY_SEPARATOR, $rootPrefix) !== 0) {
            return null;
        }

        return $real;
    }
}
