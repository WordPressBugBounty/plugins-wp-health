<?php
namespace WPUmbrella\Services\Provider;

use WPUmbrella\Core\Schemas\UserSchema;
use Morphism\Morphism;

class Users
{
    const NAME_SERVICE = 'UsersProvider';

    protected $schema;

    public function __construct()
    {
        $this->createDefaultSchema();
    }

    protected function createDefaultSchema()
    {
        $this->schema = new UserSchema();
    }

    public function get($requestArgs = [])
    {
        wp_umbrella_get_service('WordPressContext')->requirePluggable();

        $argKeys = [
            'include',
            'exclude',
            'search',
            'orderby',
            'order',
            'offset',
            'number',
            'role'
        ];

        $args = [];
        foreach ($argKeys as $argKey) {
            if (isset($requestArgs[$argKey])) {
                $args[$argKey] = $requestArgs[$argKey];
            }
        }

        $data = (array) array_map(function ($item) {
            $value['id'] = $item->ID;
            $value['data'] = (array) $item->data;
            $value['caps'] = (array) $item->caps;
            $value['roles'] = (array) $item->roles;
            return (array) $value;
        }, get_users($args));

        if (empty($data)) {
            return $data;
        }

        $schema = $this->schema->getSchema();

        Morphism::setMapper('WPUmbrella\DataTransferObject\User', $schema);

        return Morphism::map('WPUmbrella\DataTransferObject\User', $data);
    }

    public function getUserAdminCanBy($capabilities = ['update_plugins'])
    {
        wp_umbrella_get_service('WordPressContext')->requirePluggable();

        if (is_multisite()) {
            $supers = get_super_admins();
            if (!empty($supers)) {
                $user = get_user_by('login', $supers[0]);

                return $user;
            }
        }

        global $wpdb;

        $admins = $wpdb->get_results(
            "SELECT *
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
			WHERE um.meta_key = '{$wpdb->prefix}capabilities'
			AND um.meta_value LIKE '%administrator%'
			LIMIT 1
			"
        );

        return isset($admins[0]) ? $admins[0] : false;
    }
}
