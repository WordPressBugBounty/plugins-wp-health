<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Rank Math Pro
 */
class RankMathProLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'seo-by-rank-math-pro/rank-math-pro.php';
    protected $pluginName = 'Rank Math Pro';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $licenseData = get_option('rank_math_connect_data');

        if (empty($licenseData) || !is_array($licenseData)) {
            return null;
        }

        $apiKey = $licenseData['api_key'] ?? '';

        if (empty($apiKey)) {
            return null;
        }

        $status = !empty($apiKey) ? LicenseStatus::VALID : LicenseStatus::UNKNOWN;

        return $this->buildLicenseData($apiKey, $status, [
            'extra_data' => [
                'username' => $licenseData['username'] ?? null,
                'email' => $licenseData['email'] ?? null,
            ],
        ]);
    }
}
