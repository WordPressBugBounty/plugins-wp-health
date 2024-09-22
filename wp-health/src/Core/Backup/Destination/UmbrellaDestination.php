<?php
namespace WPUmbrella\Core\Backup\Destination;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupDestination;

class UmbrellaDestination implements BackupDestination
{
    protected $api = null;

    /**
     * @param Namer $namer
     */
    public function __construct($namer = [])
    {
        $this->namer = $namer;
    }

    public function setApi($api)
    {
        $this->api = $api;
        return $this;
    }

    public function send($extension)
    {
        if ($this->api == null) {
            return null;
        }

        try {
            $filename = $this->getName($extension);
            $signedUrl = $this->api->getSignedUrlForUpload($filename);

            if (!$signedUrl) {
                return 'no_signed_url';
            }

            $this->api->postBackupBySignedUrl($signedUrl, $filename);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     *
     * @param string $extension
     * @return string
     */
    public function getName($extension)
    {
        return sprintf('%s.%s', $this->namer->getName(), $extension);
    }
}
