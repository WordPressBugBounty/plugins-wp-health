<?php

namespace WPUmbrella\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class GodTransient
{
    const ERROR_ALREADY_SEND = 'wp-umbrella-error-already-send';

    const ERRORS_SAVE = 'wp-umbrella-errors-save';
}
