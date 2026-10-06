<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class HiddenType extends AbstractDatatype {
    public string $type = 'hidden';
    
    public function __construct(array $args) {
        parent::__construct($args);
        $this->html = "<input name='" . esc_attr($args['name']) . "' type='" . esc_attr($this->type) . "' value='" . esc_attr($this->val) . "' />";
    }
}
