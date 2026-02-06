<?php

if (!class_exists('UmbrellaPDOConnection', false)):
    class UmbrellaPDOConnection implements UmbrellaConnectionInterface
    {
        protected $connection;
        protected $unbuffered = false;

        public function getConfiguration()
        {
            return $this->configuration;
        }

        /**
         * @param bool $attEmulatePrepares
         */
        public function setAttEmulatePrepares($attEmulatePrepares)
        {
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $attEmulatePrepares);
        }

        /**
         * @param UmbrellaDatabaseConfiguration $configuration
         */
        public function __construct(UmbrellaDatabaseConfiguration $configuration)
        {
            $this->configuration = $configuration;
            $this->connect();
        }

        /**
         * Establish the PDO connection
         *
         * @param bool $throwOnError If true, throws exception on failure. If false, returns boolean.
         * @return bool True if connection successful
         * @throws UmbrellaException If connection fails and $throwOnError is true
         */
        protected function connect($throwOnError = true)
        {
            $configuration = $this->configuration;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            if ($configuration->useSSL) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = true;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                $this->connection = new PDO(self::getDsn($configuration), $configuration->user, $configuration->password, $options);
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 2002 && strtolower($configuration->getHostname()) === 'localhost') {
                    try {
                        $fallbackConfig = clone $configuration;
                        $fallbackConfig->host = '127.0.0.1';
                        $this->connection = new PDO(self::getDsn($fallbackConfig), $fallbackConfig->user, $fallbackConfig->password, $options);
                    } catch (PDOException $e2) {
                        if ($throwOnError) {
                            throw new UmbrellaException($e->getMessage(), 'db_connect_error_pdo', (string)$e2->getCode());
                        }
                        return false;
                    }
                } else {
                    if ($throwOnError) {
                        throw new UmbrellaException($e->getMessage(), 'db_connect_error_pdo', (string)$e->getCode());
                    }
                    return false;
                }
            }

            // ATTR_EMULATE_PREPARES is not necessary for newer mysql versions
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, version_compare($this->connection->getAttribute(PDO::ATTR_SERVER_VERSION), '5.1.17', '<'));
            $this->connection->exec(sprintf('SET NAMES %s', UmbrellaDatabaseFunction::getDatabaseCharset($this)));
            $this->unbuffered = false;

            return true;
        }

        public function query($query, array $parameters = [], $unbuffered = false)
        {
            if ($this->unbuffered !== $unbuffered) {
                $this->unbuffered = $unbuffered;
                $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !$unbuffered);
            }

            try {
                $statement = $this->connection->prepare($query);
                $statement->execute($parameters);
                return new UmbrellaPDOStatement($statement);
            } catch (PDOException $e) {
                $internalErrorCode = isset($e->errorInfo[1]) ? (string)$e->errorInfo[1] : '';
                throw new UmbrellaException($e->getMessage(), 'db_query_error', $internalErrorCode);
            }
        }

        public function execute($query)
        {
            try {
                $this->connection->exec($query);
            } catch (PDOException $e) {
                $internalErrorCode = isset($e->errorInfo[1]) ? (string)$e->errorInfo[1] : '';
                throw new UmbrellaException($e->getMessage(), 'db_query_error', $internalErrorCode);
            }
        }

        public function escape($value)
        {
            return $value === null ? 'null' : $this->connection->quote($value);
        }

        public function close()
        {
            $this->connection = null;
        }

        public function ping()
        {
            if ($this->connection === null) {
                return false;
            }

            try {
                $this->connection->query('SELECT 1');
                return true;
            } catch (PDOException $e) {
                return false;
            }
        }

        public function reconnect()
        {
            try {
                $this->close();
                return $this->connect(false);
            } catch (Exception $e) {
                return false;
            }
        }

        public static function getDsn(UmbrellaDatabaseConfiguration $configuration)
        {
            $pdoParameters = [
                'dbname' => $configuration->name,
                'charset' => 'utf8',
            ];
            $socket = $configuration->getSocket();
            if ($socket !== '') {
                $pdoParameters['host'] = $configuration->getHostname();
                $pdoParameters['unix_socket'] = $socket;
            } else {
                $pdoParameters['host'] = $configuration->getHostname();
                $pdoParameters['port'] = $configuration->getPort();
            }
            $parameters = [];
            foreach ($pdoParameters as $name => $value) {
                $parameters[] = $name . '=' . $value;
            }
            $dsn = sprintf('mysql:%s', implode(';', $parameters));
            return $dsn;
        }
    }

endif;
