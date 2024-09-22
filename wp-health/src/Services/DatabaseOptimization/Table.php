<?php
namespace WPUmbrella\Services\DatabaseOptimization;


class Table
{
	public function getData(){
		global $wpdb;

		return $wpdb->get_var("SELECT COUNT(table_name)
			FROM information_schema.TABLES
			WHERE table_schema = '" . DB_NAME . "'
			AND Engine <> 'InnoDB'
			AND data_free > 0"
		);
	}

	public function handle(){
		global $wpdb;
		$query = $wpdb->get_results("SELECT table_name, data_free
			FROM information_schema.TABLES
			WHERE table_schema = '" . DB_NAME . "'
			AND Engine <> 'InnoDB'
			AND data_free > 0");

		if(is_null($query)){
			return;
		}

		$data = [
			"total_optimized" => 0
		];
		foreach ( $query as $table ) {
			$wpdb->query( "OPTIMIZE TABLE wp_posts" );
			$data["total_optimized"]++;
		}

		return $data;
	}
}
