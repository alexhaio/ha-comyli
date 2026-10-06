<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

abstract class AbstractDatatype {
    public string $html = '';
    public string $type = '';
    public string $val = '';
    
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(array $args) {
        $this->val = isset($args['data'][$args['name']]) ? (string)$args['data'][$args['name']] : '';
    }
    
    public function getHtml(): string {
        return $this->html;
    }
}
