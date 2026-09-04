<?php
namespace WPUmbrella\Services\Core;

/**
 * The core update offers WordPress holds, and the one we are allowed to apply.
 *
 * `update_core` lists one entry per version the site could move to. Only the
 * ones marked `upgrade` are real updates: `latest` is the version the site
 * already runs, and `autoupdate` describes a background minor update, which
 * wp-admin leaves out of a user-requested upgrade too.
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
     */
    public function resolve()
    {
        $offer = $this->find($this->load());

        if ($offer !== null || !function_exists('wp_version_check')) {
            return $offer;
        }

        wp_umbrella_debug_log("Core update: no upgrade offer, refreshing the transient");
        wp_version_check([], true);

        return $this->find($this->load());
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
}
