<?php
namespace WPUmbrella\Core;

use WPUmbrella\Core\Container\ContainerSkypress;
use WPUmbrella\Core\Container\ManageContainer;
use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Core\Hooks\ExecuteHooksFrontend;
use WPUmbrella\Core\Controllers;
use WPUmbrella\Core\UmbrellaRequest;
use WPUmbrella\Services\HostResolver;
use WPUmbrella\Helpers\Host;
use WPUmbrella\Helpers\Controller as ControllerHelper;

abstract class Kernel
{
    protected static $container = null;

    protected static $universalProcess = null;

    protected static $apiLoad = false;

    protected static $data = ['slug' => null, 'main_file' => null, 'file' => null, 'root' => ''];

    public static function setSettings($data)
    {
        self::$data = array_merge(self::$data, $data);
    }

    public static function setContainer(ManageContainer $container)
    {
        self::$container = self::getDefaultContainer();
    }

    protected static function getDefaultContainer()
    {
        return new ContainerSkypress();
    }

    public static function getContainer()
    {
        if (null === self::$container) {
            self::$container = self::getDefaultContainer();
        }

        return self::$container;
    }

    public static function handleHooksPlugin()
    {
        require_once WP_UMBRELLA_DIR . '/src/Async/ActionSchedulerSendErrors.php';

        switch (current_filter()) {
            case 'plugins_loaded':
                if (self::$universalProcess && self::$universalProcess['load']) {
                    self::buildActionScheduler();
                    wp_umbrella_get_service('RequestSettings')->buildRequestSettings();
                }

                load_plugin_textdomain('wp-health', false, WP_UMBRELLA_LANGUAGES);

                foreach (self::getContainer()->getActions() as $key => $class) {
                    if (!class_exists($class)) {
                        continue;
                    }

                    $class = new $class();

                    switch (true) {
                        case $class instanceof ExecuteHooksBackend:
                            if (is_admin()) {
                                $class->hooks();
                            }
                            break;

                        case $class instanceof ExecuteHooksFrontend:
                            if (!is_admin()) {
                                $class->hooks();
                            }
                            break;

                        case $class instanceof ExecuteHooks:
                            $class->hooks();
                            break;
                    }
                }
                break;
            case 'activate_' . self::$data['slug'] . '/' . self::$data['slug'] . '.php':
                foreach (self::getContainer()->getActions() as $key => $class) {
                    if (!class_exists($class)) {
                        continue;
                    }

                    $class = new $class();
                    if ($class instanceof ActivationHook) {
                        $class->activate();
                    }
                }
                break;
            case 'deactivate_' . self::$data['slug'] . '/' . self::$data['slug'] . '.php':
                foreach (self::getContainer()->getActions() as $key => $class) {
                    if (!class_exists($class)) {
                        continue;
                    }

                    $class = new $class();
                    if ($class instanceof DeactivationHook) {
                        $class->deactivate();
                    }
                }
                break;
        }
    }

