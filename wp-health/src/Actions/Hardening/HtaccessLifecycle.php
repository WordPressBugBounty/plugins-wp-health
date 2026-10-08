<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\Hardening\TransientLock;
use WPUmbrella\Services\Security\HtaccessFile;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessLifecycle implements ActivationHook, DeactivationHook, ExecuteHooks
{
    const PENDING_WRITE_OPTION = 'wp_umbrella_htaccess_pending_write';
    const BACKFILL_RETRY_LOCK = 'wp_umbrella_htaccess_uploads_backfill_lock';
    const RECONCILE_RETRY_LOCK = 'wp_umbrella_htaccess_block_reconcile_lock';

    const RETRY_LOCK_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * @var TransientLock
     */
    protected $lock;

    public function __construct()
    {
        $this->lock = new TransientLock();
    }

    public function hooks()
    {
        add_action('admin_init', [$this, 'maybeCompletePendingWrite']);
        add_action('admin_init', [$this, 'maybeReconcileBlock']);
        add_action('admin_init', [$this, 'maybeSyncUploadsBlock']);
    }

    /**
     * The option is the source of truth and the file follows it. Covers a block
     * left behind by an older version, and one the write never produced because
     * the request died on its verification probes.
     */
    public function maybeReconcileBlock()
    {
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        if (!$this->ownsRootHtaccess() || !$this->canEditNetworkScopedKeys()) {
            return;
        }

        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('htaccess_umbrella_block')) {
            return;
        }

        $htaccess = wp_umbrella_get_service('HtaccessFile');

        if ($htaccess->getBlockVersion() === HtaccessFile::BLOCK_VERSION) {
            return;
        }

        if (!$this->lock->acquire(self::RECONCILE_RETRY_LOCK, self::RETRY_LOCK_TTL)) {
            return;
        }

        $result = $htaccess->writeUmbrellaBlock();

        if (isset($result['status']) && $result['status'] === 'ok') {
            $this->lock->release(self::RECONCILE_RETRY_LOCK);
        }
    }

    /**
     * Ordered by cost: an autoloaded option, then one small file read, and only
     * on a block that has to move the write and its verification requests.
     */
    public function maybeSyncUploadsBlock()
    {
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        $htaccess = wp_umbrella_get_service('HtaccessFile');

        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('htaccess_umbrella_block')) {
            if ($htaccess->hasUploadsBlock()) {
                $htaccess->cleanUploadsBlock();
            }

            return;
        }

        if ($htaccess->hasCurrentUploadsBlock()) {
            return;
        }

        if (!$this->lock->acquire(self::BACKFILL_RETRY_LOCK, self::RETRY_LOCK_TTL)) {
            return;
        }

        $result = $htaccess->writeUploadsBlock();

        if (isset($result['status']) && $result['status'] === 'ok') {
            $this->lock->release(self::BACKFILL_RETRY_LOCK);
        }
    }

    public function activate()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('htaccess_umbrella_block')) {
            return;
        }

        if (wp_umbrella_get_service('HtaccessFile')->hasUmbrellaBlock()) {
            return;
        }

        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            update_option(self::PENDING_WRITE_OPTION, 1, false);
            return;
        }

        $this->writeOrDisable();
    }

    public function maybeCompletePendingWrite()
    {
        if (wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        if (!get_option(self::PENDING_WRITE_OPTION)) {
            return;
        }

        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            return;
        }

        delete_option(self::PENDING_WRITE_OPTION);

        $this->writeOrDisable();
    }

    public function deactivate()
    {
        if ($this->ownsRootHtaccess()) {
            wp_umbrella_get_service('HtaccessFile')->cleanUmbrellaBlock();
        } else {
            wp_umbrella_get_service('HtaccessFile')->cleanUploadsBlock();
        }

        delete_option(self::PENDING_WRITE_OPTION);
        delete_transient(self::BACKFILL_RETRY_LOCK);
        delete_transient(self::RECONCILE_RETRY_LOCK);
    }

    protected function ownsRootHtaccess()
    {
        return wp_umbrella_get_service('HardeningSettings')->ownsRootHtaccess();
    }

    protected function canEditNetworkScopedKeys()
    {
        return wp_umbrella_get_service('HardeningSettings')->canEditNetworkScopedKeys();
    }

    protected function writeOrDisable()
    {
        if (!$this->ownsRootHtaccess() || !$this->canEditNetworkScopedKeys()) {
            return;
        }

        $settings = wp_umbrella_get_service('HardeningSettings');

        if (!$settings->isEnabled('htaccess_umbrella_block')) {
            return;
        }

        $htaccess = wp_umbrella_get_service('HtaccessFile');

        if ($htaccess->hasUmbrellaBlock()) {
            return;
        }

        $result = $htaccess->writeUmbrellaBlock();

        if (!$settings->blockWasApplied($result)) {
            $settings->updateSettings(['htaccess_umbrella_block' => false]);
        }
    }
}
