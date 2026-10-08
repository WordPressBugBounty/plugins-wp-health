<?php
namespace WPUmbrella\Controller\Translation;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Translation\Update as TranslationUpdate;

if (!defined('ABSPATH')) {
    exit;
}

class Update extends AbstractController
{
    public function executePost($params)
    {
        try {
            $items = isset($params['items']) ? $params['items'] : [];

            if (!$this->isValidItems($items)) {
                return $this->returnResponse([
                    'status' => 'error',
                    'code' => 'invalid_items',
                ], 400);
            }

            define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

            $data = wp_umbrella_get_service('TranslationUpdate')->update(array_values($items));

            if (!isset($data['status']) || $data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    protected function isValidItems($items)
    {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (!is_array($item)
                || !isset($item['type'], $item['slug'])
                || !in_array($item['type'], TranslationUpdate::TYPES, true)
                || !is_string($item['slug'])
                || $item['slug'] === '') {
                return false;
            }

            if (isset($item['language'])
                && (!is_string($item['language']) || !preg_match(TranslationUpdate::LANGUAGE_PATTERN, $item['language']))) {
                return false;
            }
        }

        return true;
    }
}
