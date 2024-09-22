<?php
namespace WPUmbrella\Controller\Theme;

use WPUmbrella\Core\Models\AbstractController;

class Delete extends AbstractController
{
    public function executeGet($params)
    {
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$theme) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No theme'], 400);
        }

        $manageTheme = \wp_umbrella_get_service('ManageTheme');

        try {
            $data = $manageTheme->delete($theme);

            if (isset($data['status']) && $data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }

    public function executeDelete($params)
    {
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$theme) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No theme'], 400);
        }

        $manageTheme = \wp_umbrella_get_service('ManageTheme');

        try {
            $data = $manageTheme->delete($theme);

            if (isset($data['status']) && $data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
