<?php
namespace WPUmbrella\Services\ApiWordPress;

class BearerTokenExtractor
{
    /**
     * Parse a Bearer token from a raw Authorization header value.
     *
     * Returns null when the header is missing, empty, not a string,
     * does not start with the "Bearer " scheme, or carries an empty value.
     *
     * The scheme prefix is matched case-insensitively per RFC 6750.
     *
     * @param mixed $headerValue
     * @return string|null
     */
    public function fromHeaderValue($headerValue)
    {
        if (!is_string($headerValue) || $headerValue === '') {
            return null;
        }

        if (stripos($headerValue, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($headerValue, 7));

        if ($token === '') {
            return null;
        }

        return $token;
    }
}
