<?php

namespace WPUmbrella\Core\Constants;

abstract class BackupData {
	const MO_IN_BYTES = 1048576;

	const DEFAULT_MEMORY_LIMIT_DIVIDED_BY = 2.5;

	const MAXIMUM_LINES_BY_BATCH = 100000; // For prevent overload process

	const FACTOR_CHAR_LENGTH = 2.3;
}

