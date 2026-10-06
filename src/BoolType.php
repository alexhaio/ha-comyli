<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class BoolType extends AbstractDatatype {
    public string $type = 'bool';
    
    public function __construct(array $args) {
        parent::__construct($args);
        
        $descShort = isset($args['description_short']) ? $args['description_short'] : '';
        $descLong = isset($args['description_long']) ? $args['description_long'] : '';
        
        $selectedNo = ($this->val === '0') ? ' selected' : '';
        $selectedYes = ($this->val === '1') ? ' selected' : '';
        
        $this->html = "<div class='" . esc_attr($this->type) . "'>"
             . "<label for='" . esc_attr($args['name']) . "'>" . esc_html($descShort) . "</label>"
             . "<select name='" . esc_attr($args['name']) . "' id='" . esc_attr($args['name']) . "'>"
                . "<option value='0'" . $selectedNo . ">Nein</option>"
                . "<option value='1'" . $selectedYes . ">Ja</option>"
             . "</select>"
             . "<p>" . wp_kses_post($descLong) . "</p>"
        . "</div>";
    }
}
