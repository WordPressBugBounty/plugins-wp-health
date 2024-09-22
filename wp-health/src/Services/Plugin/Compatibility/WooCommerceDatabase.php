<?php
namespace WPUmbrella\Services\Plugin\Compatibility;


class WooCommerceDatabase
{
	public function updateDatabase(){
		$fileName = WP_PLUGIN_DIR.'/woocommerce/includes/class-wc-install.php';

        if (!file_exists($fileName)) {
			return;
		}

		include_once $fileName;

		if(!class_exists('WC_Install')){
			return;
		}

		\WC_Install::run_manual_database_update();
	}
}
