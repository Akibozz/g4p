<?php

namespace ADP\BaseVersion\Includes;

use ADP\BaseVersion\Includes\AdminExtensions\AdminNotice;
use ADP\BaseVersion\Includes\AdminExtensions\AdminPage;
use ADP\BaseVersion\Includes\Database\Database;

defined('ABSPATH') or exit;

class PluginActions
{
    /**
     * @var string
     */
    protected $pluginFileFullPath;

    /**
     * @param string|null $pluginFileFullPath
     */
    public function __construct($pluginFileFullPath)
    {
        $this->pluginFileFullPath = $pluginFileFullPath;
    }

    /**
     *  Only a static class method or function can be used in an uninstall hook.
     */
    public function register()
    {
        if ($this->pluginFileFullPath && file_exists($this->pluginFileFullPath)) {
            register_activation_hook($this->pluginFileFullPath, array($this, 'install'));
            register_deactivation_hook($this->pluginFileFullPath, array($this, 'deactivate'));
            add_filter(
                'plugin_action_links_' . plugin_basename(WC_ADP_PLUGIN_PATH . WC_ADP_PLUGIN_FILE),
                array($this, 'settingsLink')
            );
        }
    }

    public function settingsLink($actions)
    {
        $settingsLink = sprintf(
            '<a href=%s>%s</a>',
            admin_url('admin.php?page=' . AdminPage::SLUG),
            __('Settings', 'advanced-dynamic-pricing-for-woocommerce')
        );
        array_unshift($actions, $settingsLink);

        return $actions;
    }

    public function install()
    {
        Database::createDatabase();
        do_action('wdp_install');
    }

    public function deactivate()
    {
        AdminNotice::cleanUp();
    }

    /**
     * Method required for tests
     */
    public function uninstall()
    {
        $file = WC_ADP_PLUGIN_PATH . 'uninstall.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
}
