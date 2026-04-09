<?php
namespace WPUmbrella\Controller\Theme;

use WPUmbrella\Core\Models\AbstractController;

class Update extends AbstractController
{
    public function executePost($params)
    {
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$theme) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No theme'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $manageTheme = \wp_umbrella_get_service('ManageTheme');

        $trace = \wp_umbrella_get_service('RequestTrace');

        try {
            $trace->addTrace('third_party_check_started');
            wp_umbrella_get_service('ThemesProvider')->checkDiviTheme();

            if (class_exists('\YOOtheme\Theme\Wordpress\ThemeLoader', false) || is_dir(get_theme_root() . '/yootheme')) {
                wp_umbrella_get_service('ThemesProvider')->checkYootheme(get_transient('update_themes'), [
                    'remote' => 'https://yootheme.com/api/update/yootheme_wp',
                    'id' => 'yootheme',
                    'name' => 'yootheme',
                    'stability' => 'stable'
                ]);
            }
            $trace->addTrace('third_party_check_done');

            $requireBackup = isset($params['require_backup']) ? (bool) $params['require_backup'] : true;
            $backupDone = isset($params['backup_done']) ? (bool) $params['backup_done'] : false;

            $data = $manageTheme->update($theme, [
                'require_backup' => $requireBackup,
                'backup_done' => $backupDone,
            ]);

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            $trace->addTrace('controller_exception', ['message' => $e->getMessage()]);
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
