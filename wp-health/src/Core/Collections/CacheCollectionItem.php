<?php
namespace WPUmbrella\Core\Collections;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

interface CacheCollectionItem
{
    public function clear();

    public static function isAvailable();
}
