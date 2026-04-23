<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

interface CollectorInterface
{
    public function getId();

    public function collect();
}
