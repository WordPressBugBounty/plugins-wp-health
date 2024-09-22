<?php

namespace WPUmbrella\Services\Plugin\Compatibility;

class ElementorDatabase {


	protected function get_update_db_manager_class() {

		if( class_exists( '\ElementorPro\Core\Upgrade\Manager' ) ){
			return '\ElementorPro\Core\Upgrade\Manager';
		}

		if( class_exists( '\Elementor\Core\Upgrade\Manager' ) ){
			return '\Elementor\Core\Upgrade\Manager';
		}

		return null;
	}

	public function updateDatabase(){
		$manager_class = $this->get_update_db_manager_class();

		if( ! $manager_class ){
			return;
		}

		/** @var \Elementor\Core\Upgrade\Manager $manager */
		$manager = new $manager_class();

		$updater = $manager->get_task_runner();

		try {
			if ( $updater->is_process_locked() && empty( $assoc_args['force'] ) ) {
				//'Oops! Process is already running. Use --force to force run.
				return "already_running";
			}

			if ( ! $manager->should_upgrade() ) {
				//The DB is already updated!
				return "already_upgraded";
			}

			$callbacks = $manager->get_upgrade_callbacks();
			$did_tasks = false;

			if ( ! empty( $callbacks ) ) {
				$updater->handle_immediately( $callbacks );
				$did_tasks = true;
			}

			$manager->on_runner_complete( $did_tasks );

		} catch (\Exception $e) {
		}
	}
}
