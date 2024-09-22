<?php

namespace Coderatio\SimpleBackup\Foundation;

use mysqli;
use SebastianBergmann\Timer\RuntimeException;

final class Database
{
    protected $connection;

    public static function prepare($config = [])
    {
        $self = new self();

        $config['db_host'] = !isset($config['db_host']) ? 'localhost' : $config['db_host'];

        if(!isset($config['db_host_sock']) || $config['db_host_sock'] === null) {
            $self->connection = new mysqli(
                $config['db_host'],
                $config['db_user'],
                $config['db_password'],
                $config['db_name']
            );
        } else {
            $self->connection = new mysqli(
                $config['db_host'],
                $config['db_user'],
                $config['db_password'],
                $config['db_name'],
                null,
                $config['db_host_sock']
            );
        }

        if ($self->connection->connect_error) {
            throw new \RuntimeException('Failed to connect to database: ' . $self->connection->connect_error);
        }

        return $self->connection;
    }
}
