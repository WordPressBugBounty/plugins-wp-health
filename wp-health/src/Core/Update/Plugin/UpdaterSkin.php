<?php
namespace WPUmbrella\Core\Update\Plugin;

use WP_Error;
use WP_Ajax_Upgrader_Skin;

class UpdaterSkin extends WP_Ajax_Upgrader_Skin
{

    public function request_filesystem_credentials($error = false, $context = '', $allow_relaxed_file_ownership = false)
    {
        if ($context) {
            $this->options['context'] = $context;
        }


		$this->errors = new WP_Error();

        // file.php and template.php are documented to be required; the rest are there to match
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/template.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        // This will output a credentials form in event of failure; we don't want that, so just hide with a buffer.
        @ob_start();
        /** @handled function */
        $result = request_filesystem_credentials('', '', $error, $context, null, $allow_relaxed_file_ownership);
		@flush();
        @ob_end_clean();

        return $result;
    }

}
