<?php
namespace WPUmbrella\Services\SiteHealth;

class CurlInterceptor
{
    const HOOK_NAME = 'http_api_curl';

    private $handle = null;
    private $options = [];
    private $requireInterception;

    public function __construct($requireInterception = true)
    {
        $this->requireInterception = $requireInterception;
    }

    private function reset()
    {
        $this->handle = null;
    }

    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function handleHook($handle)
    {
        $this->handle = $handle;
        curl_setopt_array($handle, $this->options);
    }

    public function intercept($callable)
    {
        $this->reset();
        $action = [$this, 'handleHook'];
        add_action(self::HOOK_NAME, $action);
        $result = $callable();
        if ($this->handle === null && $this->requireInterception) {
            throw new Exception('Not a valid cURL handle');
        }
        remove_action(self::HOOK_NAME, $action);
        return $result;
    }
}
