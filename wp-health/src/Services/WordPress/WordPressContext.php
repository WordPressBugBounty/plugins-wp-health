<?php

namespace WPUmbrella\Services\WordPress;


use WP_Rewrite;

class WordPressContext
{
	protected $multisite = false;

	public function __construct(){
		if(!function_exists('is_multisite')){
			return;
		}

		if (is_multisite()) {
			global $blog_id;
            $this->multisite         = $blog_id;
        }
	}

	public function isMultiSite(){
		return $this->multisite;
	}

    public function set($name, $value)
    {
		$GLOBALS[$name] = $value;
    }

    public function get($name)
    {
		return isset($GLOBALS[$name]) ? $GLOBALS[$name] : null;
    }

    /**
     * @param string $constant
     *
     * @return bool
     */
    public function hasConstant($constant)
    {
        return defined($constant);
    }

    public function getConstant($constant)
    {
        if (!$this->hasConstant($constant)) {
            return null;
        }

        return constant($constant);
    }

    public function setConstant($name, $value)
    {
        if ($this->hasConstant($name)) {
            return;
        }

        define($name, $value);
    }

    public function getCurrentUser()
    {
        $this->requirePluggable();
        $this->requireCookieConstants();

        return wp_get_current_user();
    }


    public function requirePluggable()
    {
        require_once $this->getConstant('ABSPATH').$this->getConstant('WPINC').'/pluggable.php';
    }

    public function requireCookieConstants()
    {
        wp_cookie_constants();
    }

    public function requireAdminUserLibrary()
    {
        require_once $this->getConstant('ABSPATH').'wp-admin/includes/user.php';
    }


	public function getTransient($option)
	{
		return get_site_transient($option);

	}

	public function getSitemetaTransient($option)
	{
		/** @var wpdb $wpdb */
		global $wpdb;
		$option = '_site_transient_'.$option;

		$result = $wpdb->get_var($wpdb->prepare("SELECT `meta_value` FROM `{$wpdb->sitemeta}` WHERE meta_key = '%s' AND `site_id` = '%s'", $option, $this->multisite));
		$result = maybe_unserialize($result);

		return $result;
	}

    /**
     * @param WP_User|stdClass $user
     * @param bool             $remember
     * @param string           $secure
     */
    public function setAuthCookie($user, $remember = false, $secure = '')
    {
        $this->requireCookieConstants();

        wp_set_auth_cookie($user->ID, $remember, $secure);
    }

    public function requireWpRewrite()
    {
        if ($this->get('wp_rewrite') instanceof WP_Rewrite) {
            return;
        }

        $this->set('wp_rewrite', new WP_Rewrite());
    }

	public function requireTaxonomies()
    {
        $wpTaxonomies = $this->get('wp_taxonomies');

        if (!empty($wpTaxonomies)) {
            return;
        }

        create_initial_taxonomies();
    }

    public function requirePostTypes()
    {
        $wpPostTypes = $this->get('wp_post_types');

        if (!empty($wpPostTypes)) {
            return;
        }

        create_initial_post_types();
    }

	public function getUserData($userId){
		$this->requirePluggable();
		$this->requireCookieConstants();

		return get_userdata($userId);

	}

	public function getHash($value){
		if(!function_exists('wp_hash')){
			include_once ABSPATH . '/wp-includes/pluggable.php';
		}

		return wp_hash($value);
	}

}
