<?php
namespace WPUmbrella\DataTransferObject;

if (!defined('ABSPATH')) {
    exit;
}

#[AllowDynamicProperties]
class User
{
    public $id;
    public $user_login;
    public $user_nicename;
    public $user_email;
    public $user_url;
    public $user_registered;
    public $user_activation_key;
    public $user_status;
    public $display_name;
    public $caps;
    public $roles;
}
