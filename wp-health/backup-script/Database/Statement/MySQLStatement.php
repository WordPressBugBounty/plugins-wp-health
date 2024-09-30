<?php

if (!class_exists('UmbrellaMySQLStatement', false)):
    class UmbrellaMySQLStatement implements UmbrellaDatabaseStatementInterface
    {
        protected $connection;
        protected $result;

        /**
         * @param resource      $conn
         * @param resource|null $result
         *
         * @throws Exception
         */
        public function __construct($connection, $result = null)
        {
            $this->connection   = $connection;
            $this->result = $result;
        }

        public function fetch()
        {
            if ($this->result === false && mysql_errno($this->connection)) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_query_error', mysql_errno($this->connection));
            } elseif (!is_resource($this->result)) {
                throw new UmbrellaException("Only read-only queries can yield results.", 'db_query_error');
            }
            $result = @mysql_fetch_assoc($this->result);
            if ($result === false && mysql_errno($this->connection)) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_query_error', mysql_errno($this->connection));
            }
            return $result;
        }

        public function fetchAll()
        {
            $rows = array();
            while ($row = $this->fetch()) {
                $rows[] = $row;
            }
            return $rows;
        }

        public function getNumRows()
        {
            return mysql_num_rows($this->result);
        }

        public function free()
        {
            if (!is_resource($this->result)) {
                return true;
            }
            return mysql_free_result($this->result);
        }
    }
endif;
