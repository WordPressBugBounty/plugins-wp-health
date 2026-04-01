<?php

namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooksFrontend;

class BrokenLinkHighlighter implements ExecuteHooksFrontend
{
    public function hooks()
    {
        add_action('wp_footer', [$this, 'renderHighlightScript'], WP_UMBRELLA_MAX_PRIORITY_HOOK);
    }

    public function renderHighlightScript()
    {
        if (!isset($_GET['wpu-broken-links'])) {
            return;
        }

        ?>
        <script>
        (function () {
            var hash = window.location.hash;
            if (hash.indexOf('#wpu-broken-links=') !== 0) {
                return;
            }

            var raw = hash.substring('#wpu-broken-links='.length);
            var hrefs = raw.split('||').map(decodeURIComponent).filter(Boolean);

            if (!hrefs.length) {
                return;
            }

            var style = document.createElement('style');
            style.textContent =
                '.wpu-broken-link-highlight {' +
                '  outline: 3px solid #e74c3c !important;' +
                '  background-color: rgba(231, 76, 60, 0.15) !important;' +
                '  border-radius: 3px !important;' +
                '  position: relative !important;' +
                '}' +
                '.wpu-broken-link-highlight::after {' +
                '  content: "Broken link" !important;' +
                '  position: absolute !important;' +
                '  top: -22px !important;' +
                '  left: 0 !important;' +
                '  background: #e74c3c !important;' +
                '  color: #fff !important;' +
                '  font-size: 11px !important;' +
                '  padding: 2px 6px !important;' +
                '  border-radius: 3px !important;' +
                '  white-space: nowrap !important;' +
                '  z-index: 999999 !important;' +
                '  line-height: 1.4 !important;' +
                '  font-family: -apple-system, BlinkMacSystemFont, sans-serif !important;' +
                '}' +
                '';
            document.head.appendChild(style);

            var links = document.querySelectorAll('a[href]');
            var matched = [];

            for (var i = 0; i < links.length; i++) {
                var link = links[i];
                for (var j = 0; j < hrefs.length; j++) {
                    if (link.href === hrefs[j] || link.getAttribute('href') === hrefs[j]) {
                        link.classList.add('wpu-broken-link-highlight');
                        matched.push(link);
                        break;
                    }
                }
            }

            if (matched.length > 0) {
                matched[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname);
            }
        })();
        </script>
        <?php
    }
}
