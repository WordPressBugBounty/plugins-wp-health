<?php
namespace WPUmbrella\CLI;

if (!defined('ABSPATH')) {
    exit;
}

class PairCommand
{
    /**
     * Pair this WordPress site with a WP Umbrella project using a pair code.
     *
     * ## OPTIONS
     *
     * --code=<pair_code>
     * : Single-use pair code issued by the partner provisioning flow.
     *
     * [--force]
     * : Re-pair an already-paired site. Existing api_key and request_token are cleared before pairing.
     *
     * ## EXAMPLES
     *
     *     wp wp-umbrella pair --code=abc123_long_base64url_string
     *     wp wp-umbrella pair --code=abc123_long_base64url_string --force
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assocArgs)
    {
        $pairCode = isset($assocArgs['code']) ? $assocArgs['code'] : '';
        $force = !empty($assocArgs['force']);

        if (!is_string($pairCode) || $pairCode === '') {
            \WP_CLI::error('--code is required.');
            return;
        }

        $service = $this->resolveService();

        if (!$service->isValidCodeFormat($pairCode)) {
            \WP_CLI::error('pair_code is missing or malformed (expected base64url, 16-512 chars).');
            return;
        }

        $alreadyPaired = $this->isAlreadyPaired();

        if ($alreadyPaired && !$force) {
            \WP_CLI::error('This site is already paired. Re-run with --force to clear the current state and pair again.');
            return;
        }

        $snapshot = null;

        if ($alreadyPaired && $force) {
            \WP_CLI::warning('Site is already paired. --force will clear existing state before pairing; the previous state is restored if pairing fails.');
            $snapshot = $service->snapshotPairingState();
            $service->clearPairingState();
        }

        $result = $service->pair($pairCode);

        if (!empty($result['ok'])) {
            $projectId = isset($result['project_id']) ? $result['project_id'] : '';
            \WP_CLI::success(sprintf('Site paired with project %s.', (string) $projectId));
            return;
        }

        if ($snapshot !== null) {
            $service->restorePairingState($snapshot);
            \WP_CLI::warning('Pairing failed; restored previous api_key and request_token.');
        }

        $error = isset($result['error']) ? $result['error'] : 'unknown_error';
        $message = isset($result['message']) ? $result['message'] : '';

        \WP_CLI::error(sprintf('%s%s', $error, $message !== '' ? ': ' . $message : ''));
    }

    protected function isAlreadyPaired()
    {
        $optionService = wp_umbrella_get_service('Option');

        $requestToken = $optionService->getRequestTokenWithoutCache();
        if (is_string($requestToken) && $requestToken !== '') {
            return true;
        }

        $apiKey = $optionService->getApiKeyWithoutCache();
        if (is_string($apiKey) && $apiKey !== '') {
            return true;
        }

        return false;
    }

    protected function resolveService()
    {
        return wp_umbrella_get_service('PairByCodeService');
    }
}
