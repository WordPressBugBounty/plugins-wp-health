<?php
namespace WPUmbrella\Services\DatabaseOptimization;

class ExpiredTransient
{
    public function getData()
    {
        global $wpdb;
        $total = 0;
        try {
            $time = isset($_SERVER['REQUEST_TIME']) ? (int) $_SERVER['REQUEST_TIME'] : time();
            $total = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(option_id) FROM $wpdb->options
					WHERE option_name LIKE %s
					AND option_value < %d
				",
                    $wpdb->esc_like('_transient_timeout_') . '%',
                    $time
                )
            );
        } catch(\Exception $e) {
            $total = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(option_id) FROM $wpdb->options
					WHERE option_name LIKE %s
				",
                    $wpdb->esc_like('_transient_') . '%'
                )
            );
        }

        return (int) $total;
    }

    public function handle()
    {
        global $wpdb;
        try {
            $time = isset($_SERVER['REQUEST_TIME']) ? (int) $_SERVER['REQUEST_TIME'] : time();

            $query = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM $wpdb->options
					WHERE option_name LIKE %s
					AND option_value < %d",
                    $wpdb->esc_like('_transient_timeout_') . '%',
                    $time
                )
            );
        } catch (\Exception $e) {
            $query = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM $wpdb->options
					WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_') . '%'
                )
            );
        }

        if (is_null($query)) {
            return;
        }

        $data = [
            'total_optimized' => 0,
        ];
        foreach ($query as $transient) {
            $transient = str_replace('_transient_timeout_', '', $transient);

            delete_transient($transient);
            $data['total_optimized']++;
        }

        return $data;
    }
}
