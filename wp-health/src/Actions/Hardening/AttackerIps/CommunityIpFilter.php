<?php

namespace WPUmbrella\Actions\Hardening\AttackerIps;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

class CommunityIpFilter
{
    const FILTER_TTL = 604800;

    const ORACLE_TIMEOUT_MS = 800;

    protected $storage;

    public function __construct(?FilterStorage $storage = null)
    {
        $this->storage = $storage !== null ? $storage : new FilterStorage();
    }

    public function isBlocked($ip)
    {
        $canonical = BloomFilter::canonicalizeIp($ip);

        if ($canonical === null) {
            return false;
        }

        $blob = $this->resolveFilter();

        if ($blob === null) {
            return false;
        }

        return BloomFilter::isMember($blob, $canonical);
    }

    protected function resolveFilter()
    {
        $fetchedAt = $this->storage->getFetchedAt();
        $blob = $this->storage->load();

        if ($blob !== null && (time() - $fetchedAt) < self::FILTER_TTL) {
            return $blob;
        }

        $fresh = $this->fetchFilter();

        if ($fresh === null) {
            return $blob;
        }

        return $fresh;
    }

    protected function fetchFilter()
    {
        $projectId = wp_umbrella_get_project_id();

        if (empty($projectId)) {
            return null;
        }

        $url = sprintf(
            '%s/v1/projects/%s/login-guard/filter',
            WP_UMBRELLA_NEW_API_URL,
            rawurlencode($projectId)
        );

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', wp_umbrella_get_outbound_bearer()),
                'X-Project' => site_url(),
                'X-Project-Id' => $projectId,
                'X-Secret-Token' => wp_umbrella_get_secret_token(),
            ],
            'timeout' => self::ORACLE_TIMEOUT_MS / 1000,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $blob = wp_remote_retrieve_body($response);

        if (!BloomFilter::isValidBlob($blob)) {
            return null;
        }

        if (!$this->storage->store($blob)) {
            return null;
        }

        $this->storage->markFetched();

        return $blob;
    }
}
