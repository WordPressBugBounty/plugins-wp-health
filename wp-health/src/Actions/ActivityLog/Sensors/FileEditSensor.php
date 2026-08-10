<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures edits made through the built-in plugin and theme file editors.
 *
 * Event keys emitted:
 * - plugin.file_edited (HIGH)
 * - theme.file_edited  (HIGH)
 * - core.file_modified (HIGH)
 */
class FileEditSensor extends AbstractSensor
{
    const MAX_TRACKED_FILES = 5;

    /**
     * @var array<string, bool>
     */
    protected $tracked = [];

    /**
     * @return void
     */
    public function register()
    {
        add_action('wp_ajax_edit-theme-plugin-file', [$this, 'onEditorRequest'], 1);
    }

    /**
     * @return void
     */
    public function onEditorRequest()
    {
        $target = $this->resolveTarget($_POST);

        if ($target === null) {
            return;
        }

        if (isset($this->tracked[$target['path']]) || count($this->tracked) >= self::MAX_TRACKED_FILES) {
            return;
        }

        $this->tracked[$target['path']] = true;

        $this->scheduleShutdown($target, $this->fingerprint($target['path']));
    }

    /**
     * @param array      $target
     * @param array|null $before
     *
     * @return void
     */
    protected function scheduleShutdown($target, $before)
    {
        register_shutdown_function([$this, 'onRequestShutdown'], $target, $before);
    }

    /**
     * @param array      $target
     * @param array|null $before
     *
     * @return void
     */
    public function onRequestShutdown($target, $before)
    {
        $after = $this->fingerprint($target['path']);

        if ($after === $before) {
            return;
        }

        $this->recordEvent($target['eventKey'], 'HIGH', array_filter([
            'path' => $this->relativePath($target['path']),
            'pluginSlug' => isset($target['pluginSlug']) ? $target['pluginSlug'] : null,
            'themeStylesheet' => isset($target['themeStylesheet']) ? $target['themeStylesheet'] : null,
            'created' => $before === null ? 'true' : null,
            'sizeBefore' => $before === null ? null : (string) $before['size'],
            'sizeAfter' => $after === null ? null : (string) $after['size'],
        ], function ($value) {
            return $value !== null;
        }));
    }

    /**
     * @param array $request
     *
     * @return array|null
     */
    protected function resolveTarget($request)
    {
        if (!is_array($request) || empty($request['file']) || !is_string($request['file'])) {
            return null;
        }

        $file = wp_unslash($request['file']);

        if (function_exists('validate_file') && validate_file($file) !== 0) {
            return null;
        }

        $plugin = isset($request['plugin']) && is_string($request['plugin']) ? wp_unslash($request['plugin']) : '';
        $theme = isset($request['theme']) && is_string($request['theme']) ? wp_unslash($request['theme']) : '';

        if ($theme !== '') {
            if (!$this->canEdit('edit_themes')) {
                return null;
            }

            $root = $this->themeDirectory($theme);

            if ($root === null) {
                return null;
            }

            return $this->describe($root . '/' . $file, 'theme.file_edited', ['themeStylesheet' => $theme]);
        }

        if ($plugin !== '' && defined('WP_PLUGIN_DIR')) {
            if (!$this->canEdit('edit_plugins')) {
                return null;
            }

            $slug = explode('/', $file);

            return $this->describe(
                WP_PLUGIN_DIR . '/' . $file,
                'plugin.file_edited',
                ['pluginSlug' => $slug[0]]
            );
        }

        return null;
    }

    /**
     * @param string $path
     * @param string $eventKey
     * @param array  $context
     *
     * @return array|null
     */
    protected function describe($path, $eventKey, array $context)
    {
        $path = $this->normalize($path);

        if ($path === null) {
            return null;
        }

        if ($this->isCorePath($path)) {
            return array_merge(['path' => $path, 'eventKey' => 'core.file_modified'], $context);
        }

        return array_merge(['path' => $path, 'eventKey' => $eventKey], $context);
    }

    /**
     * @param string $capability
     *
     * @return bool
     */
    protected function canEdit($capability)
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        return (bool) current_user_can($capability);
    }

    /**
     * @param string $stylesheet
     *
     * @return string|null
     */
    protected function themeDirectory($stylesheet)
    {
        if (!function_exists('wp_get_theme')) {
            return null;
        }

        $theme = wp_get_theme($stylesheet);

        if (!is_object($theme) || (method_exists($theme, 'exists') && !$theme->exists())) {
            return null;
        }

        $directory = $theme->get_stylesheet_directory();

        return is_string($directory) && $directory !== '' ? $directory : null;
    }

    /**
     * @param string $path
     *
     * @return string|null
     */
    protected function normalize($path)
    {
        $path = str_replace('\\', '/', $path);

        if (strpos($path, '../') !== false) {
            return null;
        }

        return $path;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    protected function isCorePath($path)
    {
        if (!defined('ABSPATH')) {
            return false;
        }

        $root = str_replace('\\', '/', rtrim(ABSPATH, '/\\'));

        foreach (['/wp-includes/', '/wp-admin/'] as $directory) {
            if (strpos($path, $root . $directory) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     *
     * @return array{size: int, hash: string}|null
     */
    protected function fingerprint($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        clearstatcache(true, $path);

        $size = @filesize($path);
        $hash = @md5_file($path);

        if ($size === false || $hash === false) {
            return null;
        }

        return ['size' => (int) $size, 'hash' => $hash];
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function relativePath($path)
    {
        if (!defined('ABSPATH')) {
            return $path;
        }

        $root = str_replace('\\', '/', rtrim(ABSPATH, '/\\')) . '/';

        if (strpos($path, $root) === 0) {
            return substr($path, strlen($root));
        }

        return $path;
    }
}
