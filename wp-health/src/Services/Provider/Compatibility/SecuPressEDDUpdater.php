<?php
namespace WPUmbrella\Services\Provider\Compatibility;

/**
 * Allows plugins to use their own update API.
 *
 * @author Easy Digital Downloads
 * @version 1.6.12
 */
class SecuPressEDDUpdater
{
    private $api_url = '';
    private $api_data = [];
    private $name = '';
    private $slug = '';
    private $version = '';
    private $wp_override = false;
    private $beta = false;
    private $cache_key = '';

    /**
     * Class constructor.
     *
     * @uses plugin_basename()
     * @uses hook()
     *
     * @param string  $_api_url     The URL pointing to the custom API endpoint.
     * @param string  $_plugin_file Path to the plugin file.
     * @param array   $_api_data    Optional data to send with API calls.
     */
    public function __construct($_api_url, $_plugin_file, $_api_data = null)
    {
        global $edd_plugin_data;

        $this->api_url = trailingslashit($_api_url);
        $this->api_data = $_api_data;
        $this->name = plugin_basename($_plugin_file);
        $this->slug = basename($_plugin_file, '.php');
        $this->version = $_api_data['version'];
        $this->wp_override = isset($_api_data['wp_override']) ? (bool) $_api_data['wp_override'] : false;
        $this->beta = !empty($this->api_data['beta']);
        $this->cache_key = md5(serialize($this->slug . $this->api_data['license'] . $this->beta));

        $edd_plugin_data[$this->slug] = $this->api_data;
    }

    /**
     * Check for Updates at the defined API endpoint and modify the update array.
     *
     * This function dives into the update API just when WordPress creates its update array,
     * then adds a custom API call and injects the custom plugin data retrieved from the API.
     * It is reassembled from parts of the native WordPress plugin update code.
     * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
     *
     * @uses api_request()
     *
     * @param array   $_transient_data Update array build by WordPress.
     * @return array Modified update array with custom plugin data.
     */
    public function check_update($_transient_data = null)
    {
        global $pagenow;

        if (!is_object($_transient_data)) {
            $_transient_data = new \stdClass();
        }

        if ('plugins.php' == $pagenow && is_multisite()) {
            return $_transient_data;
        }

        if (!empty($_transient_data->response) && !empty($_transient_data->response[$this->name]) && false === $this->wp_override) {
            return $_transient_data;
        }

        $version_info = $this->get_cached_version_info();

        if (false === $version_info) {
            $version_info = $this->api_request('plugin_latest_version', ['slug' => $this->slug, 'beta' => $this->beta]);

            $this->set_version_info_cache($version_info);
        }

        if (false !== $version_info && is_object($version_info) && isset($version_info->new_version)) {
            if (version_compare($this->version, $version_info->new_version, '<')) {
                $_transient_data->response[$this->name] = $version_info;
            }

            $_transient_data->last_checked = current_time('timestamp');
            $_transient_data->checked[$this->name] = $this->version;
        }

        return $_transient_data;
    }

    /**
     * Updates information on the "View version x.x details" page with custom data.
     *
     * @uses api_request()
     *
     * @param mixed   $_data
     * @param string  $_action
     * @param object  $_args
     * @return object $_data
     */
    public function plugins_api_filter($_data, $_action = '', $_args = null)
    {
        if ($_action != 'plugin_information') {
            return $_data;
        }

        if (!isset($_args->slug) || ($_args->slug != $this->slug)) {
            return $_data;
        }

        $to_send = [
            'slug' => $this->slug,
            'is_ssl' => is_ssl(),
            'fields' => [
                'banners' => [],
                'reviews' => false
            ]
        ];

        $cache_key = 'edd_api_request_' . md5(serialize($this->slug . $this->api_data['license'] . $this->beta));

        // Get the transient where we store the api request for this plugin for 24 hours
        $edd_api_request_transient = $this->get_cached_version_info($cache_key);

        //If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
        if (empty($edd_api_request_transient)) {
            $api_response = $this->api_request('plugin_information', $to_send);

            // Expires in 3 hours
            $this->set_version_info_cache($api_response, $cache_key);

            if (false !== $api_response) {
                $_data = $api_response;
            }
        } else {
            $_data = $edd_api_request_transient;
        }

        // Convert sections into an associative array, since we're getting an object, but Core expects an array.
        if (isset($_data->sections) && !is_array($_data->sections)) {
            $new_sections = [];
            foreach ($_data->sections as $key => $value) {
                $new_sections[$key] = $value;
            }

            $_data->sections = $new_sections;
        }

        // Convert banners into an associative array, since we're getting an object, but Core expects an array.
        if (isset($_data->banners) && !is_array($_data->banners)) {
            $new_banners = [];
            foreach ($_data->banners as $key => $value) {
                $new_banners[$key] = $value;
            }

            $_data->banners = $new_banners;
        }

        return $_data;
    }

