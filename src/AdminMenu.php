<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class AdminMenu {
    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function register(): void {
        // Obsolete: Admin view removed in favor of frontend rendering
    }
}
