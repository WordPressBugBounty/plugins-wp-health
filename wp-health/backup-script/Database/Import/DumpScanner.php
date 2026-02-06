<?php

if (!class_exists('UmbrellaDumpScanner', false)):
    class UmbrellaDumpScanner
    {
        const INSERT_REPLACEMENT_PATTERN = '#^INSERT\\s+INTO\\s+(`?)[^\\s`]+\\1\\s+(?:\([^)]+\)\\s+)?VALUES\\s*#';
        // File handle.
        private $handle;
        // 0 - unknown ending
        // 1 - \n ending
        // 2 - \r\n ending
        private $rn = 0;
        private $cursor = 0;
        // Buffer that holds up to one statement.
        private $buffer = '';

        /**
         * @param string $path
         *
         * @throws UmbrellaException
         */
        public function __construct($path)
        {
            $this->handle = @fopen($path, 'rb');
            if (!is_resource($this->handle)) {
                throw new UmbrellaException('Could not open database dump file', 'db_dump_open');
            }
        }

        /**
         * @param int $maxCount
         * @param int $maxSize
         *
         * @return string Up to $maxCount statements or until half of $maxSize (in bytes) is reached.
         *
         * @throws UmbrellaException
         */
        public function scan($maxCount, $maxSize)
        {
            $lineBuffer = '';
            $buffer = '';
            $delimited = false;
            $count = 0;
            $inserts = false;
            while (true) {
                if (strlen($this->buffer)) {
                    $line = $this->buffer;
                    $this->buffer = '';
                } else {
                    $line = fgets($this->handle);
                    if ($line === false) {
                        if (feof($this->handle)) {
                            // So, this is needed...
                            break;
                        }
                        throw new UmbrellaException('Could not read database dump line', 'db_dump_read_line');
                    }
                    $this->cursor += strlen($line);
                }
                $len = strlen($line);
                if ($this->rn === 0) {
                    // Run only once - detect line ending.
                    if (substr_compare($line, "\r\n", $len - 2) === 0) {
                        $this->rn = 2;
                    } else {
                        $this->rn = 1;
                    }
                }

                if (strlen($lineBuffer) === 0) {
                    // Detect comments.
                    if ($len <= 2 + $this->rn) {
                        if ($this->rn === 2) {
                            if ($line === "--\r\n" || $line === "\r\n") {
                                continue;
                            }
                        } else {
                            if ($line === "--\n" || $line === "\n") {
                                continue;
                            }
                        }
                    }
                    if (strncasecmp($line, '-- ', 3) === 0) {
                        continue;
                    }
                    if (preg_match('{^\s*$}', $line)) {
                        continue;
                    }
                }

                if (($len >= 2 && $this->rn === 1 && substr_compare($line, ";\n", $len - 2) === 0)
                    || ($len >= 3 && $this->rn === 2 && substr_compare($line, ";\r\n", $len - 3) === 0)
                ) {
                    // Statement did end - fallthrough. This logic just makes more sense to write.
                } else {
                    $lineBuffer .= $line;
                    continue;
                }
                if (strlen($lineBuffer)) {
                    $line = $lineBuffer . $line;
                    $lineBuffer = '';
                }
                // Hack, but it's all for the greater good. The mysqldump command dumps statements
                // like "/*!50013 DEFINER=`user`@`localhost` SQL SECURITY DEFINER */" which require
                // super-privileges. That's way too troublesome, so just skip those statements.
                if (strncmp($line, '/*!50013 DEFINER=`', 18) === 0) {
                    continue;
                }
                // /*!50003 CREATE*/ /*!50017 DEFINER=`foo`@`localhost`*/ /*!50003 TRIGGER `wp_hplugin_root` BEFORE UPDATE ON `wp_hplugin_root` FOR EACH ROW SET NEW.last_modified = NOW() */;
                if (strncmp($line, '/*!50003 CREATE*/ /*!50017 DEFINER=', 35) === 0) {
                    $line = preg_replace('{/\*!50017 DEFINER=.*?(\*/)}', '', $line, 1);
                }
                if (strncmp($line, '/*!50001 CREATE ALGORITHM=', 26) === 0) {
                    continue;
                }
                if (strncmp($line, '/*!50001 VIEW', 13) === 0) {
                    continue;
                }
                $count++;
                if ($delimited) {
                    // We're inside a block that looks like this:
                    //
                    //  DELIMITER ;;
                    //  /*!50003 CREATE*/ /*!50017 DEFINER=`user`@`localhost`*/ /*!50003 TRIGGER `wp_hlogin_default_storage_table` BEFORE UPDATE ON `wp_hlogin_default_storage_table`
                    //  FOR EACH ROW SET NEW.last_modified = NOW() */;;
                    //  DELIMITER ;
                    //
                    // Since the DELIMITER statement does nothing when not in the CLI context, we need to merge the delimited statements
                    // manually into a single statement.
                    if (strncmp($line, 'DELIMITER ;', 11) === 0) {
                        break;
                    }
                    // Replace the new delimiter with the default one (remove one semicolon).
                    if (($this->rn === 1 && substr_compare($line, ";;\n", -3, 3) === 0)
                        || ($this->rn === 2 && substr_compare($line, ";;\r\n", -4, 4) === 0)
                    ) {
                        $line = substr($line, 0, -($this->rn + 1)); // strip ";\n" or ";\r\n" at the end.
                    }
                    $buffer .= $line . "\n";
                    continue;
                } elseif (strncmp($line, 'DELIMITER ;;', 12) === 0) {
                    $delimited = true;
                    continue;
                }
                if (strncmp($line, 'INSERT INTO ', 12) === 0) {
                    $inserts = true;
                    if (strlen($buffer) === 0) {
                        $buffer = 'INSERT IGNORE INTO ' . substr($line, strlen('INSERT INTO '), -(1 + $this->rn)); // Strip the ";\n" or ";\r\n" at the end
                    } else {
                        if (strlen($buffer) + strlen($line) >= max(1, $maxSize / 2)) {
                            $this->buffer = $line;
                            break;
                        }
                        $newLine = preg_replace(self::INSERT_REPLACEMENT_PATTERN, ', ', $line, 1, $c);
                        $newLine = substr($newLine, 0, -(1 + $this->rn));
                        if ($c !== 1) {
                            throw new UmbrellaException(sprintf('Could not parse INSERT line: %s', $line), 'parse_insert_line');
                        }
                        $buffer .= $newLine;
                    }
                    if ($count >= $maxCount) {
                        break;
                    }
                    continue;
                } elseif ($inserts) {
                    // $buffer is not empty and we aren't inserting anything - break.
                    $this->buffer = $line;
                } else {
                    $buffer = $line;
                }
                break;
            }
            if ($inserts) {
                $buffer .= ';';
            }
            return $buffer;
        }

        /**
         * @param int $offset
         *
         * @throws UmbrellaException
         */
        public function seek($offset)
        {
            $seek = @fseek($this->handle, $offset);
            if ($seek === false) {
                throw new UmbrellaException('Could not seek database dump file', 'seek_file');
            }
            $this->cursor = $offset;
        }

        public function tell()
        {
            return $this->cursor - strlen($this->buffer);
        }

        public function close()
        {
            fclose($this->handle);
        }
    }
endif;
