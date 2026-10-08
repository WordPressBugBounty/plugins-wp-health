<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

class ReleaseUpdateLock extends AbstractController
{
    public function executeGet($params)
    {
        return $this->executePost($params);
    }

    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;
        $theme = isset($params['theme']) ? $params['theme'] : null;
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;

        if ((!$plugin && !$theme) || !is_string($lockToken) || $lockToken === '') {
            return $this->returnResponse([
                'code' => 'missing_parameters',
                'success' => false,
            ]);
        }

        $lock = wp_umbrella_get_service('UpdateLock');
        $slug = $plugin ? dirname($plugin) : $lock->themeSlug($theme);

        if (!$lock->isHeldByOther($slug, $lockToken) && $lock->phase($slug) === UpdateLock::PHASE_UPDATING) {
            return $this->returnResponse([
                'code' => 'success',
                'released' => false,
            ]);
        }

        $released = $lock->release($slug, $lockToken);

        return $this->returnResponse([
            'code' => 'success',
            'released' => $released,
        ]);
    }
}
