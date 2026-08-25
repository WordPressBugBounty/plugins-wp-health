<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\UmbrellaRequest;

if (!defined('ABSPATH')) {
    exit;
}

class ReceiveSigningKey extends AbstractController
{
    public function executePost($params)
    {
        $signingKey = wp_umbrella_signing_key_from_response($params);

        if (!$signingKey) {
            return $this->returnResponse(['success' => false], 400);
        }

        $optionService = wp_umbrella_get_service('Option');

        $options = $optionService->getOptions(['secure' => false]);
        $currentState = isset($options['key_state']) ? $options['key_state'] : null;
        $existingPublicKey = isset($options['public_key']) ? $options['public_key'] : '';

        $replacesExistingKey = is_string($existingPublicKey)
            && $existingPublicKey !== ''
            && $existingPublicKey !== $signingKey['public_key'];

        if ($replacesExistingKey && ($currentState === 'new' || !$this->isSignedByStoredKey())) {
            return $this->returnResponse(['success' => false, 'code' => 'key_locked'], 409);
        }

        $options['public_key'] = $signingKey['public_key'];
        $options['key_id'] = $signingKey['key_id'];
        if ($currentState !== 'new') {
            $options['key_state'] = 'dual';
        }
        $optionService->setOptions($options);

        return $this->returnResponse([
            'success' => true,
            'key_id' => $signingKey['key_id'],
        ]);
    }

    public function executeGet($params)
    {
        return $this->executePost($params);
    }

    protected function isSignedByStoredKey()
    {
        return wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')
            ->isSignatureAuthorized(UmbrellaRequest::createFromGlobals());
    }
}
