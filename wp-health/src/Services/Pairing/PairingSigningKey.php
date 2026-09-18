<?php
namespace WPUmbrella\Services\Pairing;

if (!defined('ABSPATH')) {
    exit;
}

class PairingSigningKey
{
    /**
     * @param mixed $storedProjectId
     * @param mixed $projectId
     *
     * @return bool
     */
    public function isSameProject($storedProjectId, $projectId)
    {
        if ($projectId === null || $projectId === '') {
            return false;
        }

        if ($storedProjectId === null || $storedProjectId === '') {
            return false;
        }

        return (string) $storedProjectId === (string) $projectId;
    }

    /**
     * @param mixed $storedProjectId
     * @param mixed $projectId
     *
     * @return bool
     */
    public function isProjectChanged($storedProjectId, $projectId)
    {
        if ($projectId === null || $projectId === '') {
            return false;
        }

        if ($storedProjectId === null || $storedProjectId === '') {
            return false;
        }

        return !$this->isSameProject($storedProjectId, $projectId);
    }

    /**
     * @param array $options
     * @param array|null $signingKey
     * @param string $context
     *
     * @return array
     */
    public function applySigningKey($options, $signingKey, $context = 'pairing')
    {
        if (!$signingKey) {
            return $options;
        }

        $existingPublicKey = isset($options['public_key']) ? $options['public_key'] : '';
        $hasExistingPublicKey = is_string($existingPublicKey) && $existingPublicKey !== '';

        if ($hasExistingPublicKey && $existingPublicKey !== $signingKey['public_key']) {
            wp_umbrella_debug_log($context . ': signing key not stored');

            return $options;
        }

        $options['public_key'] = $signingKey['public_key'];
        $options['key_id'] = $signingKey['key_id'];

        if (!$hasExistingPublicKey || !isset($options['key_state']) || $options['key_state'] !== 'new') {
            $options['key_state'] = 'dual';
        }

        return $options;
    }

    /**
     * @param array $options
     * @param mixed $storedProjectId
     * @param mixed $projectId
     *
     * @return array
     */
    public function clearWhenProjectChanged($options, $storedProjectId, $projectId)
    {
        if (!$this->isProjectChanged($storedProjectId, $projectId)) {
            return $options;
        }

        return $this->clearSigningKey($options);
    }

    /**
     * A project that does not exist yet has no key to be reached with, so the
     * site starts this one over from nothing.
     *
     * @param array $options
     *
     * @return array
     */
    public function clearForNewProject($options)
    {
        return $this->clearSigningKey($options);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function clearSigningKey($options)
    {
        $options['public_key'] = '';
        $options['key_id'] = '';
        $options['key_state'] = '';

        return $options;
    }
}
