<?php
namespace WPUmbrella\Services\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use Morphism\Morphism;

class WordPressData
{
    const NAME_SERVICE = 'WordPressDataProvider';

    public function countPosts()
    {
        global $wp_post_types;

        $postTypes = get_post_types([
            'show_ui' => true,
            'public' => true,
        ]);
        unset(
            $postTypes['attachment'],
            $postTypes['seopress_rankings'],
            $postTypes['seopress_backlinks'],
            $postTypes['seopress_404'],
            $postTypes['elementor_library'],
            $postTypes['customer_discount'],
            $postTypes['cuar_private_file'],
            $postTypes['cuar_private_page'],
            $postTypes['ct_template']
        );

        $postTypes = apply_filters('wp_umbrella_post_types', $postTypes);

        try {
            global $wpdb;

            $count = $wpdb->get_row(
                "SELECT COUNT( * ) AS num_posts
                FROM $wpdb->posts
                WHERE post_type IN ('" . implode("','", $postTypes) . "')
                AND post_status = 'publish' ",
                ARRAY_A
            );

            if (!isset($count['num_posts'])) {
                return 0;
            }
            return (int) $count['num_posts'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function countAttachments()
    {
        try {
            global $wpdb;

            $count = $wpdb->get_row(
                "SELECT COUNT( * ) AS num_posts
				 FROM $wpdb->posts
				 WHERE post_type = 'attachment'
				 AND post_status != 'trash' ",
                ARRAY_A
            );
            if (!isset($count['num_posts'])) {
                return 0;
            }
            return (int) $count['num_posts'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getSizes()
    {
        if (!class_exists('WP_Debug_Data')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        }

        return \WP_Debug_Data::get_sizes();
    }

    public function getSnapshot()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return [
            'count_plugin' => count(get_plugins()),
            'theme' => wp_umbrella_get_service('ThemesProvider')->getCurrentTheme(),
            'wordpress_version' => wp_umbrella_get_service('WordPressProvider')->getWordPressVersion(),
            'count_public_post' => $this->countPosts(),
            'count_attachment' => $this->countAttachments(),
        ];
    }
}
