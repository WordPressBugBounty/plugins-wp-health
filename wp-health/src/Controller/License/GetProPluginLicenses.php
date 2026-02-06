<?php
namespace WPUmbrella\Controller\License;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for retrieving pro plugin licenses
 *
 * API Endpoint: GET /wp-umbrella/v1/licenses
 *
 * Query Parameters:
 * - only_active: bool (optional, default: false) - Only return licenses for active plugins
 * - include_summary: bool (optional, default: true) - Include license summary statistics
 * - include_not_found: bool (optional, default: true) - Include plugins where license wasn't detected
 *
 * Response:
 * {
 *   "code": "success",
 *   "data": {
 *     "licenses": [...],
 *     "summary": {...}
 *   }
 * }
 */
class GetProPluginLicenses extends AbstractController
{
    /**
     * Handle GET request
     *
     * @param array $params Request parameters
     * @return \WP_REST_Response
     */
    public function executeGet($params)
    {
        try {
            $onlyActive = isset($params['only_active'])
                ? filter_var($params['only_active'], FILTER_VALIDATE_BOOLEAN)
                : false;

            $includeSummary = isset($params['include_summary'])
                ? filter_var($params['include_summary'], FILTER_VALIDATE_BOOLEAN)
                : true;

            $includeNotFound = isset($params['include_not_found'])
                ? filter_var($params['include_not_found'], FILTER_VALIDATE_BOOLEAN)
                : true;

            /** @var \WPUmbrella\Services\LicenseManager */
            $licenseManager = wp_umbrella_get_service('LicenseManager');

            $licenses = $licenseManager->getAllLicenses([
                'only_active' => $onlyActive,
                'include_not_found' => $includeNotFound,
            ]);

            $response = [
                'licenses' => $licenses,
            ];

            if ($includeSummary) {
                $response['summary'] = $licenseManager->getLicensesSummary();
            }

            /**
             * Filter the license response before returning
             *
             * @param array $response The response data
             * @param array $params The request parameters
             */
            $response = apply_filters('wp_umbrella_licenses_response', $response, $params);

            return $this->returnResponse([
                'code' => 'success',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
