<?php

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Actions\TrackingError;
use WPUmbrella\God\ErrorBuffer;

/**
 * Action Scheduler handler for the captured PHP errors.
 *
 * Drains the local error buffer, POSTs the batch to the worker, and removes
 * the entries only once the API acknowledged them.
 */

define('WP_UMBRELLA_ERRORS_BATCH_SIZE', 50);
define('WP_UMBRELLA_ERRORS_MAX_ITERATIONS_PER_RUN', 5);
define('WP_UMBRELLA_ERRORS_MAX_DURATION_SECONDS_PER_RUN', 20);
define('WP_UMBRELLA_ERRORS_BACKOFF_OPTION', 'wp_umbrella_errors_backoff');
define('WP_UMBRELLA_ERRORS_BACKOFF_BASE_SECONDS', 900);
define('WP_UMBRELLA_ERRORS_BACKOFF_MAX_SECONDS', 86400);

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

add_action(TrackingError::ACTION_HOOK, 'wp_umbrella_send_errors', 10);

// Single actions scheduled by the previous plugin version can still be pending
// when the site updates.
add_action('action_wp_umbrella_send_errors_v2', 'wp_umbrella_send_errors', 10);

/**
 * Handles the recurring error sync.
 *
 * Drains the buffer in a bounded loop and stops on the first failure, so the
 * batch that failed is retried untouched once the backoff has elapsed.
 *
 * @return void
 */
function wp_umbrella_send_errors()
{
    // The caps come first and run on every tick, whatever the API answers.
    // A site the API keeps refusing still sees its buffer bounded by the
    // entry cap and emptied by the age cap, so a durable refusal can never
    // turn into an ever growing option.
    ErrorBuffer::prune();

    if (time() < wp_umbrella_errors_backoff_until()) {
        return;
    }

    $startedAt = microtime(true);

    for ($iteration = 0; $iteration < WP_UMBRELLA_ERRORS_MAX_ITERATIONS_PER_RUN; $iteration++) {
        if (microtime(true) - $startedAt >= WP_UMBRELLA_ERRORS_MAX_DURATION_SECONDS_PER_RUN) {
            break;
        }

        if ('success' !== wp_umbrella_send_errors_one_batch()) {
            break;
        }
    }
}

/**
 * Sends a single batch. Returns one of:
 *   - 'empty'         buffer empty, nothing to do
 *   - 'success'       batch acknowledged and removed, another one can follow
 *   - 'error_network' the request never reached the API, batch preserved
 *   - 'error_refused' the API refused the batch, batch preserved
 *   - 'error_rejected' the API cannot ever accept the batch, batch dropped
 *
 * Every outcome other than 'empty' and 'success' arms the backoff, so a site
 * the API refuses durably stops retrying on every tick.
 *
 * @return string
 */
function wp_umbrella_send_errors_one_batch()
{
    $buffer = ErrorBuffer::all();

    if (empty($buffer)) {
        return 'empty';
    }

    $batch = array_slice($buffer, 0, WP_UMBRELLA_ERRORS_BATCH_SIZE, true);

    $errors = [];
    $keys = [];

    foreach ($batch as $key => $error) {
        $keys[] = $key;

        try {
            $payload = wp_umbrella_build_error_payload($error);
        } catch (\Exception $e) {
            $payload = null;
        }

        if (null === $payload) {
            continue;
        }

        $errors[] = $payload;
    }

    // Nothing sendable in this slice: drop it so the next one moves up.
    if (empty($errors)) {
        ErrorBuffer::remove($keys);

        return 'success';
    }

    $response = wp_umbrella_post_errors($errors);

    if (is_wp_error($response)) {
        $response = wp_umbrella_post_errors($errors);
    }

    if (is_wp_error($response)) {
        wp_umbrella_errors_arm_backoff(0);

        return 'error_network';
    }

    $status = (int) wp_remote_retrieve_response_code($response);

    if (200 === $status) {
        ErrorBuffer::remove($keys);
        wp_umbrella_errors_clear_backoff();

        return 'success';
    }

    wp_umbrella_errors_arm_backoff(wp_umbrella_errors_retry_after($response));

    // A batch the API can never accept would otherwise sit at the head of the
    // buffer until the age cap collects it, blocking everything behind it.
    if (in_array($status, [400, 413, 422], true)) {
        ErrorBuffer::remove($keys);

        return 'error_rejected';
    }

    return 'error_refused';
}

