<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessPostureAnalyzer
{
    const FINGERPRINT_PREFIX = 'v2:';

    public function analyze()
    {
        $htaccessFile = wp_umbrella_get_service('HtaccessFile');
        $server = wp_umbrella_get_service('WebServer')->getType();

        $exists = $htaccessFile->exists();
        $contents = $exists ? $htaccessFile->getContents() : '';

        $customerContents = preg_replace('/# BEGIN WP Umbrella.*?# END WP Umbrella\n?/s', '', $contents);
        if (!is_string($customerContents)) {
            $customerContents = $contents;
        }

        $directives = [
            'deny_php_in_uploads' => $this->hasDenyPhpInUploads($customerContents),
            'protect_wp_config' => $this->hasProtectWpConfig($customerContents),
            'protect_htaccess' => $this->hasProtectHtaccess($customerContents),
            'disable_directory_browsing' => $this->hasDisableDirectoryBrowsing($customerContents),
            'block_xmlrpc' => $this->hasBlockXmlrpc($customerContents),
        ];

        $blockVersion = $htaccessFile->getBlockVersion();
        $blockIntact = $this->isBlockIntact($htaccessFile, $blockVersion);

        return [
            'exists' => $exists,
            'server' => $server,
            'directives' => $directives,
            'umbrella_block_hash' => $this->umbrellaBlockHash($contents),
            'umbrella_block_version' => $blockVersion,
            'umbrella_block_intact' => $blockIntact,
            'fingerprint' => $this->fingerprint($directives, $blockIntact),
        ];
    }

    private function isBlockIntact($htaccessFile, $blockVersion)
    {
        if (is_multisite() && !is_main_site()) {
            return true;
        }

        $enabled = wp_umbrella_get_service('HardeningSettings')->isEnabled('htaccess_umbrella_block');

        if (!$enabled) {
            return $blockVersion === null;
        }

        return $htaccessFile->isUmbrellaBlockCanonical();
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

    private function fingerprint($directives, $blockIntact)
    {
        $posture = [
            'directives' => $directives,
            'umbrella_block_intact' => $blockIntact,
        ];

        return self::FINGERPRINT_PREFIX . md5(json_encode($posture));
    }
}
