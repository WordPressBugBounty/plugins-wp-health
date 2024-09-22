<?php
namespace WPUmbrella\Core\Schemas;

use WPUmbrella\Core\Models\SchemaInterface;

class UserSchema implements SchemaInterface
{
	public function getSchema($options = []){
		return [
            'id' => 'id',
            'user_login' => 'data.user_login',
            'user_nicename' => 'data.user_nicename',
            'user_email' => 'data.user_email',
            'user_url' => 'data.user_url',
            'user_registered' => 'data.user_registered',
            'user_activation_key' => 'data.user_activation_key',
            'user_status' => 'data.user_status',
            'display_name' => 'data.display_name',
            'caps' => 'caps',
            'roles' => 'roles',

        ];
	}

}