    /**
     * @static
     *
     * @param string $path
     * @param string $type
     * @param string $namespace
     *
     * @return void
     */
    public static function buildClasses($path, $type, $namespace = '')
    {
        try {
            $files = array_diff(scandir($path), ['..', '.']);
            foreach ($files as $filename) {
                $pathCheck = $path . '/' . $filename;

                if (is_dir($pathCheck)) {
                    self::buildClasses($pathCheck, $type, $namespace . $filename . '\\');
                    continue;
                }

                $pathinfo = pathinfo($filename);
                if (isset($pathinfo['extension']) && 'php' !== $pathinfo['extension']) {
                    continue;
                }

                $data = '\\WPUmbrella\\' . $namespace . str_replace('.php', '', $filename);

                switch ($type) {
                    case 'services':
                        self::getContainer()->setService($data);
                        break;
                    case 'actions':
                        self::getContainer()->setAction($data);
                        break;
                }
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * Build Container.
     */
    public static function buildContainer()
    {
        self::buildClasses(self::$data['root'] . '/src/Services', 'services', 'Services\\');
        self::buildClasses(self::$data['root'] . '/src/Actions', 'actions', 'Actions\\');
    }

    /**
     * At this stage, it is necessary to check the API key
     */
    public static function canExecute(UmbrellaRequest $request)
    {
        $requestVersion = $request->getRequestVersion();

        $action = $request->getAction();

        $token = $request->getToken();
        $response = wp_umbrella_get_service('ApiWordPressPermission')->isTokenAuthorized($token, [
            'with_cache' => $action !== '/v1/validation-application-token'
        ]);

        if (!isset($response['authorized']) || !$response['authorized']) {
            return false;
        }

        return true;
    }

    public static function maybeMagicLogin()
    {
        if (isset(self::$universalProcess['action']) && self::$universalProcess['action'] === '/v1/login') {
            $host = wp_umbrella_get_service('HostResolver')->getCurrentHost();

            // o2switch does not need to handle this particular case.
            if (in_array($host, [
                Host::O2SW
            ], true)) {
                return;
            }

            $pluginsNoWork = [
                WP_PLUGIN_DIR . '/secupress/secupress.php',
                WP_PLUGIN_DIR . '/secupress-pro/secupress-pro.php'
            ];

            //  This problem occurs with SecuPress and a bad configuration with EDD
            foreach ($pluginsNoWork as $plugin) {
                if (file_exists($plugin)) {
                    return;
                }
            }

            $umbrellaRequest = UmbrellaRequest::createFromGlobals();

            $authorize = wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isFullyAuthorized($umbrellaRequest);

            if (!$authorize) {
                return;
            }

            if (!wp_umbrella_get_service('Option')->canOneClickAccess()) {
                return;
            }

            $userId = $umbrellaRequest->getParam('user_id');

            if (!$userId) {
                return;
            }

            if (!function_exists('get_userdata')) {
                include_once ABSPATH . '/wp-includes/pluggable.php';
            }

            $user = get_userdata($userId);

            wp_set_current_user((int) $user->ID, $user->user_login);

            // On Cloudways type installations, and the trigger by API requires this prevention.
            if (!defined('SECURE_AUTH_COOKIE')) {
                wp_cookie_constants();
                wp_umbrella_get_service('RequestSettings')->setCookies($user);
            }

            wp_set_auth_cookie($user->ID);
            wp_redirect(admin_url('index.php'), 302);
            // Required for POST requests
            ?>

            <html>
                <head>
                    <meta http-equiv="refresh" content="0;URL=<?php echo admin_url('index.php'); ?>">
                </head>
                <body>
                    <?php _e('Redirection in progress...', 'wp-health'); ?>☂
                    <script>
                        document.addEventListener("DOMContentLoaded", function(event) {
                            window.location = "<?php echo admin_url('index.php'); ?>";
                        });
                    </script>
                </body>
            </html>
            <?php
            return true;
        }

        return false;
    }

    protected static function buildActionScheduler()
    {
        if (function_exists('action_scheduler_initialize_3_dot_3_dot_0')) {
            action_scheduler_initialize_3_dot_3_dot_0();
            \ActionScheduler_Versions::initialize_latest_version();
        }

        if (!class_exists('ActionScheduler_Store')) {
            require_once WP_UMBRELLA_DIR . '/thirds/action-scheduler/deprecated/ActionScheduler_Store_Deprecated.php';
            require_once WP_UMBRELLA_DIR . '/thirds/action-scheduler/classes/abstracts/ActionScheduler_Store.php';
            require_once WP_UMBRELLA_DIR . '/thirds/action-scheduler/classes/abstracts/ActionScheduler_Abstract_Schema.php';
            require_once WP_UMBRELLA_DIR . '/thirds/action-scheduler/classes/schema/ActionScheduler_StoreSchema.php';
        }

        $tables = [
            \ActionScheduler_StoreSchema::ACTIONS_TABLE,
            \ActionScheduler_StoreSchema::CLAIMS_TABLE,
            \ActionScheduler_StoreSchema::GROUPS_TABLE,
        ];

        global $wpdb;
        foreach ($tables as $table) {
            $wpdb->tables[] = $table;
            $name = $GLOBALS['wpdb']->prefix . $table;
            $wpdb->$table = $name;
        }
    }

    public static function setPluginPriority()
    {
        $pluginBasename = 'wp-health/wp-health.php';
        $activePlugins = get_option('active_plugins');

        if (!is_array($activePlugins) || reset($activePlugins) === $pluginBasename) {
            return;
        }

        $key = array_search($pluginBasename, $activePlugins);

        if ($key === false || $key === null) {
            return;
        }

        unset($activePlugins[$key]);
        array_unshift($activePlugins, $pluginBasename);
        update_option('active_plugins', array_values($activePlugins));
    }

    /**
     * Hook third parties only WP Umbrella is loaded
     */
    protected static function hookThirdParties()
    {
        do_action('wp_umbrella_kernel_hook_third_parties');

        // Compatibility with the plugin "ASE"
        add_filter('option_admin_site_enhancements', function ($data) {
            $data['disable_all_updates'] = false;
            return $data;
        });

        // Disable QM
        add_filter('qm/dispatch/html', '__return_false');
        add_filter('qm/dispatch/ajax', '__return_false');
        add_filter('qm/dispatch/redirect', '__return_false');
        add_filter('qm/dispatch/rest', '__return_false');
        add_filter('qm/dispatch/wp_die', '__return_false');
    }

    public static function pluginLoadedWithSetupAdmin()
    {
        add_action('plugins_loaded', function () {
            $hostname = gethostname();

            $hostNameIncompatibleWithSetupAdmin = apply_filters('wp_umbrella_host_name_incompatible_with_setup_admin', [
                'myukcloud.com'
            ], $hostname);

            foreach ($hostNameIncompatibleWithSetupAdmin as $hostName) {
                if (strpos($hostname, $hostName) !== false) {
                    return;
                }
            }

            wp_umbrella_get_service('RequestSettings')->setupAdmin();
        }, 1);
    }

    public static function execute($data)
    {
        if (!class_exists('ActionScheduler')) {
            require_once WP_UMBRELLA_DIR . '/thirds/action-scheduler/action-scheduler.php';
        }

        self::setSettings($data);
        self::buildContainer();

        $request = UmbrellaRequest::createFromGlobals();

        self::$universalProcess = [
            'load' => false,
            'need_delay' => false,
            'hook' => 'wp',
            'priority' => 10,
            'action' => null
        ];

        if ($request->canTryExecuteWPUmbrella()) {
            $controllers = Controllers::getControllers();
            $action = $request->getAction();

            if (
                self::canExecute($request) &&
                array_key_exists($action, $controllers) &&
                $request->getRequestFrom() === ControllerHelper::PHP
            ) {
                do_action('wp_umbrella_request_authorized');

                self::setPluginPriority();
                $item = $controllers[$action];
                self::$universalProcess = [
                    'load' => true,
                    'need_delay' => false,
                    'action' => $action,
                    'hook' => isset($item['hook']) ? $item['hook'] : 'plugins_loaded',
                    'priority' => isset($item['priority']) ? $item['priority'] : 10,
                ];
            } elseif (
                self::canExecute($request) &&
                array_key_exists($action, $controllers) &&
                $request->getRequestFrom() === ControllerHelper::API
            ) {
                self::$apiLoad = true;
            }
        }

        // Universal load by PHP
        if (self::$universalProcess['load']) {
            self::hookThirdParties();

            add_filter('deprecated_function_trigger_error', '__return_false');

            $action = $request->getAction();

            self::maybeMagicLogin();

            wp_umbrella_get_service('RequestSettings')->setupAdminConstants();

            self::pluginLoadedWithSetupAdmin();

            add_action('wp_loaded', function () {
                // Prevent for WooCommerce error
                wp_umbrella_get_service('RequestSettings')->adminLoaded();
            }, 1);

            add_action('wp', function () {
                require_once WP_UMBRELLA_DIR . '/request/umbrella-application.php';
                return;
            }, self::$universalProcess['priority']);
        }

        if (self::$apiLoad) {
            self::hookThirdParties();
            self::pluginLoadedWithSetupAdmin();
        }

        if ($request->getToken() === wp_umbrella_get_api_key()) {
            self::ajaxActions();
        }

        add_action('wp_ajax_wp_umbrella_snapshot_data', [__CLASS__, 'snapshot']);
        add_action('wp_ajax_nopriv_wp_umbrella_snapshot_data', [__CLASS__, 'snapshot']);

        add_action('wp_ajax_wp_umbrella_update_admin_request', [__CLASS__, 'updateAdminRequest']);
        add_action('wp_ajax_nopriv_wp_umbrella_update_admin_request', [__CLASS__, 'updateAdminRequest']);

        add_action('plugins_loaded', [__CLASS__, 'handleHooksPlugin'], 10);
        register_activation_hook($data['file'], [__CLASS__, 'handleHooksPlugin']);
        register_deactivation_hook($data['file'], [__CLASS__, 'handleHooksPlugin']);
    }

    public static function ajaxActions()
    {
        add_action('wp_ajax_' . \WPUmbrella\Controller\Plugin\DataSingle::NONCE_ACTION, [\WPUmbrella\Controller\Plugin\DataSingle::class, 'getPluginDataByAjaxRouting']);
        add_action('wp_ajax_nopriv_' . \WPUmbrella\Controller\Plugin\DataSingle::NONCE_ACTION, [\WPUmbrella\Controller\Plugin\DataSingle::class, 'getPluginDataByAjaxRouting']);
    }

    public static function snapshot()
    {
        if (!isset($_POST['nonce'])) {
            return;
        }

        if (!isset($_POST['api_key'])) {
            return;
        }

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdmin();

        if (!wp_verify_nonce($_POST['nonce'], 'wp_umbrella_snapshot_data')) {
            wp_send_json_error(
                [
                    'code' => 'nonce_failed',
                    'message' => __('Admin request nonce check failed', 'wp-health'),
                ]
            );
        }

        if (!hash_equals(wp_umbrella_get_api_key(), $_POST['api_key'])) {
            return;
        }

        wp_update_plugins([]);
        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        wp_umbrella_get_service('SessionStore')->removeUmbrellaSessions();
        return;
    }

    public static function updateAdminRequest()
    {
        // Make sure required values are set.
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $plugin = isset($_POST['plugin']) ? $_POST['plugin'] : '';
        $safeUpdate = isset($_POST['safe_update']) ? $_POST['safe_update'] : false;

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdmin();

        // Nonce and hash are required.
        if (empty($nonce)) {
            wp_send_json_error(
                [
                    'code' => 'invalid_params',
                    'message' => __('Required parameters are missing', 'wp-health'),
                ]
            );
        }

        // If nonce check failed.
        if (!wp_verify_nonce($nonce, 'wp_umbrella_update_admin_request')) {
            wp_send_json_error(
                [
                    'code' => 'nonce_failed',
                    'message' => __('Admin request nonce check failed', 'wp-health'),
                ]
            );
        }

        if (empty($plugin)) {
            wp_send_json_error(
                [
                    'code' => 'plugin_not_exist',
                    'message' => __('Plugin not exist', 'wp-health'),
                ]
            );
        }

        $plugins = [$plugin];

        try {
            $result = wp_umbrella_get_service('ManagePlugin')->bulkUpdate($plugins, [
                'try_ajax' => false,
                'only_ajax' => false,
                'safe_update' => $safeUpdate
            ]);

            wp_umbrella_get_service('SessionStore')->removeUmbrellaSessions();

            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
