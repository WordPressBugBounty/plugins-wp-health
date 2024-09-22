<?php
namespace WPUmbrella\Controller\Theme;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Data extends AbstractController
{
    public function executeGet($params)
    {
        try {
            $themes = wp_umbrella_get_service('ThemesProvider')->getThemes();
            return $this->returnResponse($themes);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
