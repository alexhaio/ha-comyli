<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class TextareaType extends AbstractDatatype {
    public string $type = 'textarea';
    
    public function __construct(array $args) {
        parent::__construct($args);
        
        $descShort = isset($args['description_short']) ? $args['description_short'] : '';
        $descLong = isset($args['description_long']) ? $args['description_long'] : '';
        
        $this->html = "<div class='" . esc_attr($this->type) . "'>"
             . "<label for='" . esc_attr($args['name']) . "'>" . esc_html($descShort) . "</label>"
             . "<textarea name='" . esc_attr($args['name']) . "' id='" . esc_attr($args['name']) . "' rows='10' style='width: 100%; font-family: monospace;'>" . esc_textarea($this->val) . "</textarea>"
             . "<p>" . wp_kses_post($descLong) . "</p>"
        . "</div>";
    }
}
