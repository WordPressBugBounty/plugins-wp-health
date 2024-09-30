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
            $parts = explode(':', $this->host, 2);
            if ($parts[0] === '') {
                return 'localhost';
            }
            return $parts[0];
        }

        public function getPort()
        {
            if (strpos($this->host, '/') !== false) {
                return 0;
            }
            $parts = explode(':', $this->host, 2);
            if (count($parts) === 2) {
                return (int)$parts[1];
            }
            return 0;
        }

        public function getSocket()
        {
            return self::getSocketPath($this->host);
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
            if (strpos($host, '/') === false) {
                return '';
            }
            $parts = explode(':', $host, 2);
            if (count($parts) === 2) {
                return $parts[1];
            }
            return $parts[0];
        }
    }
endif;
