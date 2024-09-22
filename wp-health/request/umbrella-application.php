<?php

use WPUmbrella\Core\Kernel;
use WPUmbrella\Core\Controllers;
use WPUmbrella\Core\UmbrellaRequest;
use WPUmbrella\Models\Backup\BackupProcessedData;
use WPUmbrella\Helpers\Controller;
use WPUmbrella\Helpers\Directory;
use WPUmbrella\Core\Exceptions\BackupNotCreated;



function wp_umbrella_response($data, $status = 200)
{
    header('Cache-Control: no-cache');
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

if (!$method) {
    wp_umbrella_response([
        'code' => 'not_authorized'
    ]);
    return;
}

require_once __DIR__ . '/../wp-umbrella-request-functions.php';

function wp_umbrella_prevent_not_active()
{
    if (is_plugin_active('wp-health/wp-health.php')) {
        return;
    }

    wp_umbrella_response([
        'code' => 'not_authorized'
    ]);
    return;
}

function wp_umbrella_load_wp() {

	while ( ! is_file( 'wp-load.php' ) ) {
		if ( is_dir( '..' ) && getcwd() != '/' ) {
			chdir( '..' );
		} else {
			break;
		}
	}


	if(is_file( 'wp-load.php' )){
		require_once( 'wp-load.php' );
		return;
	}


	// Need to do this here, because some host can't load wp-load.php
	$maxDepth = 5;
	$baseDir = __DIR__;
	while($maxDepth > 0){
		if(is_file( $baseDir . '/wp-config.php')){
			require_once( $baseDir . '/wp-config.php' );
			return;
		}

		$baseDir .= '/..';
		$maxDepth--;
	}

	die('Unable to load WordPress');
}


if (!defined('ABSPATH')) {
    wp_umbrella_load_wp();
}

if (!function_exists('is_plugin_active') && defined('ABSPATH')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}


try {
    require_once __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../wp-umbrella-functions.php';

    wp_umbrella_init_defined();

    Kernel::setSettings([
        'file' => __DIR__ . '/../wp-health.php',
        'slug' => 'wp-health',
        'main_file' => 'wp-health',
        'root' => __DIR__ . '/../',
    ]);
    Kernel::buildContainer();

	if (!defined('WP_UMBRELLA_IS_ADMIN')) {
		wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
		wp_umbrella_get_service('RequestSettings')->setupAdmin();
	}

} catch (\Exception $e) {
    wp_umbrella_response([
        'code' => 'error'
    ]);
    return;
}

$request = UmbrellaRequest::createFromGlobals();

$controllers = Controllers::getControllers();
$isAlreadyExecuted = false;
$action = $request->getAction();

foreach ($controllers as $key => $item) {
    if (!isset($item['route']) || empty($item['route'])) {
        continue;
    }

    foreach ($item['methods'] as $keyMethod => $data) {
        $route = $item['route'];
        $methodKernel = $data['method'];

        if ($methodKernel !== $method) {
            continue;
        }

        if ($action !== $key) {
            continue;
        }

        if ($isAlreadyExecuted) {
            continue;
        }

        $isAlreadyExecuted = true;

        $options = isset($data['options']) ? $data['options'] : [];
        $options['from'] = $request->getRequestFrom();
        $options['route'] = $route;
        $options['method'] = $method;
		$options['version'] = isset($data['version']) ? $data['version'] : 'v1';

        $controller = new $data['class']($options);

        $controller->execute();
		break;
    }
}

if(!$isAlreadyExecuted){
	wp_umbrella_response([
		'code' => 'not_authorized'
	]);
	return;
}