    /**
     * Disable SSL verification in order to prevent download update failures
     *
     * @param array   $args
     * @param string  $url
     * @return object $array
     */
    public function http_request_args($args, $url)
    {
        // If it is an https request and we are performing a package download, disable ssl verification
        if (strpos($url, 'https://') !== false && strpos($url, 'edd_action=package_download')) {
            $args['sslverify'] = false;
        }
        return $args;
    }

    /**
     * Calls the API and, if successfull, returns the object delivered by the API.
     *
     * @uses get_bloginfo()
     * @uses wp_remote_post()
     * @uses is_wp_error()
     *
     * @param string  $_action The requested action.
     * @param array   $_data   Parameters for the API action.
     * @return false|object
     */
    private function api_request($_action, $_data)
    {
        global $wp_version;

        $data = array_merge($this->api_data, $_data);

        if ($data['slug'] != $this->slug) {
            return;
        }

        if ($this->api_url == trailingslashit(home_url())) {
            return false; // Don't allow a plugin to ping itself
        }

        $api_params = [
            'edd_action' => 'get_version',
            'license' => !empty($data['license']) ? $data['license'] : '',
            'item_name' => isset($data['item_name']) ? $data['item_name'] : false,
            'item_id' => isset($data['item_id']) ? $data['item_id'] : false,
            'version' => isset($data['version']) ? $data['version'] : false,
            'slug' => $data['slug'],
            'author' => $data['author'],
            'url' => home_url(),
            'beta' => !empty($data['beta']),
        ];

        $request = wp_remote_post($this->api_url, ['timeout' => 15, 'sslverify' => false, 'body' => $api_params]);

        if (!is_wp_error($request)) {
            $request = json_decode(wp_remote_retrieve_body($request));
        }

        if ($request && isset($request->sections)) {
            $request->sections = maybe_unserialize($request->sections);
        } else {
            $request = false;
        }

        if ($request && isset($request->banners)) {
            $request->banners = maybe_unserialize($request->banners);
        }

        if (!empty($request->sections)) {
            foreach ($request->sections as $key => $section) {
                $request->$key = (array) $section;
            }
        }

        return $request;
    }

    public function show_changelog()
    {
        global $edd_plugin_data;

        if (empty($_REQUEST['edd_sl_action']) || 'view_plugin_changelog' != $_REQUEST['edd_sl_action']) {
            return;
        }

        if (empty($_REQUEST['plugin'])) {
            return;
        }

        if (empty($_REQUEST['slug'])) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have permission to install plugin updates', 'secupress'), __('Error', 'secupress'), ['response' => 403]);
        }

        $data = $edd_plugin_data[$_REQUEST['slug']];
        $beta = !empty($data['beta']) ? true : false;
        $cache_key = md5('edd_plugin_' . sanitize_key($_REQUEST['plugin']) . '_' . $beta . '_version_info');
        $version_info = $this->get_cached_version_info($cache_key);

        if (false === $version_info) {
            $api_params = [
                'edd_action' => 'get_version',
                'item_name' => isset($data['item_name']) ? $data['item_name'] : false,
                'item_id' => isset($data['item_id']) ? $data['item_id'] : false,
                'slug' => $_REQUEST['slug'],
                'author' => $data['author'],
                'url' => home_url(),
                'beta' => !empty($data['beta'])
            ];

            $request = wp_remote_post($this->api_url, ['timeout' => 15, 'sslverify' => false, 'body' => $api_params]);

            if (!is_wp_error($request)) {
                $version_info = json_decode(wp_remote_retrieve_body($request));
            }

            if (!empty($version_info) && isset($version_info->sections)) {
                $version_info->sections = maybe_unserialize($version_info->sections);
            } else {
                $version_info = false;
            }

            if (!empty($version_info)) {
                foreach ($version_info->sections as $key => $section) {
                    $version_info->$key = (array) $section;
                }
            }

            $this->set_version_info_cache($version_info, $cache_key);
        }

        if (!empty($version_info) && isset($version_info->sections['changelog'])) {
            echo '<div style="background:#fff;padding:10px;">' . $version_info->sections['changelog'] . '</div>';
        }

        exit;
    }

    public function get_cached_version_info($cache_key = '')
    {
        if (empty($cache_key)) {
            $cache_key = $this->cache_key;
        }

        $cache = get_option($cache_key);

        if (empty($cache['timeout']) || current_time('timestamp') > $cache['timeout']) {
            return false; // Cache is expired
        }

        return json_decode($cache['value']);
    }

    public function set_version_info_cache($value = '', $cache_key = '')
    {
        if (empty($cache_key)) {
            $cache_key = $this->cache_key;
        }

        $data = [
            'timeout' => strtotime('+3 hours', current_time('timestamp')),
            'value' => json_encode($value)
        ];

        update_option($cache_key, $data);
    }
}
