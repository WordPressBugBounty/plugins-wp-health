<?php
namespace WPUmbrella\Controller\Security;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Analysis extends AbstractController
{
    const MAX_TYPES = 10;

    const ANALYZERS = [
        'hidden_admin' => 'HiddenAdminAnalyzer',
        'htaccess_posture' => 'HtaccessPostureAnalyzer',
        'unlisted_code' => 'UnlistedCodeAnalyzer',
    ];

    public function executePost($params)
    {
        $requested = $this->readTypes($params);

        if (empty($requested)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'unknown_analysis_type',
            ], 400);
        }

        $isBatch = $this->isBatch($params);

        if (!$isBatch) {
            $type = $requested[0];

            if (!isset(self::ANALYZERS[$type])) {
                return $this->returnResponse([
                    'success' => false,
                    'code' => 'unknown_analysis_type',
                ], 400);
            }

            return $this->returnResponse([
                'success' => true,
                'type' => $type,
                'result' => $this->run($type),
            ]);
        }

        $results = [];
        $unknown = [];

        foreach ($requested as $type) {
            if (!isset(self::ANALYZERS[$type])) {
                $unknown[] = $type;
                continue;
            }

            $results[$type] = $this->run($type);
        }

        if (empty($results)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'unknown_analysis_type',
                'unknown_types' => $unknown,
            ], 400);
        }

        return $this->returnResponse([
            'success' => true,
            'results' => $results,
            'unknown_types' => $unknown,
        ]);
    }

    protected function run($type)
    {
        return wp_umbrella_get_service(self::ANALYZERS[$type])->analyze();
    }

    protected function isBatch($params)
    {
        return isset($params['types']) && is_array($params['types']);
    }

    /**
     * @return string[]
     */
    protected function readTypes($params)
    {
        if ($this->isBatch($params)) {
            $types = [];

            foreach ($params['types'] as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }

                if (!in_array($type, $types, true)) {
                    $types[] = $type;
                }

                if (count($types) >= self::MAX_TYPES) {
                    break;
                }
            }

            return $types;
        }

        if (isset($params['type']) && is_string($params['type']) && $params['type'] !== '') {
            return [$params['type']];
        }

        return [];
    }
}
