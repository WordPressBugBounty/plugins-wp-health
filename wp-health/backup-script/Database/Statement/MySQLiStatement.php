<?php

if (!class_exists('UmbrellaMySQLiStatement', false)):
    class UmbrellaMySQLiStatement implements UmbrellaDatabaseStatementInterface
    {
        protected $connection;
        protected $result;

        /**
         * @param mysqli_driver      $result
         * @param mysqli_result|bool $result
         */
        public function __construct($connection, $result)
        {
            $this->connection   = $connection;
            $this->result = $result;
        }

        /**
         * @return array|null
         */
        public function fetch()
        {
            if (($this->result === false || $this->result === null) && $this->connection->errno) {
                throw new UmbrellaException($this->connection->error, 'db_query_error', $this->connection->errno);
            } elseif (!$this->result) {
                throw new UmbrellaException("Only read-only queries can yield results.", 'db_query_error');
            }
            $result = $this->result->fetch_assoc();
            if (($result === false || $result === null) && $this->connection->errno) {
                throw new UmbrellaException($this->connection->error, 'db_query_error', $this->connection->errno);
            }
            return $result;
        }

        /**
         * @return array|null
         */
        public function fetchAll()
        {
            $rows = [];
            while ($row = $this->fetch()) {
                $rows[] = $row;
            }
            return $rows;
        }

        /**
         * @return int
         */
        public function getNumRows()
        {
            if (is_bool($this->result)) {
                return 0;
            }
            return $this->result->num_rows;
        }

        /**
         * @return bool
         */
        public function free()
        {
            if (is_bool($this->result)) {
                return false;
            }
            mysqli_free_result($this->result);
            return true;
        }
    }
endif;
