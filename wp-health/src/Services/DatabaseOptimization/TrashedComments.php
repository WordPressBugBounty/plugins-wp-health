<?php
namespace WPUmbrella\Services\DatabaseOptimization;


class TrashedComments
{
	public function getData(){
		global $wpdb;
		return $wpdb->get_var("SELECT COUNT(comment_ID)
			FROM $wpdb->comments
			WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')"
		);
	}

	public function handle(){
		global $wpdb;
		$query = $wpdb->get_col("SELECT comment_ID
			FROM $wpdb->comments
			WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')"
		);

		if(is_null($query)){
			return;
		}

		$data = [
			"total_optimized" => 0,
		];

		foreach ( $query as $id ) {
			wp_delete_comment( intval( $id ), true );
			$data["total_optimized"]++;
		}

		return $data;
	}
}
