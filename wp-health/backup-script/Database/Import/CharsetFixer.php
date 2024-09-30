<?php

if (!class_exists('UmbrellaCharsetFixer', false)):
    class UmbrellaCharsetFixer
    {
        protected $connection;
        protected $info;

        public function __construct(UmbrellaConnectionInterface $connection)
        {
            $this->connection = $connection;
        }

        protected function loadInfo()
        {
            if ($this->info !== null) {
                return;
            }

            $info = [
                'collation' => [],
                'charset' => [],
            ];
            $list = $this->connection->query('SHOW COLLATION')->fetchAll();
            foreach ($list as $row) {
                $info['collation'][$row['Collation']] = true;
                $info['charset'][$row['Charset']] = true;
            }

            $this->info = $info;
        }

        public function replaceCharsetOrCollation(array $matches)
        {
            $name = $matches[0];
            $this->loadInfo();
            if (strpos($name, '_') !== false) {
                // Collation
                if (!empty($this->info['collation'][$name])) {
                    return $name;
                }
                // utf8mb4_unicode_520_ci => utf8mb4_unicode_520_ci
                $try = str_replace('_520_', '_', $name, $count);
                if ($count && !empty($this->info['collation'][$try])) {
                    return $try;
                }
                // utf8mb4_unicode_520_ci => utf8_unicode_520_ci
                $try = str_replace('utf8mb4', 'utf8', $name, $count);
                if ($count && !empty($this->info['collation'][$try])) {
                    return $try;
                }
                // utf8mb4_unicode_520_ci => utf8_unicode_ci
                $try = str_replace(['utf8mb4', '_520_'], ['utf8', '_'], $name, $count);
                if ($count && !empty($this->info['collation'][$try])) {
                    return $try;
                }
            } else {
                // Encoding
                if (!empty($this->info['charset'][$name])) {
                    return $name;
                }
                $try = str_replace('utf8mb4', 'utf8', $name, $count);
                if ($count && !empty($this->info['charset'][$try])) {
                    return $try;
                }
            }
            return $name;
        }
    }
endif;
