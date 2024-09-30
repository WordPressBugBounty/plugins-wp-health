<?php

if(!class_exists('UmbrellaMySQLConnection', false)):
    class UmbrellaMySQLConnection implements UmbrellaConnectionInterface
    {
        protected $connection;

        protected $configuration;

        public function getConfiguration()
        {
            return $this->configuration;
        }

        /**
         * @param UmbrellaDatabaseConfiguration $conf
         *
         * @throws Exception
         */
        public function __construct(UmbrellaDatabaseConfiguration $configuration)
        {
            if (!extension_loaded('mysql')) {
                throw new UmbrellaException('Mysql extension is not loaded.', 'mysql_disabled');
            }

            $this->configuration = $configuration;

            $flag = 0;
            if ($this->configuration->useSSL) {
                $flag = MYSQL_CLIENT_SSL;
            }

            $this->connection = @mysql_connect($this->configuration->host, $this->configuration->user, $this->configuration->password, false, $flag);
            if (!is_resource($this->connection)) {
                // Attempt to recover from "[2002] No such file or directory" error.
                $errno = mysql_errno();
                if ($errno !== 2002 || strtolower($this->configuration->getHostname()) !== 'localhost' || !is_resource($this->connection = @mysql_connect('127.0.0.1', $this->configuration->user, $this->configuration->password, false, $flag))) {
                    throw new UmbrellaException(mysql_error(), 'db_connect_error_mysql', (string)$errno);
                }
            }
            if (mysql_select_db($this->configuration->name, $this->connection) === false) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_connect_error_mysql', (string)mysql_errno($this->connection));
            }
            if (!@mysql_set_charset(cloner_db_charset($this), $this->connection)) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_connect_error_mysql', (string)mysql_errno($this->connection));
            }
        }

        public function query($query, array $parameters = [], $unbuffered = false)
        {
            $query = UmbrellaDatabaseFunction::bindQueryParams($this, $query, $parameters);

            if ($unbuffered) {
                $result = mysql_unbuffered_query($query, $this->connection);
            } else {
                $result = mysql_query($query, $this->connection);
            }

            if ($result === false) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_query_error', (string)mysql_errno($this->connection));
            } elseif ($result === true) {
                // This is one of INSERT, UPDATE, DELETE, DROP statements.
                return new ClonerMySQLStmt($this->connection, null);
            } else {
                // This is one of SELECT, SHOW, DESCRIBE, EXPLAIN statements.
                return new ClonerMySQLStmt($this->connection, $result);
            }
        }

        public function execute($query)
        {
            $this->query($query);
        }

        public function escape($value)
        {
            return $value === null ? 'null' : "'" . mysql_real_escape_string($value, $this->connection) . "'";
        }

        public function close()
        {
            if (empty($this->connection)) {
                return;
            }
            mysql_close($this->connection);
            $this->connection = null;
        }
    }

endif;
