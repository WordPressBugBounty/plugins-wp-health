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

        $candidate = ($shouldResolveAbsolute && $source !== null && $source !== '' && $source[0] === '/')
            ? $source
            : Directory::joinPaths($defaultSource, (string) $source);

        $real = @realpath($candidate);

        if ($real === false) {
            return null;
        }

        if ($this->belongsToAnotherBlog($real)) {
            return null;
        }

        foreach ([$defaultSource, ABSPATH, WP_CONTENT_DIR] as $root) {
            if ($this->isInsideRoot($real, $root)) {
                return $real;
            }
        }

        return null;
    }

    protected function belongsToAnotherBlog($real)
    {
        if (!function_exists('is_multisite') || !is_multisite() || is_main_site()) {
            return false;
        }

        $uploads = wp_upload_dir(null, false);

        if (!is_array($uploads) || empty($uploads['basedir'])) {
            return false;
        }

        $baseDir = @realpath($uploads['basedir']);

        if ($baseDir === false) {
            return false;
        }

        if (!$this->isInsideRoot($real, dirname($baseDir))) {
            return false;
        }

        return !$this->isInsideRoot($real, $baseDir);
    }

    protected function isInsideRoot($real, $root)
    {
        $rootReal = @realpath($root);

        if ($rootReal === false) {
            return false;
        }

        $rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return strpos($real . DIRECTORY_SEPARATOR, $rootPrefix) === 0;
    }
}
