<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class SystemReport extends AbstractController
{
    public function executeGet($params)
    {
        $options = [];

        // Filter sections: ?sections=security,database,active_plugins
        if (!empty($params['sections'])) {
            $options['sections'] = array_map('trim', explode(',', $params['sections']));
        }

        // Error log lines: ?error_log_lines=100 (default: 50, max: 500)
        if (isset($params['error_log_lines'])) {
            $options['error_log_lines'] = max(1, min(500, (int) $params['error_log_lines']));
        }

        $data = wp_umbrella_get_service('SystemReportProvider')->getData($options);

        return $this->returnResponse($data);
    }
}
