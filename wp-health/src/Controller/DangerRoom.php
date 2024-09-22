<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;


class DangerRoom extends AbstractController
{
    public function executePost($params)
    {
		update_option('wp_umbrella_disallow_one_click_access', true);
		delete_option(WP_UMBRELLA_SLUG);

		do_action('wp_umbrella_danger_room');

        return $this->returnResponse([
			'code' => 'success'
		]);
    }
}
