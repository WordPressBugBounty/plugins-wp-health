<?php


if (!class_exists('UmbrellaDatabaseStatementInterface', false)):
    interface UmbrellaDatabaseStatementInterface
    {
        /**
         * @return int
         */
        public function getNumRows();

        /**
         * @return array|null
         *
         * @throws ClonerException
         */
        public function fetch();

        /**
         * @return array|null
         *
         * @throws ClonerException
         */
        public function fetchAll();

        /**
         * @return bool
         */
        public function free();
    }
endif;
