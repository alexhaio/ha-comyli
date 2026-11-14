<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class Config {
    /** @var array<string, mixed> */
    public array $plugin = [];

    public function __construct(string $pluginFilePath) {
        $this->plugin['name'] = "ha-comyli";
        $this->plugin['type'] = "comyli";
        $this->plugin['replaceReplace'] = "[" . $this->plugin['name'] . " id={{id}}]";

        $this->plugin['path'] = dirname($pluginFilePath);
        $this->plugin['url'] = plugin_dir_url($pluginFilePath);
    }
}
