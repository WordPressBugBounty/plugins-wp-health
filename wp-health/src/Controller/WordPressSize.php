<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Actions\Size\SizeScanScheduler;
use WPUmbrella\Core\Models\AbstractController;

class WordPressSize extends AbstractController
{
    public function executeGet($params)
    {
        $mode = isset($params['mode']) ? $params['mode'] : 'async';

        try {
            $store = wp_umbrella_get_service('WordPressSizeStore');

            if ($mode === 'sync') {
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
