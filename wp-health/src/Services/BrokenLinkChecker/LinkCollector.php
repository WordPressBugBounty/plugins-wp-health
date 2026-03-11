<?php
namespace WPUmbrella\Services\BrokenLinkChecker;

class LinkCollector
{
    const MAX_HTML_SIZE = 2 * 1024 * 1024; // 2MB
    const MAX_ANCHOR_LENGTH = 500;

    public function extractLinks($html, $pageUrl)
    {
        if (strlen($html) > self::MAX_HTML_SIZE) {
            return [];
        }

        $siteHost = parse_url(site_url(), PHP_URL_HOST);

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $this->removeElementById($doc, 'wpadminbar');

        $links = [];
        $anchors = $doc->getElementsByTagName('a');

        foreach ($anchors as $node) {
            $href = $node->getAttribute('href');

            if ($this->shouldSkipHref($href)) {
                continue;
            }

            $href = $this->resolveUrl($href, $pageUrl);

            if (empty($href)) {
                continue;
            }

            $anchorText = trim($node->textContent);
            if (strlen($anchorText) > self::MAX_ANCHOR_LENGTH) {
                $anchorText = substr($anchorText, 0, self::MAX_ANCHOR_LENGTH);
            }

            $hrefHost = parse_url($href, PHP_URL_HOST);
            $isInternal = $hrefHost && $siteHost && strcasecmp($hrefHost, $siteHost) === 0;

            $links[] = [
                'href' => $href,
                'anchor_text' => $anchorText,
                'is_internal' => $isInternal ? 1 : 0,
                'position' => $this->detectPosition($node),
                'rel' => $node->getAttribute('rel') ?: null,
            ];
        }

        return $links;
    }

    protected function shouldSkipHref($href)
    {
        if (empty($href) || $href === '#') {
            return true;
        }

        $prefixes = ['javascript:', 'mailto:', 'tel:', 'data:'];
        foreach ($prefixes as $prefix) {
            if (strpos($href, $prefix) === 0) {
                return true;
            }
        }

        if (strpos($href, '/wp-admin') !== false || strpos($href, '/wp-login') !== false) {
            return true;
        }

        return false;
    }

    protected function resolveUrl($href, $pageUrl)
    {
        if (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0) {
            return $href;
        }

        if (strpos($href, '//') === 0) {
            $scheme = parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $href;
        }

        if (strpos($href, '/') === 0) {
            $parsed = parse_url(site_url());
            return $parsed['scheme'] . '://' . $parsed['host'] . $href;
        }

        // Relative URL
        $base = rtrim(dirname($pageUrl), '/');
        return $base . '/' . $href;
    }

    protected function removeElementById(\DOMDocument $doc, $id)
    {
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query("//*[@id='{$id}']");

        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    protected function detectPosition(\DOMNode $node)
    {
        $current = $node->parentNode;

        while ($current && $current instanceof \DOMElement) {
            $tag = strtolower($current->tagName);
            $class = strtolower($current->getAttribute('class'));

            if ($tag === 'header' || $tag === 'nav') {
                return 'header';
            }

            if ($tag === 'footer') {
                return 'footer';
            }

            if ($tag === 'aside' || strpos($class, 'sidebar') !== false || strpos($class, 'widget') !== false) {
                return 'sidebar';
            }

            $current = $current->parentNode;
        }

        return 'content';
    }
}
