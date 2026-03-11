<?php
namespace WPUmbrella\Models;

class RollbackSkin extends \Plugin_Upgrader_Skin
{
    public function __construct($args = [])
    {
        parent::__construct($args);

        if (!empty($args['pluginFile'])) {
            $pluginFile = $args['pluginFile'];

            if (function_exists('get_plugins')) {
                $all_plugins = get_plugins();
                if (isset($all_plugins[$pluginFile])) {
                    $this->plugin_info = $all_plugins[$pluginFile];
                }
            }
        }
    }

    public function header()
    {
        return;
    }

    public function footer()
    {
        return;
    }

    public function before()
    {
        return;
    }

    public function after()
    {
        return;
    }

    public function feedback($string, ...$args)
    {
        return;
    }
}
