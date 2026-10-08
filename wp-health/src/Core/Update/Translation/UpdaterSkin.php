<?php
namespace WPUmbrella\Core\Update\Translation;

use WPUmbrella\Core\Update\Plugin\UpdaterSkin as BaseUpdaterSkin;

/**
 * Language_Pack_Upgrader::bulk_upgrade() only returns the upgrader's last
 * stored result for each pack, which is stale when a pack fails before its
 * install step. The skin sees every pack, so the outcome is recorded here.
 */
class UpdaterSkin extends BaseUpdaterSkin
{
    public $language_update = null;

    protected $outcomes = [];

    public function set_result($result)
    {
        parent::set_result($result);

        if (is_wp_error($result)) {
            $this->recordError($result);
            return;
        }

        $key = $this->currentKey();

        if ($key !== null && !isset($this->outcomes[$key])) {
            $this->outcomes[$key] = true;
        }
    }

    public function error($errors, ...$args)
    {
        parent::error($errors, ...$args);

        $this->recordError($errors);
    }

    /**
     * @return true|array|null True when installed, ['code', 'message'] on failure, null when never attempted.
     */
    public function getOutcome($languageUpdate)
    {
        $key = spl_object_hash($languageUpdate);

        return isset($this->outcomes[$key]) ? $this->outcomes[$key] : null;
    }

    protected function currentKey()
    {
        return is_object($this->language_update) ? spl_object_hash($this->language_update) : null;
    }

    protected function recordError($errors)
    {
        $key = $this->currentKey();

        if ($key === null || (isset($this->outcomes[$key]) && is_array($this->outcomes[$key]))) {
            return;
        }

        if (is_wp_error($errors)) {
            $code = $errors->get_error_code();
            $message = $errors->get_error_message();
        } else {
            $code = is_string($errors) ? $errors : '';
            $message = isset($this->upgrader->strings[$code]) ? $this->upgrader->strings[$code] : $code;
        }

        $this->outcomes[$key] = [
            'code' => !empty($code) ? (string) $code : 'unknown_error',
            'message' => wp_strip_all_tags((string) $message),
        ];
    }
}
