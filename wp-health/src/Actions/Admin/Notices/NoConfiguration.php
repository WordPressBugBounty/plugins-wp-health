<?php

namespace WPUmbrella\Actions\Admin\Notices;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Notices\NoConfiguration as TemplateFileNoConfiguration;

class NoConfiguration implements ExecuteHooksBackend
{
    public function hooks()
    {
        $apiKey = wp_umbrella_get_api_key();
        if (empty($apiKey)) {
            add_action('admin_notices', [$this, 'admin_notice']);
        }
	}

	public function admin_notice(){

		try {
			$screen = get_current_screen();

			if (!$screen) {
				return;
			}

			if($screen->id === "settings_page_wp-umbrella-settings"){
				return;
			}
	   	} catch (\Exception $e) {
		   return;
	   	}

		if(!file_exists(TemplateFileNoConfiguration::get_template_file())){
			return;
		}

		include_once TemplateFileNoConfiguration::get_template_file();
	}
}
