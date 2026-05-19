<?php
namespace WPUmbrella\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Option
{
    /**
     * @var array
     */
    protected $optionsDefault = [
        'api_key' => '',
        'allowed' => false,
    ];

    protected $secureKeys = ['secret_token', 'api_key', 'request_token'];

    /**
     * Get options default.
     *
     * @return array
     */
    public function getOptionsDefault()
    {
        return $this->optionsDefault;
    }

	protected function getOptionsWithoutCache(){

		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}options WHERE option_name = 'wp-health'";
		$options = $wpdb->get_row($sql);

		if(!$options){
			return [];
		}

		return maybe_unserialize($options->option_value);
	}

	public function getApiKeyWithoutCache(){
		$options = $this->getOptionsWithoutCache();

		if(!isset($options['api_key'])){
			return null;
		}

		return $options['api_key'];
	}

	public function getSecretTokenWithoutCache(){
		$options = $this->getOptionsWithoutCache();

		if(!isset($options['secret_token'])){
			return null;
		}

		return $options['secret_token'];
	}

	public function getRequestTokenWithoutCache(){
		$options = $this->getOptionsWithoutCache();

		if(!isset($options['request_token'])){
			return null;
		}

		return $options['request_token'];
	}


    /**
     * @return array
     */
    public function getOptions($params = [])
    {
		$secure = $params['secure'] ?? true;

        $options = wp_parse_args(get_option(WP_UMBRELLA_SLUG), $this->getOptionsDefault());

		if($secure) {
			foreach ($this->secureKeys as $secureKey) {
				if (isset($options[$secureKey])) {
					unset($options[$secureKey]);
				}
			}
		}
        return $options;
    }


    /**
     * @param string $name Key name option
     *
     * @return array
     */
    public function getOption($name, $secure = true)
    {
		if ($secure && in_array($name, $this->secureKeys, true)) {
			return null;
		}

        $options = $this->getOptions([
			'secure' => $secure,
		]);

        if (!array_key_exists($name, $options)) {
            return null;
        }

        return apply_filters('wp_umbrella_' . $name . '_option', $options[$name]);
    }

	public function canOneClickAccess(){
		$disallowOneClickAccess = get_option('wp_umbrella_disallow_one_click_access');

		if(!$disallowOneClickAccess || empty($disallowOneClickAccess)){
			return true;
		}

		return false;
	}

    /**
     * @param array $options
     *
     * @return $this
     */
    public function setOptions($options)
    {
        update_option(WP_UMBRELLA_SLUG, $options);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setOptionByKey($key, $value)
    {
        $options = $this->getOptions();
        $options[$key] = $value;
        $this->setOptions($options);

        return $this;
    }
}
