<?php

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Helpers\GodTransient;

function wp_umbrella_get_data_current_from_current_file($file)
{
    $fileClean = str_replace(realpath(ABSPATH), '', $file);

    $stylesheetPath = get_stylesheet_directory();
    $templatePath = get_template_directory();

    $errorFrom['Plugin'] = false !== strpos($file, realpath(WP_PLUGIN_DIR)) ? $fileClean : false;
    $errorFrom['Child Theme'] = $stylesheetPath != $templatePath && false !== strpos($file, realpath($stylesheetPath) . '\\') ? $fileClean : false;
    $errorFrom['Parent Theme'] = false !== strpos($file, realpath($templatePath)) ? $fileClean : false;
    $errorFrom['Content'] = false !== strpos($file, realpath(WP_CONTENT_DIR)) ? $fileClean : false;
    $errorFrom['Unknown'] = $fileClean;

    $errorFrom = array_filter($errorFrom);
    $errorFromKey = key($errorFrom);
    $errorFromFile = addcslashes(reset($errorFrom), '\\');

    switch ($errorFromKey) {
        case 'Plugin':
            $errorFromBonus = trim(dirname(str_replace(realpath(WP_PLUGIN_DIR), '', $file)), '\\');
            $errorFromBonusArray = array_values(array_filter(explode('/', $errorFromBonus)));
            $slug = $errorFromBonusArray[0];

            $errorFromname = '';
            $plugins = get_plugins('/' . $slug);

            if ($plugin = reset($plugins)) {
                $errorFromname = $plugin['Name'];

                return [
                    'type' => 'plugin',
                    'slug' => $slug,
                    'name' => $plugin['Name'],
                    'title' => $plugin['Title'],
                    'description' => $plugin['Description'],
                    'version' => $plugin['Version'],
                    'author' => $plugin['Author'],
                    'author_uri' => $plugin['AuthorURI'],
                    'uri' => $plugin['PluginURI'],
                    'domain_path' => $plugin['DomainPath'],
                    'network' => $plugin['Network'],
                    'author_name' => $plugin['AuthorName'],
                ];
            }
            break;
        case 'Parent Theme':
        case 'Child Theme':
            $theme = wp_get_theme();
            if (!$theme) {
                return null;
            }

            return [
                'type' => 'theme',
                'name' => $theme->name,
                'title' => $theme->title,
                'description' => $theme->description,
                'version' => $theme->version,
                'author' => $theme->author,
                'author_uri' => $theme->author_uri,
                'parent_theme' => $theme->parent_theme,
                'template' => $theme->template,
                'stylesheet' => $theme->stylesheet,
            ];
            break;
    }

    return null;
}

add_action('action_wp_umbrella_send_errors_v2', 'wp_umbrella_send_errors', 10);

function wp_umbrella_send_errors()
{
    $data = get_option(GodTransient::ERRORS_SAVE);

    if (!$data || empty($data)) {
        return;
    }

    delete_option(GodTransient::ERRORS_SAVE);

    foreach ($data as $key => $error) {
        if (!isset($error['file'], $error['code'], $error['line'], $error['message'])) {
            continue;
        }

        try {
            if (!is_string($error['file'])) {
                continue;
            }

            $dataPost = wp_umbrella_get_data_current_from_current_file($error['file']);

            if (null === $dataPost) {
                continue;
            }

            $dataPost['file'] = $error['file'];
            $dataPost['line'] = $error['line'];
            $dataPost['code'] = $error['code'];
            $dataPost['message'] = $error['message'];
            // $dataPost['backtrace'] = $backtrace;
            $dataPost['php_version'] = phpversion();
            $dataPost['wordpress_version'] = get_bloginfo('version');

            $response = wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/errors', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', wp_umbrella_get_api_key()),
                    'X-Project' => site_url(),
                    'X-Project-Id' => wp_umbrella_get_project_id(),
                    'X-Secret-Token' => wp_umbrella_get_secret_token(),
                ],
                'body' => json_encode($dataPost),
            ]);

            if (is_wp_error($response)) {
                $response = wp_remote_post(WP_UMBRELLA_API_URL . '/v1/errors', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => sprintf('Bearer %s', wp_umbrella_get_api_key()),
                        'X-Project' => site_url(),
                        'X-Project-Id' => wp_umbrella_get_project_id(),
                        'X-Secret-Token' => wp_umbrella_get_secret_token(),
                    ],
                    'body' => json_encode($dataPost),
                ]);
            }
        } catch (\Exception $e) {
        }
    }
}
