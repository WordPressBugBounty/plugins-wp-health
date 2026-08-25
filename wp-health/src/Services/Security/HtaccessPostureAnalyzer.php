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

        $uploadsExecution = $htaccessFile->probeUploadsPhpExecution();

        $directives = [
            'deny_php_in_uploads' => $this->deniesPhpInUploads($uploadsExecution, $customerContents),
            'protect_wp_config' => $this->hasProtectWpConfig($customerContents),
            'protect_htaccess' => $this->hasProtectHtaccess($customerContents),
            'disable_directory_browsing' => $this->hasDisableDirectoryBrowsing($customerContents),
            'block_xmlrpc' => $this->hasBlockXmlrpc($customerContents),
        ];

        $blockVersion = $htaccessFile->getBlockVersion();
        $blockIntact = $this->isBlockIntact($htaccessFile, $blockVersion);
        $probe = wp_umbrella_get_service('StaticFileProtectionProbe');

        return [
            'exists' => $exists,
            'server' => $server,
            'edge_server' => $probe->getEdgeServer(),
            'static_files_protected' => $probe->getState(),
            'directives' => $directives,
            'uploads_php_execution' => $uploadsExecution,
            'uploads_block_present' => $htaccessFile->hasUploadsBlock(),
            'umbrella_block_hash' => $this->umbrellaBlockHash($contents),
            'umbrella_block_version' => $blockVersion,
            'umbrella_block_intact' => $blockIntact,
            'fingerprint' => $this->fingerprint($directives, $blockIntact),
        ];
    }

    protected function deniesPhpInUploads($uploadsExecution, $customerContents)
    {
        if ($uploadsExecution === 'blocked') {
            return true;
        }

        if ($uploadsExecution === 'executed' || $uploadsExecution === 'served_raw') {
            return false;
        }

        return $this->hasDenyPhpInUploads($customerContents);
    }

    protected function isBlockIntact($htaccessFile, $blockVersion)
    {
        if (is_multisite() && !is_main_site()) {
            return true;
        }

        $settings = wp_umbrella_get_service('HardeningSettings');

        if (!$settings->isEnabled('htaccess_umbrella_block')) {
            return $blockVersion === null;
        }

        $state = $settings->getBlockState();

        if ($htaccessFile->isUploadsOnlyState()) {
            if ($state === null) {
                $settings->recordBlockState(['status' => 'partial']);

                return true;
            }

            return $state['status'] === 'partial';
        }

        if ($blockVersion === 1 && isset($state['version']) && (int) $state['version'] > 1) {
            return false;
        }

        return $htaccessFile->isUmbrellaBlockCanonical();
    }

    protected function hasDenyPhpInUploads($contents)
    {
        if (stripos($contents, 'uploads') === false) {
            return false;
        }

        return (bool) preg_match('/\.ph(p[0-9]?|tml)/i', $contents)
            && (bool) preg_match('/(Deny\s+from\s+all|Require\s+all\s+denied|SetHandler\s+None|RemoveHandler)/i', $contents);
    }

    protected function hasProtectWpConfig($contents)
    {
        return (bool) preg_match('/<Files[^>]*wp-config\.php/i', $contents)
            || (bool) preg_match('/wp-config\.php/i', $contents) && (bool) preg_match('/(Deny\s+from\s+all|Require\s+all\s+denied)/i', $contents);
    }

    protected function hasProtectHtaccess($contents)
    {
        return (bool) preg_match('/<Files[^>]*(\.ht[a-z]*|\^\.ht)/i', $contents)
            || (bool) preg_match('/<FilesMatch[^>]*\\\\.ht/i', $contents);
    }

    protected function hasDisableDirectoryBrowsing($contents)
    {
        return (bool) preg_match('/Options\s+.*-Indexes/i', $contents);
    }

    protected function hasBlockXmlrpc($contents)
    {
        if (stripos($contents, 'xmlrpc.php') === false) {
            return false;
        }

        return (bool) preg_match('/(Deny\s+from\s+all|Require\s+all\s+denied|RewriteRule.*xmlrpc)/i', $contents);
    }

    protected function umbrellaBlockHash($contents)
    {
        if (preg_match('/# BEGIN WP Umbrella(.*?)# END WP Umbrella/s', $contents, $matches)) {
            return md5($matches[1]);
        }

        return null;
    }

    protected function fingerprint($directives, $blockIntact)
    {
        $posture = [
            'directives' => $directives,
            'umbrella_block_intact' => $blockIntact,
        ];

        return self::FINGERPRINT_PREFIX . md5(json_encode($posture));
    }
}
