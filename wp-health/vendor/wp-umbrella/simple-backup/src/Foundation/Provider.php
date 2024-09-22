<?php

namespace Coderatio\SimpleBackup\Foundation;

use Coderatio\SimpleBackup\Foundation\Mysqldump;

class Provider extends Mysqldump
{
    public static function init($config)
    {
        $config['db_host'] = !isset($config['db_host']) ? 'localhost' : $config['db_host'];
        $config['db_host_sock'] = isset($config['db_host_sock']) ? $config['db_host_sock'] : null;
        $config['include_tables'] = isset($config['include_tables']) ? $config['include_tables'] : [];
        $config['exclude_tables'] = isset($config['exclude_tables']) ? $config['exclude_tables'] : [];
        $config['add-drop-table'] = isset($config['add-drop-table']) ? $config['add-drop-table'] : true;
        $config['insert-ignore'] = isset($config['insert-ignore']) ? $config['insert-ignore'] : true;

        try {

            $hostDsn = "mysql:host={$config['db_host']};dbname={$config['db_name']}";
            if($config['db_host_sock'] !== null) {
                $hostDsn = "mysql:unix_socket={$config['db_host_sock']};dbname={$config['db_name']}";
            }

            return new self(
                $hostDsn,
                (string)($config['db_user']),
                (string)($config['db_password']),
                [
                    'add-drop-table' => $config['add-drop-table'],
                    'include-tables' => $config['include_tables'],
                    'exclude-tables' => $config['exclude_tables'],
                    'insert-ignore' => $config['insert-ignore']
                ]
            );
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }
}
