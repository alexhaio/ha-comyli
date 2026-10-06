<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class TextType extends AbstractDatatype {
    public string $type = 'text';
    
    public function __construct(array $args) {
        parent::__construct($args);
        
        $descShort = isset($args['description_short']) ? $args['description_short'] : '';
        $descLong = isset($args['description_long']) ? $args['description_long'] : '';
        
        $this->html = "<div class='" . esc_attr($this->type) . "'>"
             . "<label for='" . esc_attr($args['name']) . "'>" . esc_html($descShort) . "</label>"
             . "<input name='" . esc_attr($args['name']) . "' id='" . esc_attr($args['name']) . "' type='" . esc_attr($this->type) . "' value='" . esc_attr($this->val) . "' />"
             . "<p>" . wp_kses_post($descLong) . "</p>"
        . "</div>";
    }
}
