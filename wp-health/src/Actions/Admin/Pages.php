<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Helpers\Pages as PagesHelper;

class Pages implements ExecuteHooksBackend
{
    protected $optionService;

    protected $getOwnerService;

    protected $owner;

    protected $options;

    public function __construct()
    {
        $this->optionService = wp_umbrella_get_service('Option');
        $this->getOwnerService = wp_umbrella_get_service('Owner');
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'addPage']);
        add_action('admin_enqueue_scripts', [$this, 'hideMenu']);
    }

    /**
     * Add menu and sub pages.
     *
     * @see admin_menu
     */
    public function addPage()
    {
        $dataWhiteLabel = wp_umbrella_get_service('WhiteLabel')->getData();

        add_options_page(
            $dataWhiteLabel['plugin_name'],
            $dataWhiteLabel['plugin_name'],
            'manage_options',
            PagesHelper::SETTINGS,
            [$this, 'settings']
        );
    }

    public function hideMenu()
    {
        if (!\wp_umbrella_get_service('WhiteLabel')->hideMenu()) {
            return;
        } ?>
<style type="text/css">
	.wp-submenu a[href='options-general.php?page=wp-umbrella-settings'] {
		display: none !important;
	}
</style>
<?php
    }

    public function settings()
    {
        if (wp_umbrella_allowed()) {
            $this->owner = $this->getOwnerService->getOwnerImplicitApiKey();
            $this->options = $this->optionService->getOptions();

            $backupVersion = get_option('wp_umbrella_backup_version');

            if (
                isset($this->owner['project']) &&
                isset($this->owner['project']['ProjectBackupSetting']) &&
                isset($this->owner['project']['ProjectBackupSetting']['process_version']) &&
                $this->owner['project']['ProjectBackupSetting']['process_version'] !== $backupVersion
            ) {
                update_option('wp_umbrella_backup_version', $this->owner['project']['ProjectBackupSetting']['process_version'], false);
            }
        }

        include_once WP_UMBRELLA_TEMPLATES_ADMIN_PAGES . '/settings.php';
    }
}
