<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Semantic diff between two WordPress role maps.
 *
 * WordPress compares options with maybe_serialize(), so key order and loose
 * typing count as a change. Comparing normalized role => granted capability
 * sets tells whether the capability map really moved.
 */
class RoleMapDiff
{
    /**
     * @param mixed $oldValue
     * @param mixed $newValue
     *
     * @return array{rolesAdded: array<int, string>, rolesRemoved: array<int, string>, capabilitiesAdded: array<int, string>, capabilitiesRemoved: array<int, string>}|null
     */
    public static function compute($oldValue, $newValue)
    {
        if (!is_array($oldValue) || !is_array($newValue)) {
            return null;
        }

        $old = self::normalize($oldValue);
        $new = self::normalize($newValue);

        $rolesAdded = array_values(array_diff(array_keys($new), array_keys($old)));
        $rolesRemoved = array_values(array_diff(array_keys($old), array_keys($new)));

        $capabilitiesAdded = [];
        $capabilitiesRemoved = [];

        foreach ($new as $role => $capabilities) {
            if (!isset($old[$role])) {
                continue;
            }

            foreach (array_diff($capabilities, $old[$role]) as $capability) {
                $capabilitiesAdded[] = $role . ':' . $capability;
            }

            foreach (array_diff($old[$role], $capabilities) as $capability) {
                $capabilitiesRemoved[] = $role . ':' . $capability;
            }
        }

        sort($rolesAdded);
        sort($rolesRemoved);
        sort($capabilitiesAdded);
        sort($capabilitiesRemoved);

        return [
            'rolesAdded' => $rolesAdded,
            'rolesRemoved' => $rolesRemoved,
            'capabilitiesAdded' => $capabilitiesAdded,
            'capabilitiesRemoved' => $capabilitiesRemoved,
        ];
    }

    /**
     * @param array $diff
     *
     * @return bool
     */
    public static function isEmpty(array $diff)
    {
        return $diff['rolesAdded'] === []
            && $diff['rolesRemoved'] === []
            && $diff['capabilitiesAdded'] === []
            && $diff['capabilitiesRemoved'] === [];
    }

    /**
     * @param array $diff
     *
     * @return bool
     */
    public static function hasGain(array $diff)
    {
        return $diff['rolesAdded'] !== [] || $diff['capabilitiesAdded'] !== [];
    }

    /**
     * Role slug => sorted list of granted capabilities. Denied capabilities
     * (falsy values) are dropped, display names are ignored.
     *
     * @param array $roles
     *
     * @return array<string, array<int, string>>
     */
    protected static function normalize(array $roles)
    {
        $normalized = [];

        foreach ($roles as $role => $definition) {
            $capabilities = [];

            if (is_array($definition) && isset($definition['capabilities']) && is_array($definition['capabilities'])) {
                foreach ($definition['capabilities'] as $capability => $granted) {
                    if ($granted) {
                        $capabilities[] = (string) $capability;
                    }
                }
            }

            sort($capabilities);
            $normalized[(string) $role] = $capabilities;
        }

        ksort($normalized);

        return $normalized;
    }
}
