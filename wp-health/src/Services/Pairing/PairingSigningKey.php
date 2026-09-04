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
     * @param array $options
     * @param mixed $storedProjectId
     * @param mixed $projectId
     *
     * @return array
     */
    public function clearWhenProjectChanged($options, $storedProjectId, $projectId)
    {
        if ($this->isSameProject($storedProjectId, $projectId)) {
            return $options;
        }

        $options['public_key'] = '';
        $options['key_id'] = '';
        $options['key_state'] = '';

        return $options;
    }
}
