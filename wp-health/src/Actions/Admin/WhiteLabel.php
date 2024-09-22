<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Core\Hooks\DeactivationHook;

class WhiteLabel implements ExecuteHooksBackend, DeactivationHook
{
    protected $isActiveWhiteLabel = null;

	protected $getOwnerService;

    public function __construct()
    {
        $this->getOwnerService = wp_umbrella_get_service('Owner');

    }

    public function hooks()
    {
        add_filter('plugin_action_links', [$this, 'pluginLinks'], 10, 2);
        add_filter('all_plugins', [$this, 'pluginInfoFilter'], 10, 2);
        add_filter('plugin_row_meta', [$this, 'pluginRowMeta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueCSS']);
    }

	public function deactivate(){
		delete_transient('wp_umbrella_white_label_data_cache');
	}


    public function adminEnqueueCSS($page)
    {
        if ($page !== 'plugins.php' && $page !== 'update-core.php') {
            return;
        }

        if (isset($_GET['plugin_status']) && $_GET['plugin_status'] !== 'mustuse') {
            return;
        }

		$whiteLabelData = wp_umbrella_get_service('WhiteLabel')->getData();

		if($whiteLabelData['plugin_name'] !== 'WP Umbrella' || $whiteLabelData['hide_plugin']){
			echo '<style>
			table.plugins [data-plugin="_WPHealthHandlerMU.php"],
			table.plugins [data-plugin="InitUmbrella.php"] {
				display: none;
			}
		  </style>
		  ';
		}


		if($page === 'update-core.php') {

			if($whiteLabelData['hide_plugin']){
				?>
				<script>
					document.addEventListener("DOMContentLoaded", function(event) {
						const checkbox = document.querySelector("input[value=\'wp-health/wp-health.php\']")

						if(checkbox) {
							checkbox.closest("tr").style.display = "none";
						}
					});

				</script>
				<?php
			}
			else if($whiteLabelData['plugin_name'] !== 'WP Umbrella'){
				?>
				<script>
					document.addEventListener("DOMContentLoaded", function(event) {

						const cell = document.querySelector('input[value=\'wp-health/wp-health.php\']').closest('tr').querySelector('.plugin-title');

						if(cell){
							cell.querySelector('strong').innerHTML = "<?php echo $whiteLabelData['plugin_name']; ?>";
							cell.querySelector('img').src = "<?php echo $whiteLabelData['logo']; ?>";
							cell.querySelector('a').innerHTML = '';
						}

					});

				</script>
				<?php
			}




		}
    }

    /**
     * @wp_filter all_plugins
     */
    public function pluginInfoFilter($plugins)
    {

        if (!isset($plugins[WP_UMBRELLA_BNAME])) {
            return $plugins;
        }

		$whiteLabelData = wp_umbrella_get_service('WhiteLabel')->getData();

        if ($whiteLabelData['hide_plugin']) {
            unset($plugins[WP_UMBRELLA_BNAME]);
            return $plugins;
        }

        $plugins[WP_UMBRELLA_BNAME]['Name'] = $whiteLabelData['plugin_name'];
        $plugins[WP_UMBRELLA_BNAME]['Title'] = $whiteLabelData['plugin_name'];
        $plugins[WP_UMBRELLA_BNAME]['Description'] = $whiteLabelData['plugin_description'];
        $plugins[WP_UMBRELLA_BNAME]['AuthorURI'] = $whiteLabelData['plugin_author_url'];
        $plugins[WP_UMBRELLA_BNAME]['Author'] = $whiteLabelData['plugin_author'];
        $plugins[WP_UMBRELLA_BNAME]['AuthorName'] = $whiteLabelData['plugin_author'];
        $plugins[WP_UMBRELLA_BNAME]['PluginURI'] = '';

        return $plugins;
    }

    public function pluginRowMeta($meta, $slug)
    {
        if ($slug !== WP_UMBRELLA_BNAME) {
            return $meta;
        }

        if (isset($meta[2])) {
            unset($meta[2]);
        }

        return $meta;
    }

    public function pluginLinks($links, $file)
    {
        if (WP_UMBRELLA_BNAME !== $file) {
            return $links;
        }

        $settings = sprintf('<a href="%s">%s</a>', admin_url('options-general.php?page=wp-umbrella-settings'), __('Settings'));
        array_unshift($links, $settings);

        return $links;
    }
}
