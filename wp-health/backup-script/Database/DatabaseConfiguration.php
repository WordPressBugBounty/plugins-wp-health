<?php

if (!class_exists('UmbrellaDatabaseConfiguration', false)):
    class UmbrellaDatabaseConfiguration
    {
        public $user = '';
        public $password = '';
        /** @var string https://codex.wordpress.org/Editing_wp-config.php#Possible_DB_HOST_values */
        public $host = '';
        public $name = '';
        public $useSSL = false;

        public function __construct($user, $password, $host, $name, $useSSL = false)
        {
            $this->user = $user;
            $this->password = $password;
            $this->host = $host;
            $this->name = $name;
            $this->useSSL = $useSSL;
        }

        public static function fromArray($info)
        {
            if (empty($info)) {
                return self::createEmpty();
            } elseif ($info instanceof self) {
                return $info;
            }
            return new self(
                $info['db_user'],
                $info['db_password'],
                $info['db_host'],
                $info['db_name'],
                $info['db_ssl']
            );
        }

        public static function createEmpty()
        {
            return new self('', '', '', '');
        }

        public function getHostname()
        {
            $host = self::stripSocketPath($this->host);
            preg_match('#^([^:/]*)#', $host, $parts);
            if ($parts[1] === '') {
                return 'localhost';
            }
            return $parts[1];
        }

        public function getPort()
        {
            if (self::getSocketPath($this->host) !== '') {
                return 0;
            }
            if (preg_match('#^[^:/]*:(\d+)#', $this->host, $parts)) {
                return (int)$parts[1];
            }
            return 0;
        }

        public function getSocket()
        {
            return self::getSocketPath($this->host);
        }

        /**
         * @return array
         */
        public function getLegacyParsing()
        {
            $parts = explode(':', $this->host, 2);
            $hostname = $parts[0] === '' ? 'localhost' : $parts[0];

            $port = 0;
            $socket = '';
            if (strpos($this->host, '/') === false) {
                $port = count($parts) === 2 ? (int) $parts[1] : 0;
            } else {
                $socket = count($parts) === 2 ? $parts[1] : $parts[0];
            }

            if ($hostname === $this->getHostname() && $port === $this->getPort() && $socket === $this->getSocket()) {
                return [];
            }

            return ['hostname' => $hostname, 'port' => $port, 'socket' => $socket];
        }

        public function setUseSSL($ssl)
        {
            $this->useSSL = $ssl;
            return $this;
        }

        public function toArray()
        {
            return [
                'db_user' => $this->user,
                'db_password' => $this->password,
                'db_name' => $this->name,
                'db_host' => $this->host,
                'db_ssl' => $this->useSSL,
            ];
        }

        protected static function getSocketPath($host)
        {
            $separator = strpos($host, ':/');
            if ($separator !== false) {
                return substr($host, $separator + 1);
            }
            if (strpos($host, '/') === 0) {
                return $host;
            }
            return '';
        }

        protected static function stripSocketPath($host)
        {
            $separator = strpos($host, ':/');
            if ($separator !== false) {
                return substr($host, 0, $separator);
            }
            return $host;
        }
    }
endif;
