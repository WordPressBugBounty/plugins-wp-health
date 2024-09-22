<?php

namespace WPUmbrella\Actions\Table;


use WPUmbrella\Core\Hooks\ExecuteHooks;

class CreateCustomTables implements ExecuteHooks {
    public function hooks() {
        add_action('admin_init', [$this, 'init']);
    }

    public function init() {
        if ( ! is_user_logged_in()) {
            return;
        }

        $tables = wp_umbrella_get_service('TableList')->getTables();
        wp_umbrella_get_service('TableManager')->createTablesIfNeeded($tables);
    }
}
