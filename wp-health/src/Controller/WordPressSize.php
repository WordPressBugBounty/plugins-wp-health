<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Actions\Size\SizeScanScheduler;
use WPUmbrella\Core\Models\AbstractController;

class WordPressSize extends AbstractController
{
    /**
     * Measuring the install walks every file of wp-content, which on a large
     * site costs more than the request is worth. By default the stored
     * measurement is returned as is and a background scan is queued when it is
     * missing or old. `?mode=sync` measures inline, for a person waiting on a
     * fresh number.
     */
    public function executeGet($params)
    {
        $mode = isset($params['mode']) ? $params['mode'] : 'async';

        try {
            $store = wp_umbrella_get_service('WordPressSizeStore');

            if ($mode === 'sync') {
                // A walk that gives up halfway stores nothing, and the reading
                // already on file is still the best answer we have.
                $measured = $store->refresh();

                if (is_null($measured)) {
                    $measured = $store->getState();
                }

                return $this->returnResponse($this->format($measured));
            }

            $scanQueued = false;

            if ($store->shouldScan()) {
                $scheduler = new SizeScanScheduler();
                $scheduler->schedule();
                $scanQueued = true;
            }

            return $this->returnResponse($this->format($store->getState(), $scanQueued));
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'sizes_not_computed',
            ]);
        }
    }

    /**
     * `scan_queued` tells the reader that the measurement it is being handed
     * has just been asked to be redone, so it knows to come back for the new
     * one rather than treat this answer as current.
     */
    protected function format($state, $scanQueued = false)
    {
        if (!is_array($state) || !isset($state['sizes']) || !is_array($state['sizes'])) {
            return [
                'code' => 'sizes_not_computed',
                'scan_queued' => $scanQueued,
            ];
        }

        $data = $state['sizes'];
        $data['code'] = 'success';
        $data['computed_at'] = isset($state['computed_at']) ? $state['computed_at'] : null;
        $data['scan_queued'] = $scanQueued;

        return $data;
    }
}
