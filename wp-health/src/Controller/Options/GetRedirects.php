<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\BrokenLinkChecker\RedirectTableManager;

class GetRedirects extends AbstractController
{
    const PER_PAGE = 200;

    public function executeGet($params)
    {
        return $this->getRedirects($params);
    }

    public function executePost($params)
    {
        return $this->getRedirects($params);
    }

    protected function getRedirects($params)
    {
        $page = max(1, intval($params['page'] ?? 1));
        $perPage = min(self::PER_PAGE, max(1, intval($params['per_page'] ?? self::PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        global $wpdb;
        $tableName = RedirectTableManager::getTableName();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}");

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_pattern, destination_url, redirect_type, match_type FROM {$tableName} ORDER BY id ASC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        $redirects = array_map(function ($row) {
            return [
                'sourcePattern' => $row['source_pattern'],
                'destinationUrl' => $row['destination_url'],
                'httpCode' => intval($row['redirect_type']),
                'matchType' => $row['match_type'],
            ];
        }, $rows ?: []);

        return $this->returnResponse([
            'success' => true,
            'redirects' => $redirects,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}
