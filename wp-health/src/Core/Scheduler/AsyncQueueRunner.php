<?php
namespace WPUmbrella\Core\Scheduler;

use WPUmbrella\Services\Scheduler\Scheduler;
use WPUmbrella\Services\Scheduler\SchedulerLock;
use function add_action;
use function admin_url;
use function apply_filters;
use function is_admin;

trait AsyncQueueRunner
{
    /**
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * @var SchedulerLock
     */
    protected $schedulerLock;

    public function maybeDispatchAsyncRequestOnShutdown()
    {
        if (!is_admin()) {
            return;
        }

        if ($this->schedulerLock->isLocked(self::LOCK_KEY)) {
            return;
        }

        $this->schedulerLock->lock(self::LOCK_KEY, self::INTERVAL);

        if (!has_action(self::CRON_HOOK)) {
            return;
        }

        if (!$this->scheduler->isAllowed()) {
            return;
        }

        // Only start an async queue at most once every 60 seconds
        $this->dispatch();

        remove_action('shutdown', [$this, 'maybeDispatchAsyncRequestOnShutdown']);
    }

    public function ajaxHandle()
    {
        do_action(self::CRON_HOOK);

        if (!$this->scheduler->isAllowed()) {
            return;
        }

        sleep(5);

        $this->maybeDispatchAsyncRequestOnShutdown();
    }

    public function shutdownHooks()
    {
        add_action('shutdown', [$this, 'maybeDispatchAsyncRequestOnShutdown']);
    }

    public function asyncHooks()
    {
        add_action('wp_ajax_' . self::CRON_HOOK, [$this, 'maybeHandle']);
        add_action('wp_ajax_nopriv_' . self::CRON_HOOK, [$this, 'maybeHandle']);
    }

    public function maybeHandle()
    {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer(self::CRON_HOOK, 'nonce');

        $this->ajaxHandle();

        wp_die();
    }

    public function dispatch()
    {
        $url = add_query_arg($this->getQueryArgs(), $this->getQueryUrl());
        $args = $this->getPostArgs();

        return wp_remote_post(esc_url_raw($url), $args);
    }

    public function getPostArgs(): array
    {
        return [
            'blocking' => false,
            'timeout' => apply_filters('wp_umbrella_scheduler_http_request_timeout', 0.01),
            'httpversion' => '1.0',
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => [],
            'cookies' => $_COOKIE,
        ];
    }

    public function getQueryArgs(): array
    {
        return [
            'action' => self::CRON_HOOK,
            'nonce' => wp_create_nonce(self::CRON_HOOK),
        ];
    }

    public function getQueryUrl(): string
    {
        return admin_url('admin-ajax.php');
    }
}
