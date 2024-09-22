<?php

namespace WPUmbrella\Core\Backup\Processor;

if (!defined('ABSPATH')) {
    exit;
}

class GzipArchiveProcessor extends ArchiveProcessor
{
    const DEFAULT_OPTIONS = '-czvf';

    public function __construct($namer, $options = self::DEFAULT_OPTIONS, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($namer, 'tar', $options, 'tar.gz', $timeout);
    }

}
