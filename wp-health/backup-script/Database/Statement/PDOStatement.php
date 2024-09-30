<?php

if (!class_exists('UmbrellaPDOStatement', false)):
    class UmbrellaPDOStatement implements UmbrellaDatabaseStatementInterface
    {

        protected $statement;


        public function __construct(PDOStatement $statement)
        {
            $this->statement = $statement;
        }

        public function fetch()
        {
            try {
                return $this->statement->fetch();
            } catch (PDOException $e) {
                $internalErrorCode = isset($e->errorInfo[1]) ? (string)$e->errorInfo[1] : '';
                throw new UmbrellaException($e->getMessage(), 'db_query_error', $internalErrorCode);
            }
        }

        public function fetchAll()
        {
            return $this->statement->fetchAll();
        }

        public function getNumRows()
        {
            return $this->statement->rowCount();
        }

        public function free()
        {
            return $this->statement->closeCursor();
        }
    }
endif;
