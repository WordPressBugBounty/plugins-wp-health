<?php
namespace WPUmbrella\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Controller;
use WPUmbrella\Core\UmbrellaRequest;
use WPUmbrella\Core\Constants\SignedRequest;
use WPUmbrella\Core\Models\TraitApiController;
use WPUmbrella\Core\Models\TraitPhpController;
use WP_REST_Request;
use WP_REST_Response;

abstract class AbstractController
{
    use TraitApiController;
    use TraitPhpController;

    protected $options;

    /**
     * @var string|null
     */
    protected $signatureShadow = null;

    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * @param UmbrellaRequest $request
     *
     * @return void
     */
    protected function recordSignatureShadow(UmbrellaRequest $request)
    {
        if (empty($this->options['signature_shadow'])) {
            return;
        }

        $publicKey = wp_umbrella_get_public_key();
        if (!is_string($publicKey) || $publicKey === '') {
            return;
        }

        if (!$this->hasAnySigningHeader($request->getHeaders())) {
            $this->signatureShadow = 'unsigned';
            return;
        }

        $this->signatureShadow = wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isSignatureAuthorized($request)
            ? 'signature'
            : 'invalid';
    }

    /**
     * @param array $headers
     *
     * @return bool
     */
    protected function hasAnySigningHeader($headers)
    {
        if (!is_array($headers)) {
            return false;
        }

        $names = [
            SignedRequest::SIGNATURE_V2_HEADER,
            SignedRequest::SIGNATURE_HEADER,
            SignedRequest::TIMESTAMP_HEADER,
            SignedRequest::NONCE_HEADER,
        ];

        foreach ($names as $name) {
            if (isset($headers[strtolower($name)])) {
                return true;
            }
        }

        return false;
    }

    public function getFrom()
    {
        return isset($this->options['from']) ? $this->options['from'] : null;
    }

    public function getVersion()
    {
        return isset($this->options['version']) ? $this->options['version'] : 'v1';
    }

    public function getRoute()
    {
        return isset($this->options['route']) ? $this->options['route'] : null;
    }

    public function getMethod()
    {
        return isset($this->options['method']) ? $this->options['method'] : null;
    }

    public function getPermission()
    {
        return isset($this->options['permission']) ? $this->options['permission'] : null;
    }

    public function getNeedAdministrator()
    {
        return isset($this->options['need_administrator']) ? $this->options['need_administrator'] : false;
    }

    public function execute()
    {
        switch ($this->getFrom()) {
            case Controller::API:
            default:
                $this->executeApi();
                break;
            case Controller::PHP:
                $this->executePhp();
                break;
        }
    }

    public function returnResponse($data, $status = 200)
    {
        wp_umbrella_get_service('SessionStore')->removeUmbrellaSessions();

        // Append request trace breadcrumbs if any were recorded during this request
        if (is_array($data)) {
            $trace = wp_umbrella_get_service('RequestTrace')->getTrace();
            if ($trace !== null) {
                $data['_trace'] = $trace;
            }

            if ($this->signatureShadow !== null) {
                $data['signature_shadow'] = $this->signatureShadow;
            }
        }

        $from = $this->getFrom();

        switch ($from) {
            case Controller::API:
                return $this->getResponseApi($data, $status);
                break;
            case Controller::PHP:
                return $this->getResponsePhp($data, $status);
                break;
        }
    }
}
