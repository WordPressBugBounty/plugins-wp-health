<?php

namespace WPUmbrella\Core\Backup\Processor;

if (!defined('ABSPATH')) {
    exit;
}

class ZipArchiveProcessor extends ArchiveProcessor
{
    const DEFAULT_OPTIONS = '-r';

    public function __construct($namer, $options = self::DEFAULT_OPTIONS, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($namer, 'zip', $options, 'zip', $timeout);
    }
}