/**
 * Timestamp before which no request must be attempted.
 *
 * @return int
 */
function wp_umbrella_errors_backoff_until()
{
    $backoff = get_option(WP_UMBRELLA_ERRORS_BACKOFF_OPTION, []);

    return isset($backoff['until']) ? (int) $backoff['until'] : 0;
}

/**
 * Pushes the next attempt further away after a failure. The delay doubles on
 * every consecutive failure, from one scheduler interval up to a day, unless
 * the API asked for a specific delay.
 *
 * @param int $requestedSeconds
 *
 * @return void
 */
function wp_umbrella_errors_arm_backoff($requestedSeconds)
{
    $backoff = get_option(WP_UMBRELLA_ERRORS_BACKOFF_OPTION, []);
    $failures = isset($backoff['failures']) ? (int) $backoff['failures'] + 1 : 1;

    if ($requestedSeconds > 0) {
        $delay = min((int) $requestedSeconds, WP_UMBRELLA_ERRORS_BACKOFF_MAX_SECONDS);
    } else {
        $delay = min(
            WP_UMBRELLA_ERRORS_BACKOFF_BASE_SECONDS * pow(2, min($failures - 1, 10)),
            WP_UMBRELLA_ERRORS_BACKOFF_MAX_SECONDS
        );
    }

    update_option(WP_UMBRELLA_ERRORS_BACKOFF_OPTION, [
        'failures' => $failures,
        'until' => time() + (int) $delay,
    ], false);
}

/**
 * @return void
 */
function wp_umbrella_errors_clear_backoff()
{
    delete_option(WP_UMBRELLA_ERRORS_BACKOFF_OPTION);
}

/**
 * Reads the delay the API asked for, in seconds. Returns 0 when the response
 * carries no usable Retry-After.
 *
 * @param array $response
 *
 * @return int
 */
function wp_umbrella_errors_retry_after($response)
{
    $header = wp_remote_retrieve_header($response, 'retry-after');

    if (is_array($header)) {
        $header = reset($header);
    }

    if (!is_string($header) && !is_int($header)) {
        return 0;
    }

    $header = trim((string) $header);

    if ('' === $header) {
        return 0;
    }

    if (ctype_digit($header)) {
        return (int) $header;
    }

    $timestamp = strtotime($header);

    return false === $timestamp ? 0 : max(0, $timestamp - time());
}

/**
 * @param array $errors
 *
 * @return array|\WP_Error
 */
function wp_umbrella_post_errors(array $errors)
{
    return wp_umbrella_handle_outbound_response(wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/errors', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => sprintf('Bearer %s', wp_umbrella_get_outbound_bearer()),
            'X-Project' => site_url(),
            'X-Project-Id' => wp_umbrella_get_project_id(),
            'X-Secret-Token' => wp_umbrella_get_secret_token(),
        ],
        'body' => wp_json_encode(['errors' => $errors]),
        'timeout' => 15,
    ]));
}

/**
 * Builds the API payload for one buffered error, or null when the error
 * cannot be attributed to a plugin or a theme.
 *
 * @param mixed $error
 *
 * @return array|null
 */
function wp_umbrella_build_error_payload($error)
{
    if (!is_array($error) || !isset($error['file'], $error['code'], $error['line'], $error['message'])) {
        return null;
    }

    if (!is_string($error['file'])) {
        return null;
    }

    $payload = wp_umbrella_get_data_current_from_current_file($error['file']);

    if (null === $payload) {
        return null;
    }

    $payload['file'] = $error['file'];
    $payload['line'] = $error['line'];
    $payload['code'] = $error['code'];
    $payload['message'] = $error['message'];
    $payload['date_error'] = isset($error['date_error']) && is_string($error['date_error'])
        ? $error['date_error']
        : gmdate('Y-m-d\TH:i:s\Z');
    $payload['backtrace'] = isset($error['backtrace']) && is_string($error['backtrace'])
        ? $error['backtrace']
        : '';
    $payload['php_version'] = phpversion();
    $payload['wordpress_version'] = get_bloginfo('version');

    return $payload;
}
