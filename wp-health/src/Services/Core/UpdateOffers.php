<?php
namespace WPUmbrella\Services\Core;

/**
 * The core update offers WordPress holds, and the one we are allowed to apply.
 *
 * `update_core` lists one entry per version the site could move to. Only the
 * ones marked `upgrade` are real updates: `latest` is the version the site
 * already runs, and `autoupdate` describes a background minor update, which
 * wp-admin leaves out of a user-requested upgrade too.
 *
 * Asked for a given version, the `autoupdate` entries do count: they are how
 * wordpress.org lists the last release of every branch behind the newest one,
 * which is what lets a site on 6.8 take the security release of its branch
 * without being moved to the next major.
 */
class UpdateOffers
{
    const NAME_SERVICE = 'CoreUpdateOffers';

    protected $transient = null;

    /**
     * The offer to apply, or null when WordPress has none to give.
     *
     * A transient carrying no `upgrade` offer is worth a second look before we
     * conclude there is nothing to do. It can be stale because wp_cron never
     * refreshed it, or gone because an object cache with no persistent backend
     * dropped it, and both look exactly like a site that is up to date. Asking
     * WordPress again is the only way to tell them apart, and it is only worth
     * the round trip once nothing was found.
     *
     * @param string|null $target The version to move to, the highest `upgrade`
     *                            offer when null.
     */
    public function resolve($target = null)
    {
        $offer = $this->pick($target);

        if ($offer !== null || !function_exists('wp_version_check')) {
            return $offer;
        }

        wp_umbrella_debug_log("Core update: no offer found, refreshing the transient");
        wp_version_check([], true);

        return $this->pick($target);
    }

    /**
     * Every version the site can move up to, keyed by version.
     */
    public function available()
    {
        return $this->above($this->load(), $this->installedVersion());
    }

    /**
     * Every offer the transient carried when it was last read.
     */
    public function all()
    {
        return isset($this->transient->updates) ? (array) $this->transient->updates : [];
    }

    /**
     * The `response` of every offer, to report what WordPress had to say.
     */
    public function responses()
    {
        $responses = [];

        foreach ($this->all() as $offer) {
            $responses[] = isset($offer->response) ? $offer->response : 'none';
        }

        return $responses;
    }

    protected function pick($target)
    {
        $updates = $this->load();

        if ($target === null) {
            return $this->find($updates);
        }

        $available = $this->above($updates, $this->installedVersion());

        return isset($available[$target]) ? $available[$target] : null;
    }

    protected function installedVersion()
    {
        return wp_umbrella_get_service('WordPressProvider')->getWordPressVersion();
    }

    protected function load()
    {
        $this->transient = wp_umbrella_get_service('WordPressContext')->getTransient('update_core');

        return $this->all();
    }

    /**
     * The highest `upgrade` offer, so a transient carrying several of them
     * settles on the same version whichever update path reads it.
     */
    protected function find($updates)
    {
        $found = null;

        foreach ($updates as $offer) {
            if (!isset($offer->response, $offer->current) || $offer->response !== 'upgrade') {
                continue;
            }

            if ($found === null || version_compare($offer->current, $found->current, '>')) {
                $found = $offer;
            }
        }

        return $found;
    }

    /**
     * One offer per version strictly above the installed one, so a stale
     * transient can never walk the site back to an older release.
     *
     * wordpress.org lists the localized `upgrade` ahead of the en_US one and the
     * `upgrade` ahead of the `autoupdate` entries, so the first offer met for a
     * version is the one to keep.
     */
    protected function above($updates, $installed)
    {
        $available = [];

        foreach ($updates as $offer) {
            if (!isset($offer->response, $offer->current) || !in_array($offer->response, ['upgrade', 'autoupdate'], true)) {
                continue;
            }

            $version = (string) $offer->current;

            if (isset($available[$version]) || !version_compare($version, $installed, '>')) {
                continue;
            }

            $available[$version] = $offer;
        }

        return $available;
    }
}
