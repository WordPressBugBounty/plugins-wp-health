<?php

if (!class_exists('UmbrellaCharsetFixer', false)):
    class UmbrellaCharsetFixer
    {
        protected $connection;
        protected $info;
        protected $allowFallback;

        /**
         * @param UmbrellaConnectionInterface $connection
         * @param bool $allowFallback When the structural rewrites don't match an
         *   unknown collation/charset (e.g. MySQL 8 utf8mb4_0900_*), fall back to
         *   a supported one. Enabled for cloning (fresh target DB) only; left off
         *   for in-place restore so a live site's collation semantics are never
         *   silently changed.
         */
        public function __construct(UmbrellaConnectionInterface $connection, $allowFallback = false)
        {
            $this->connection = $connection;
            $this->allowFallback = $allowFallback;
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
                // No structural rewrite matched. This happens with MySQL 8.0
                // collations (utf8mb4_0900_ai_ci, utf8mb4_0900_as_cs, ...) when
                // restoring onto MariaDB / MySQL 5.x, which don't know them.
                // Returning the original name leaves CREATE TABLE failing with
                // error 1273 and the table is never created. Fall back to a
                // collation of the same charset family that the target actually
                // supports, keeping utf8mb4 (4-byte unicode) whenever possible.
                // Cloning only: for in-place restore we keep the original name so
                // the behaviour is unchanged (the statement keeps failing as before).
                if ($this->allowFallback) {
                    return $this->fallbackCollation($name);
                }
                return $name;
            } else {
                // Encoding
                if (!empty($this->info['charset'][$name])) {
                    return $name;
                }
                $try = str_replace('utf8mb4', 'utf8', $name, $count);
                if ($count && !empty($this->info['charset'][$try])) {
                    return $try;
                }
                // Unknown charset: fall back to any supported unicode charset.
                // Cloning only; in-place restore keeps the original name (unchanged).
                if ($this->allowFallback) {
                    return $this->fallbackCharset();
                }
                return $name;
            }
        }

        /**
         * Pick a collation the target supports for the charset family of $name.
         * Prefers utf8mb4_unicode_ci (closest to MySQL 8's utf8mb4_0900_ai_ci:
         * accent- and case-insensitive Unicode), then *_general_ci, then the
         * charset's server default. Never returns an unknown collation.
         *
         * @param string $name
         * @return string
         */
        protected function fallbackCollation($name)
        {
            $this->loadInfo();

            $charset = substr($name, 0, strpos($name, '_'));
            if (empty($this->info['charset'][$charset])) {
                // Charset itself unknown: downgrade utf8mb4 -> utf8 if we can.
                if ($charset === 'utf8mb4' && !empty($this->info['charset']['utf8'])) {
                    $charset = 'utf8';
                } else {
                    $charset = $this->fallbackCharset();
                }
            }

            $candidates = [$charset . '_unicode_ci', $charset . '_general_ci'];
            foreach ($candidates as $candidate) {
                if (!empty($this->info['collation'][$candidate])) {
                    return $candidate;
                }
            }

            // Last resort: the server's default collation for this charset.
            $row = $this->connection->query("SHOW CHARACTER SET LIKE '" . $charset . "'")->fetch();
            if (is_array($row) && !empty($row['Default collation'])) {
                return $row['Default collation'];
            }

            return 'utf8_general_ci';
        }

        /**
         * A charset the target supports, preferring 4-byte unicode.
         *
         * @return string
         */
        protected function fallbackCharset()
        {
            $this->loadInfo();
            foreach (['utf8mb4', 'utf8'] as $charset) {
                if (!empty($this->info['charset'][$charset])) {
                    return $charset;
                }
            }
            return 'utf8';
        }
    }
endif;
