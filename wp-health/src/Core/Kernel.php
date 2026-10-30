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
use WPUmbrella\Services\Loopback\SignedLoopback;
use WPUmbrella\Services\Loopback\LoopbackToken;
use WPUmbrella\Helpers\Host;
use WPUmbrella\Helpers\Controller as ControllerHelper;
use WPUmbrella\Services\Provider\Compatibility\DiviUpdater;
use WPUmbrella\Services\Provider\Compatibility\PremiumUpdateDetector;
use WPUmbrella\Services\Provider\Compatibility\SeedProdUpdater;
use WPUmbrella\Actions\TwoFactor\ActivityLogEvents as TwoFactorActivityLogEvents;
use WPUmbrella\Services\TwoFactor\TwoFactorPolicy;

abstract class Kernel
{
    const TIER_SIGNATURE = 'signature';

    const TIER_LOGIN_SIGNATURE = 'login_signature';

    const TIER_SECRET_TOKEN = 'secret_token';

    const TIER_API_KEY = 'api_key';

    protected static $container = null;

    protected static $universalProcess = null;

    protected static $apiLoad = false;

    protected static $elevatedAction = null;

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
        require_once WP_UMBRELLA_DIR . '/src/Async/ActionSchedulerSendActivityLog.php';

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

                    if (!self::isInstantiable($class)) {
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

                    if (!self::isInstantiable($class)) {
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

                    if (!self::isInstantiable($class)) {
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

    protected static function isInstantiable($class)
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return false;
        }

        return $reflection->isInstantiable();
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
        return self::getAuthorizationTier($request) !== null;
    }

    public static function canSetupAdmin(UmbrellaRequest $request)
    {
        if (!$request->requestedRoutesMatchAction() || !self::isElevationEntryPoint()) {
            return false;
        }

        return self::canSetupAdminForTier(self::getAuthorizationTier($request), $request->getAction());
    }

    protected static function isElevationEntryPoint()
    {
        foreach (['DOING_AJAX', 'DOING_CRON', 'XMLRPC_REQUEST', 'WP_ADMIN', 'IFRAME_REQUEST', 'WP_INSTALLING'] as $constant) {
            if (defined($constant)) {
                return false;
            }
        }

        $script = isset($_SERVER['SCRIPT_FILENAME']) ? basename((string) $_SERVER['SCRIPT_FILENAME']) : '';
        if (strtolower(substr($script, -4)) !== '.php') {
            return true;
        }

        return in_array($script, ['index.php', 'umbrella-application.php'], true);
    }

    /**
     * @param string|null $route
     * @param string|null $action
     *
     * @return bool
     */
    public static function isDispatchedRouteAllowed($route, $action)
    {
        if (!is_string($route) || !is_string($action) || $action === '') {
            return false;
        }

        return untrailingslashit($route) === UmbrellaRequest::REST_NAMESPACE . $action;
    }

    /**
     * @param mixed $result
     * @param mixed $server
     * @param \WP_REST_Request $restRequest
     *
     * @return mixed
     */
    public static function guardRestDispatch($result, $server, $restRequest)
    {
        if (self::$elevatedAction === null) {
            return $result;
        }

        if (is_object($restRequest) && self::isDispatchedRouteAllowed($restRequest->get_route(), self::$elevatedAction)) {
            return $result;
        }

        self::dropElevatedIdentity();

        return new \WP_Error('not_authorized', 'not_authorized', ['status' => 403]);
    }

    public static function dropElevatedIdentity()
    {
        if (self::$elevatedAction === null) {
            return;
        }

        self::$elevatedAction = null;
        wp_set_current_user(0);
    }

    protected static function guardElevatedRequest($action, $from)
    {
        self::$elevatedAction = $action;

        add_filter('rest_pre_dispatch', [__CLASS__, 'guardRestDispatch'], PHP_INT_MIN, 3);

        if ($from === ControllerHelper::API) {
            add_action('parse_request', [__CLASS__, 'dropElevatedIdentity'], PHP_INT_MAX);
        }
    }

    protected static function canSetupAdminForTier($tier, $action)
    {
        if ($tier === null) {
            return false;
        }

        if ($tier !== self::TIER_API_KEY) {
            return true;
        }

        $controllers = Controllers::getControllers();
        if (!isset($controllers[$action]['methods'])) {
            return false;
        }

        foreach ($controllers[$action]['methods'] as $data) {
            if (isset($data['options']['permission']) && $data['options']['permission'] === ControllerHelper::PERMISSION_ONLY_API_TOKEN) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string|null
     */
    public static function getAuthorizationTier(UmbrellaRequest $request)
    {
        $action = $request->getAction();
        $options = [
            'with_cache' => $action !== '/v1/validation-application-token'
        ];

        $verifier = wp_umbrella_get_service('SignedRequestVerifier');
        if ($verifier->hasSignatureHeaders($request->getHeaders())) {
            $verified = $verifier->verify(
                $request->getHeaders(),
                $request->getMethod(),
                $request->getRequestPath(),
                $request->getRawBody(),
                $request->getRequestQuery(),
                $request->getActionHeader()
            );
            if ($verified) {
                return self::TIER_SIGNATURE;
            }
        }

        if ($request->isLoginRoute() && $request->getParam('x-umb-login-sig')) {
            if ($verifier->verifyLogin(
                $request->getParam('user_id'),
                $request->getParam('x-umb-login-sig'),
                $request->getParam('x-umb-login-ts'),
                $request->getParam('x-umb-login-nonce'),
                $request->getParam('x-umb-login-keyid')
            )) {
                return self::TIER_LOGIN_SIGNATURE;
            }
        }

        if (wp_umbrella_is_signature_only()) {
            return null;
        }

        $bearer = $request->getAuthorizationBearer();
        if ($bearer !== null) {
            $hashedBearer = wp_umbrella_get_service('WordPressContext')->getHash($bearer);
            $response = wp_umbrella_get_service('ApiWordPressPermission')->isSecretTokenAuthorized($hashedBearer, $options);
            if (isset($response['authorized']) && $response['authorized']) {
                return self::TIER_SECRET_TOKEN;
            }
        }

        $token = $request->getToken();
        if (!$token) {
            return null;
        }

        $response = wp_umbrella_get_service('ApiWordPressPermission')->isTokenAuthorized($token, $options);
        if (!isset($response['authorized']) || !$response['authorized']) {
            return null;
        }

        if (wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isFullyAuthorized($request)) {
            return self::TIER_SECRET_TOKEN;
        }

        return self::TIER_API_KEY;
    }

    /**
     * @param \WP_User|false $user
     *
     * @return void
     */
    protected static function recordTwoFactorBypass($user)
    {
        if (!is_object($user) || empty($user->ID)) {
            return;
        }

        if (!(new TwoFactorPolicy())->appliesToUser($user)) {
            return;
        }

        (new TwoFactorActivityLogEvents())->onBypassed($user);
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

            if (!$user || !wp_umbrella_get_service('WordPressContext')->canOneClickLoginAs((int) $user->ID)) {
                return;
            }

            self::recordTwoFactorBypass($user);

            wp_set_current_user((int) $user->ID, $user->user_login);

            // On Cloudways type installations, and the trigger by API requires this prevention.
            if (!defined('SECURE_AUTH_COOKIE')) {
                wp_cookie_constants();
                wp_umbrella_get_service('RequestSettings')->preventWPEngine();
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

            // On multisite the admin lookup can come up empty, and the hosting
            // cookie is needed either way.
            wp_umbrella_get_service('RequestSettings')->preventWPEngine();
        }, 1);
    }

    public static function execute($data)
    {
        require_once WP_UMBRELLA_DIR . '/thirds/action-scheduler/action-scheduler.php';

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

        $tier = null;
        $canSetupAdmin = false;

        if ($request->canTryExecuteWPUmbrella()) {
            $controllers = Controllers::getControllers();
            $action = $request->getAction();
            $tier = self::getAuthorizationTier($request);
            $routesMatchAction = $request->requestedRoutesMatchAction();
            $canSetupAdmin = $routesMatchAction && self::isElevationEntryPoint() && self::canSetupAdminForTier($tier, $action);

            if (
                $tier !== null &&
                $routesMatchAction &&
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
                $tier !== null &&
                $routesMatchAction &&
                array_key_exists($action, $controllers) &&
                $request->getRequestFrom() === ControllerHelper::API
            ) {
                self::$apiLoad = true;
            }
        }

        if ($canSetupAdmin && (self::$universalProcess['load'] || self::$apiLoad)) {
            self::guardElevatedRequest($request->getAction(), $request->getRequestFrom());
        }

        if (self::$universalProcess['load'] || self::$apiLoad) {
            add_action('after_setup_theme', function () {
                (new DiviUpdater())->register();
                (new SeedProdUpdater())->register();
            }, PHP_INT_MAX);
        }

        // Universal load by PHP
        if (self::$universalProcess['load']) {
            self::hookThirdParties();

            add_filter('deprecated_function_trigger_error', '__return_false');

            $action = $request->getAction();

            self::maybeMagicLogin();

            if ($canSetupAdmin) {
                wp_umbrella_get_service('RequestSettings')->setupAdminConstants();

                self::pluginLoadedWithSetupAdmin();

                add_action('wp_loaded', function () {
                    // Prevent for WooCommerce error
                    wp_umbrella_get_service('RequestSettings')->adminLoaded();
                }, 1);
            }

            add_action('wp', function () {
                require_once WP_UMBRELLA_DIR . '/request/umbrella-application.php';
                return;
            }, self::$universalProcess['priority']);
        }

        if (self::$apiLoad) {
            self::hookThirdParties();

            if ($canSetupAdmin) {
                self::pluginLoadedWithSetupAdmin();
            }
        }

        self::ajaxActions();

        add_action('wp_ajax_wp_umbrella_snapshot_data', [__CLASS__, 'snapshot']);
        add_action('wp_ajax_nopriv_wp_umbrella_snapshot_data', [__CLASS__, 'snapshot']);

        add_action('wp_ajax_wp_umbrella_update_admin_request', [__CLASS__, 'updateAdminRequest']);
        add_action('wp_ajax_nopriv_wp_umbrella_update_admin_request', [__CLASS__, 'updateAdminRequest']);

        add_action('init', [__CLASS__, 'maybePrepareSnapshotContext'], PHP_INT_MAX);
        add_action('init', [__CLASS__, 'maybePrepareForceCheckContext'], PHP_INT_MAX);

        add_action('plugins_loaded', [__CLASS__, 'handleHooksPlugin'], 10);
        register_activation_hook($data['file'], [__CLASS__, 'handleHooksPlugin']);
        register_deactivation_hook($data['file'], [__CLASS__, 'handleHooksPlugin']);
    }

    public static function ajaxActions()
    {
        add_action('wp_ajax_' . \WPUmbrella\Controller\Plugin\DataSingle::NONCE_ACTION, [\WPUmbrella\Controller\Plugin\DataSingle::class, 'getPluginDataByAjaxRouting']);
        add_action('wp_ajax_nopriv_' . \WPUmbrella\Controller\Plugin\DataSingle::NONCE_ACTION, [\WPUmbrella\Controller\Plugin\DataSingle::class, 'getPluginDataByAjaxRouting']);
    }

    protected static function snapshotRequestIsSigned()
    {
        $timestamp = isset($_POST['timestamp']) ? $_POST['timestamp'] : '';
        $signature = isset($_POST['signature']) ? $_POST['signature'] : '';

        return wp_umbrella_verify_admin_request([
            'action' => 'wp_umbrella_snapshot_data',
            'timestamp' => (string) $timestamp,
        ], $signature, $timestamp);
    }

    /**
     * @param string $kind
     * @return array|null
     */
    protected static function prepareSignedLoopback($kind)
    {
        try {
            return wp_umbrella_get_service('SignedLoopback')->prepare($kind);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function snapshot()
    {
        if (!self::snapshotRequestIsSigned()) {
            return;
        }

        self::prepareSignedLoopback(SignedLoopback::SNAPSHOT);

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_umbrella_snapshot_data')) {
            return;
        }

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdmin();

        wp_update_plugins([]);
        (new PremiumUpdateDetector())->cacheAdminPluginUpdates();
        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        wp_umbrella_get_service('SessionStore')->removeUmbrellaSessions();
        return;
    }

    /**
     * Premium updaters that hook in on admin_init may check the current user
     * there, so it is set before core fires admin_init.
     */
    public static function maybePrepareSnapshotContext()
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }

        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action !== 'wp_umbrella_snapshot_data') {
            return;
        }

        if (!self::snapshotRequestIsSigned()) {
            return;
        }

        $loopback = self::prepareSignedLoopback(SignedLoopback::SNAPSHOT);

        if (!is_array($loopback) || $loopback['token'] !== LoopbackToken::CONSUMED || !empty($loopback['stripped'])) {
            return;
        }

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdminUser();

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_umbrella_snapshot_data')) {
            wp_set_current_user(0);
        }
    }

    public static function maybePrepareForceCheckContext()
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }

        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action !== 'wp_umbrella_update_admin_request') {
            return;
        }

        if (filter_input(INPUT_GET, 'force-check') !== '1') {
            return;
        }

        if (!self::adminRequestIsSigned()) {
            return;
        }

        self::prepareSignedLoopback(SignedLoopback::UPDATE);

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdminUser();

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_umbrella_update_admin_request')) {
            wp_set_current_user(0);
            return;
        }

        wp_umbrella_get_service('RequestSettings')->preventWPEngine();

        $previousPagenow = isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : null;
        $GLOBALS['pagenow'] = 'update-core.php';

        add_action('admin_init', function () use ($previousPagenow) {
            if ($previousPagenow === null) {
                unset($GLOBALS['pagenow']);
            } else {
                $GLOBALS['pagenow'] = $previousPagenow;
            }
        }, PHP_INT_MAX);

        wp_umbrella_get_service('RequestTrace')->addTrace('force_check_context_prepared', [
            'pagenow' => 'update-core.php',
            'can_update_plugins' => current_user_can('update_plugins') ? 1 : 0,
        ]);

        wp_umbrella_debug_log('admin-ajax fallback: prepared force-check admin context (pagenow=update-core.php) before admin_init');
    }

    protected static function adminRequestPlugin()
    {
        return isset($_POST['plugin']) ? (string) wp_unslash($_POST['plugin']) : '';
    }

    protected static function adminRequestLockToken()
    {
        return isset($_POST['lock_token']) && is_string($_POST['lock_token']) ? (string) wp_unslash($_POST['lock_token']) : '';
    }

    protected static function adminRequestOperation()
    {
        return isset($_POST['operation']) && is_string($_POST['operation']) ? (string) wp_unslash($_POST['operation']) : '';
    }

    protected static function adminRequestIsSigned()
    {
        $timestamp = isset($_POST['timestamp']) ? $_POST['timestamp'] : '';
        $signature = isset($_POST['signature']) ? $_POST['signature'] : '';

        $fields = [
            'action' => 'wp_umbrella_update_admin_request',
            'plugin' => self::adminRequestPlugin(),
            'timestamp' => (string) $timestamp,
        ];

        if (self::adminRequestLockToken() !== '' || self::adminRequestOperation() !== '') {
            $fields['lock_token'] = self::adminRequestLockToken();
            $fields['operation'] = self::adminRequestOperation();
        }

        return wp_umbrella_verify_admin_request($fields, $signature, $timestamp);
    }

    /**
     * @param string $plugin
     * @param mixed $result
     * @return array
     */
    protected static function delegationResult($plugin, $result)
    {
        if (!is_array($result)) {
            return ['status' => 'error', 'code' => 'unknown_error', 'data' => []];
        }

        $data = [];
        if (isset($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $key => $value) {
                if (is_string($key) && strpos($key, $plugin) === 0 && (is_scalar($value) || $value === null)) {
                    $data[$key] = $value;
                }
            }
        }

        return [
            'status' => isset($result['status']) && is_string($result['status']) ? $result['status'] : 'error',
            'code' => isset($result['code']) && is_string($result['code']) ? $result['code'] : 'unknown_error',
            'data' => $data,
        ];
    }

    public static function updateAdminRequest()
    {
        // Make sure required values are set.
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $plugin = self::adminRequestPlugin();

        if (!self::adminRequestIsSigned()) {
            wp_send_json_error(
                [
                    'code' => 'invalid_params',
                    'message' => __('Required parameters are missing', 'wp-health'),
                ]
            );
        }

        self::prepareSignedLoopback(SignedLoopback::UPDATE);

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdminUser();

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

        wp_umbrella_get_service('RequestSettings')->preventWPEngine();

        if (empty($plugin)) {
            wp_send_json_error(
                [
                    'code' => 'plugin_not_exist',
                    'message' => __('Plugin not exist', 'wp-health'),
                ]
            );
        }

        $plugins = [$plugin];

        $lockToken = self::adminRequestLockToken();
        $operation = self::adminRequestOperation();
        $delegated = $lockToken !== '' && $operation !== '';
        $lock = wp_umbrella_get_service('UpdateLock');
        $lockSlug = dirname($plugin);

        if ($delegated) {
            if (!$lock->claimDelegation($lockSlug, $lockToken, $operation)) {
                wp_send_json([
                    'status' => 'in_progress',
                    'code' => 'update_in_progress',
                    'message' => sprintf('An update is already in progress for plugin %s', $lockSlug),
                    'data' => '',
                ]);
            }

            $lock->completeDelegationOnShutdown($lockSlug, $lockToken, $operation);
        }

        wp_umbrella_debug_log(sprintf(
            "admin-ajax fallback for '%s': logged_in=%s can_update_plugins=%s pagenow=%s force_check=%s",
            $plugin,
            is_user_logged_in() ? 'yes' : 'no',
            current_user_can('update_plugins') ? 'yes' : 'no',
            isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '(unset)',
            filter_input(INPUT_GET, 'force-check') ?: '(none)'
        ));

        try {
            $result = wp_umbrella_get_service('ManagePlugin')->bulkUpdate($plugins, [
                'try_ajax' => false,
                'only_ajax' => false,
                // The caller that opened this loopback owns the rollback, so the
                // child must never take a second one.
                'require_backup' => false,
                'skip_lock' => true,
            ]);

            wp_umbrella_debug_log("admin-ajax fallback for '{$plugin}' result: " . (isset($result['code']) ? $result['code'] : 'unknown'));

            if ($delegated) {
                $lock->completeDelegation($lockSlug, $lockToken, $operation, self::delegationResult($plugin, $result));
            }

            if (is_array($result)) {
                $childTrace = wp_umbrella_get_service('RequestTrace')->getTrace();
                if (!empty($childTrace)) {
                    $result['_trace_child'] = $childTrace;
                }
            }

            wp_umbrella_get_service('SessionStore')->removeUmbrellaSessions();

            wp_send_json($result);
        } catch (\Exception $e) {
            if ($delegated) {
                $lock->completeDelegation($lockSlug, $lockToken, $operation, self::delegationResult($plugin, null));
            }

            wp_send_json([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
