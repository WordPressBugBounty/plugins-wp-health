<?php
namespace WPUmbrella\Services\Provider;

use WPUmbrella\DataTransferObject\User;

class Users
{
    const NAME_SERVICE = 'UsersProvider';

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

        $users = [];
        foreach (get_users($args) as $item) {
            $users[] = $this->hydrate($item);
        }

        return $users;
    }

    /**
     * @param \WP_User $item
     * @return User
     */
    protected function hydrate($item)
    {
        $row = (array) $item->data;

        $user = new User();
        $user->id = $item->ID;
        $user->user_login = $this->readColumn($row, 'user_login');
        $user->user_nicename = $this->readColumn($row, 'user_nicename');
        $user->user_email = $this->readColumn($row, 'user_email');
        $user->user_url = $this->readColumn($row, 'user_url');
        $user->user_registered = $this->readColumn($row, 'user_registered');
        $user->user_status = $this->readColumn($row, 'user_status');
        $user->display_name = $this->readColumn($row, 'display_name');
        $user->caps = (array) $item->caps;
        $user->roles = (array) $item->roles;

        return $user;
    }

    /**
     * A column the users table does not carry reads as false, which is what the
     * API has always received for it.
     *
     * @param array $row
     * @param string $column
     * @return mixed
     */
    protected function readColumn(array $row, $column)
    {
        return array_key_exists($column, $row) ? $row[$column] : false;
    }

    public function getUserAdminCanBy($capabilities = ['update_plugins'])
    {
        wp_umbrella_get_service('WordPressContext')->requirePluggable();

        if (is_multisite()) {
            // A listed super admin login may no longer resolve to a user.
            foreach (get_super_admins() as $login) {
                $user = get_user_by('login', $login);

                if ($user) {
                    return $user;
                }
            }
        }

        global $wpdb;

        // On multisite $wpdb->prefix is the current sub-site's, whose capability
        // rows hold no network administrator. The network prefix is the one that
        // carries them.
        $capabilitiesKey = is_multisite()
            ? $wpdb->base_prefix . 'capabilities'
            : $wpdb->prefix . 'capabilities';

        $admins = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.*
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
			WHERE um.meta_key = %s
			AND um.meta_value LIKE %s
			LIMIT 1
			",
                $capabilitiesKey,
                '%administrator%'
            )
        );

        return isset($admins[0]) ? $admins[0] : false;
    }
}
