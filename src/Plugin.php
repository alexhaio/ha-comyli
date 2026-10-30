<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

/**
 * Main Plugin Bootstrap Class
 * 
 * Instantiates and registers all modules required for the plugin.
 */
class Plugin {
    private Config $config;
    private Assets $assets;
    private AdminMenu $adminMenu;
    private Shortcode $shortcode;
    private Ajax $ajax;
    private LegacySpecifics $legacySpecifics;
    private PostType $postType;
    private RestApi $restApi;
    private Blocks $blocks;

    /**
     * Initializes the plugin components.
     * 
     * @param string $pluginFilePath Path to the main plugin file.
     */
    public function __construct(string $pluginFilePath) {
        $this->config = new Config($pluginFilePath);
        
        $this->assets = new Assets($this->config);
        $this->adminMenu = new AdminMenu($this->config);
        $this->shortcode = new Shortcode($this->config);
        $this->ajax = new Ajax($this->config);
        $this->legacySpecifics = new LegacySpecifics();
        $this->postType = new PostType($this->config);
        $this->restApi = new RestApi($this->config);
        $this->blocks = new Blocks($this->config);
    }

    /**
     * Triggers the registration hooks for all plugin components.
     */
    public function init(): void {
        $this->assets->register();
        $this->adminMenu->register();
        $this->shortcode->register();
        $this->ajax->register();
        $this->legacySpecifics->register();
        $this->postType->register();
        $this->restApi->register();
        $this->blocks->register();
    }
}

