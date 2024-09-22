<?php
namespace WPUmbrella\Services\Backup\V2;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrellaVendor\Symfony\Component\Filesystem\Filesystem;
use WPUmbrella\Core\Backup\Profile;

class BackupExecutor
{

	const NAME_SERVICE = 'BackupExecutorV2';

    /**
     * @param Profile $profile
     */
    public function backupSources(Profile $profile): array
    {
        $scratchDir = $profile->getScratchDirectory();

        $filesystem = new Filesystem();

        if (!is_dir($scratchDir)) {
            $filesystem->mkdir($scratchDir);
        }

        $response = [];
        foreach ($profile->getSources() as $key => $source) {
            $result = $source->fetch($scratchDir);
            if (!$result) {
                continue;
            }
            $response[$key] = $result;
        }

        return $response;
    }

    /**
     *
     * @param Profile $profile
     * @return BackupExecutor
     */
    public function zip(Profile $profile): BackupExecutor
    {
        $scratchDir = $profile->getScratchDirectory();
        $processor = $profile->getProcessor();

		$directorySuffix = get_option('wp_umbrella_backup_suffix_security');
        $processor->process($scratchDir, sprintf('%s/%s/', WP_UMBRELLA_DIR_WPU_BACKUP_BOX, $directorySuffix));

        return $this;
    }

	/**
	 * @param Profile $profile
	 * @param  $dataModel
	 */
    public function sendToDestinations(Profile $profile,  $dataModel)
    {
        foreach ($profile->getDestinations() as $destination) {
            $destination->send($profile->getProcessor()->getExtension(), $dataModel);
        }
    }

	/**
	 * @return void
	 */
    public function cleanup()
    {
		$this->destroyDir(WP_UMBRELLA_DIR_WPU_BACKUP_BOX, [
			WP_UMBRELLA_DIR_WPU_BACKUP_BOX . '/index.php',
			WP_UMBRELLA_DIR_WPU_BACKUP_BOX . '/.htaccess'
		]); // Dir temp
    }

    protected function destroyDir($dir, $excludes = [])
    {
        if (!\file_exists($dir)) {
            return;
        }

        if (!is_dir($dir) || is_link($dir)) {
            if (in_array($dir, $excludes, true)) {
                return true;
            }

            return wp_umbrella_remove_file($dir);
        }
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file, $excludes)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file, $excludes)) {
                    return false;
                }
            };
        }

        return @rmdir($dir);
    }
}
