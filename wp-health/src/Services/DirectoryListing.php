<?php
namespace WPUmbrella\Services;

use WPUmbrellaVendor\Symfony\Component\Finder\Finder;

class DirectoryListing
{
    public function hasWordPressInSubfolder($directory)
    {
        $indexFile = $directory . '/index.php';

        if (!file_exists($indexFile)) {
            return false;
        }

        $indexText = file_get_contents($indexFile);

        $searchFor = '/wp-blog-header.php';

        if (stripos($indexText, $searchFor) === false) {
            return false;
        }

        return true;
    }

    public function getData($baseDirectory = ABSPATH)
    {
        $finderFiles = new Finder();
        $finderFiles->files()
                ->in($baseDirectory)
                ->ignoreUnreadableDirs()
                ->ignoreDotFiles(false)
                ->depth(0);

        $finderDirectories = new Finder();
        $finderDirectories->directories()
                ->in($baseDirectory)
                ->ignoreUnreadableDirs()
                ->ignoreDotFiles(false)
                ->depth(0);

        $directories = [];
        $files = [];

        foreach ($finderFiles as $key => $file) {
            $path = \str_replace(ABSPATH, '', $file->getRealPath());
            $size = 0;
            try {
                $size = $file->getSize();
            } catch (\Exception $e) {
                // no black magic
            }
            $files[] = [
                'file_path' => $path,
                'pathname' => $file->getRelativePathname(),
                'size' => $size,
            ];
        }
        foreach ($finderDirectories as $key => $file) {
            $path = \str_replace(ABSPATH, '', $file->getRealPath());
            $size = 0;
            try {
                $size = $file->getSize();
            } catch (\Exception $e) {
                // no black magic
            }
            $directories[] = [
                'file_path' => $path,
                'pathname' => $file->getRelativePathname(),
                'size' => $size
            ];
        }

        return [
            'directories' => $directories,
            'files' => $files
        ];
    }
}
