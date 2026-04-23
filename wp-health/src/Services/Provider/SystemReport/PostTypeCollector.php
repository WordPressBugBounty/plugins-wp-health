<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class PostTypeCollector implements CollectorInterface
{
    public function getId()
    {
        return 'post_type_counts';
    }

    public function collect()
    {
        $postTypes = get_post_types(['public' => true], 'names');
        $counts = [];

        foreach ($postTypes as $type) {
            $countObj = wp_count_posts($type);
            $counts[$type] = isset($countObj->publish) ? (int) $countObj->publish : 0;
        }

        return $counts;
    }
}
