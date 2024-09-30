<?php

if (!class_exists('UmbrellaStatInfo', false)):
    class UmbrellaStatInfo
    {
        // https://unix.superglobalmegacorp.com/Net2/newsrc/sys/stat.h.html
        const S_IFMT = 0170000;   /* type of file */
        const S_IFIFO = 0010000;  /* named pipe (fifo) */
        const S_IFCHR = 0020000;  /* character special */
        const S_IFDIR = 0040000;  /* directory */
        const S_IFBLK = 0060000;  /* block special */
        const S_IFREG = 0100000;  /* regular */
        const S_IFLNK = 0120000;  /* symbolic link */
        const S_IFSOCK = 0140000; /* socket */

        private $stat;
        public $link = '';

        private function __construct(array $stat)
        {
            $this->stat = $stat;
        }

        /**
         * @return bool
         */
        public function isDir()
        {
            return ($this->stat['mode'] & self::S_IFDIR) === self::S_IFDIR;
        }

        public function isLink()
        {
            return ($this->stat['mode'] & self::S_IFLNK) === self::S_IFLNK;
        }

        public function getPermissions()
        {
            return ($this->stat['mode'] & 0777);
        }

        /**
         * @return int
         */
        public function getSize()
        {
            return $this->isDir() ? 0 : $this->stat['size'];
        }

        /**
         * @return int
         */
        public function getMTime()
        {
            return $this->stat['mtime'];
        }

        /**
         * @param array $stat Result of lstat() or stat() function call.
         *
         * @return ClonerStatInfo
         */
        public static function fromArray(array $stat)
        {
            return new self($stat);
        }

        public static function makeEmpty()
        {
            return new self(['size' => 0, 'mode' => 0, 'mtime' => 0]);
        }
    }
endif;
