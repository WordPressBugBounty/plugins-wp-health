<?php
namespace WPUmbrella\Controller\Theme;

use WPUmbrella\Core\Models\AbstractController;

class ThemeDirectoryExist extends AbstractController
{
    public function executeGet($params)
    {
        // Like "twentytwentyfour"
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$theme) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No theme'], 400);
        }

        $manageTheme = wp_umbrella_get_service('ManageTheme');

        try {
            $version = $manageTheme->getVersionFromThemeFile($theme);

            $data = $manageTheme->themeDirectoryExist($theme);

            return $this->returnResponse([
                'success' => $version !== false ? $data['success'] : false,
                'version' => $version
            ]);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
