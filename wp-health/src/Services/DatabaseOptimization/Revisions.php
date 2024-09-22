<?php
namespace WPUmbrella\Services\DatabaseOptimization;

class Revisions
{
    public function getData()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(ID)
			FROM $wpdb->posts
			WHERE post_type = 'revision'
		");
    }

    public function handle()
    {
        global $wpdb;
        $query = $wpdb->get_col(
            "SELECT ID
			FROM $wpdb->posts
			WHERE post_type = 'revision'"
        );

        if (is_null($query)) {
            return;
        }

        $data = [
            'total_optimized' => 0,
        ];

        foreach ($query as $id) {
            wp_delete_post_revision((int) $id) instanceof \WP_Post ? 1 : 0;
            $data['total_optimized']++;
        }

        return $data;
    }
}
