<?php

namespace WPUmbrella\Core\Constants;

abstract class SignedRequest {
	const ALGORITHM = 'ed25519';

	const TIMESTAMP_HEADER = 'X-Umbrella-Timestamp';
	const NONCE_HEADER = 'X-Umbrella-Nonce';
	const SIGNATURE_HEADER = 'X-Umbrella-Signature';
	const KEY_ID_HEADER = 'X-Umbrella-KeyId';

	const FRESHNESS_WINDOW_SECONDS = 300;

	const CANONICAL_SEPARATOR = "\n";
}
