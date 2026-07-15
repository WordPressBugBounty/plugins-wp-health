<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessPostureAnalyzer
{
    public function analyze()
    {
        $path = ABSPATH . '.htaccess';
        $server = $this->detectServer();

        if (!file_exists($path)) {
            $directives = [
                'deny_php_in_uploads' => false,
                'protect_wp_config' => false,
                'protect_htaccess' => false,
                'disable_directory_browsing' => false,
                'block_xmlrpc' => false,
            ];

            return [
                'exists' => false,
                'server' => $server,
                'directives' => $directives,
                'umbrella_block_hash' => null,
                'fingerprint' => $this->fingerprint($directives, null),
            ];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $contents = '';
        }

        $directives = [
            'deny_php_in_uploads' => $this->hasDenyPhpInUploads($contents),
            'protect_wp_config' => $this->hasProtectWpConfig($contents),
            'protect_htaccess' => $this->hasProtectHtaccess($contents),
            'disable_directory_browsing' => $this->hasDisableDirectoryBrowsing($contents),
            'block_xmlrpc' => $this->hasBlockXmlrpc($contents),
        ];

        $umbrellaBlockHash = $this->umbrellaBlockHash($contents);

        return [
            'exists' => true,
            'server' => $server,
            'directives' => $directives,
            'umbrella_block_hash' => $umbrellaBlockHash,
            'fingerprint' => $this->fingerprint($directives, $umbrellaBlockHash),
        ];
    }

    private function detectServer()
    {
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';

        if (strpos($software, 'litespeed') !== false) {
            return 'litespeed';
        }

        if (strpos($software, 'apache') !== false) {
            return 'apache';
        }

        if (strpos($software, 'nginx') !== false) {
            return 'nginx';
        }

        return 'unknown';
    }

    private function hasDenyPhpInUploads($contents)
    {
        if (stripos($contents, 'uploads') === false) {
            return false;
        }

        return (bool) preg_match('/\.ph(p[0-9]?|tml)/i', $contents)
            && (bool) preg_match('/(Deny\s+from\s+all|Require\s+all\s+denied|SetHandler\s+None|RemoveHandler)/i', $contents);
    }

    private function hasProtectWpConfig($contents)
    {
        return (bool) preg_match('/<Files[^>]*wp-config\.php/i', $contents)
            || (bool) preg_match('/wp-config\.php/i', $contents) && (bool) preg_match('/(Deny\s+from\s+all|Require\s+all\s+denied)/i', $contents);
    }

    private function hasProtectHtaccess($contents)
    {
        return (bool) preg_match('/<Files[^>]*(\.ht[a-z]*|\^\.ht)/i', $contents)
            || (bool) preg_match('/<FilesMatch[^>]*\\\\.ht/i', $contents);
    }

    private function hasDisableDirectoryBrowsing($contents)
    {
        return (bool) preg_match('/Options\s+.*-Indexes/i', $contents);
    }

    private function hasBlockXmlrpc($contents)
    {
        if (stripos($contents, 'xmlrpc.php') === false) {
            return false;
        }

        return (bool) preg_match('/(Deny\s+from\s+all|Require\s+all\s+denied|RewriteRule.*xmlrpc)/i', $contents);
    }

    private function umbrellaBlockHash($contents)
    {
        if (preg_match('/# BEGIN WP Umbrella(.*?)# END WP Umbrella/s', $contents, $matches)) {
            return md5($matches[1]);
        }

        return null;
    }

    private function fingerprint($directives, $umbrellaBlockHash)
    {
        $posture = [
            'directives' => $directives,
            'umbrella_block_hash' => $umbrellaBlockHash,
        ];

        return md5(json_encode($posture));
    }
}
