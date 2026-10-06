<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class Config {
    /** @var array<string, mixed> */
    public array $plugin = [];
    /** @var array<string, string> */
    public array $path = [];

    public function __construct(string $pluginFilePath) {
        $this->plugin['name'] = "ha-comyli";
        $this->plugin['type'] = "comyli";
        $this->plugin['capability'] = 'edit_pages';
        $this->plugin['replaceReplace'] = "[" . $this->plugin['name'] . " id={{id}}]";

        $this->plugin['path'] = dirname($pluginFilePath);
        $this->plugin['url'] = plugin_dir_url($pluginFilePath);
        $this->plugin['menu_slug'] = $this->plugin['name'];

        $this->path['css'] = "";
        $this->path['js'] = "";
    }
}
