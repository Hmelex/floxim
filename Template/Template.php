<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System;
use Floxim\Floxim\Helper\Form;
use Floxim\Floxim\System\Fx as fx;

class Template {
    
    public $action = null;
    protected $_parent = null;
    protected $_inherit_context = false;
    protected $_level = 0;
    protected $_admin_disabled = false;
    
    public function __construct($action, $data = array()) {
        if (count($data) > 0) {
            $this->push_context($data);
        }
        $this->action = $action;
    }
    
    public function set_parent($parent_template, $inherit = false) {
        $this->_parent = $parent_template;
        $this->_inherit_context = $inherit;
        $this->_level = $parent_template->get_level() + 1;
        return $this;
    }
    
    public function is_admin($set = null) {
        if ($set === null) {
            return !$this->_admin_disabled && fx::is_admin();
        }
        $this->_admin_disabled = ! $set;
        return $this;
    }
    
    public function get_level() {
        return $this->_level;
    }
    
    protected $context_stack_meta = array();
    
    public function push_context($data = array(), $meta = array()) {
        $this->context_stack []= $data;
        $meta = array_merge(array(
            'transparent' => false,
            'autopop' => false
        ), $meta);
        $this->context_stack_meta[] = $meta;
    }
    
    public function pop_context() {
        array_pop($this->context_stack);
        $meta = array_pop($this->context_stack_meta);
        if ($meta['autopop']) {
            array_pop($this->context_stack);
            array_pop($this->context_stack_meta);
        }
    }
    
    protected $mode_stack = array();
    
    public function push_mode($mode, $value) {
        if (!isset($this->mode_stack[$mode])) {
            $this->mode_stack[$mode] = array();
        }
        $this->mode_stack[$mode] []= $value;
    }
    
    public function pop_mode($mode) {
        if (isset($this->mode_stack[$mode])) {
            array_pop($this->mode_stack[$mode]);
        }
    }
    
    public function get_mode($mode) {
        if (isset($this->mode_stack[$mode])) {
            return end($this->mode_stack[$mode]);
        }
        if ($this->_parent) {
            return $this->_parent->get_mode($mode);
        }
    }

    public function set_var($var, $val) {
        $stack_count = count($this->context_stack);
        if ($stack_count == 0) {
            $this->push_context(array(), array('transparent' => true));
        }
        if (!is_array($this->context_stack[$stack_count-1])) {
            $this->push_context(array(), array('transparent' => true, 'autopop' => true));
            $stack_count++;
        }
        $this->context_stack[$stack_count-1][$var] = $val;
    }
    
    protected function print_var($val, $meta = null) {
        $tf = null;
        if ($meta && isset($meta['var_type'])) {
            $tf = new Field($val, $meta);
        }
        $res = $tf ? $tf : $val;
        return (string) $res;
    }
    
    public function get_help() {
        ini_set('memory_limit', '1G');
        ob_start();
        ?>
        <div class="fx_help">
            <a class="fx_help_expander">?</a>
            <div class="fx_help_data" style="display:none;">
            <?php
            $this->print_stack_help();
            ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function print_stack_help() {
        $context_stack = array_reverse($this->context_stack);
        echo "<div class='fx_help_template_title'>".$this->_get_template_sign()."</div>";
        foreach ($context_stack as $level => $stack) {
            echo $this->get_item_help($stack, 0);
        }
        if ($this->_parent && $this->_inherit_context){
            echo "<hr />";
            $this->_parent->print_stack_help();
        }
    }
    
    public function get_item_help($item, $level = 0, $c_path = array()) {
        $c_path []= $item;
        $item_type = is_array($item) ? 'Array' : get_class($item);
        if ($item instanceof System\Entity || $item instanceof Form\Field\Field || $item instanceof Form\Form) {
            $item = $item->get();
        }
        ob_start();
        if ($level === 0) {
            ?>
            <div class="fx_item_help_block">
            <table>
                <tr class="header"><td colspan="2"><?= $item_type ?></td></tr>
                <tr class="header">
                    <td>Prop</td>
                    <td class="value_cell">Value</td>
                </tr>
            <?php
        }
        foreach ($item as $prop => $value) {
            $is_complex = is_object($value) || is_array($value);
            $is_recursion = false;
            if ($is_complex) {
                foreach ($c_path as $c_path_item) {
                    if ($value === $c_path_item) {
                        $is_recursion = true;
                        break;
                    }
                }
            }
            ?>
            <tr class="help_level_<?=$level?>" 
                <?php if ($level > 0) { echo ' style="display:none;" ';}?>>
                <td style="padding-left:<?=(2+10*$level)?>px !important;" class="prop_cell">
                    <?php 
                    if ($is_complex) {
                        ?><a class="level_expander">
                            <b><?=$prop?></b> 
                            <span class="item_type"><?= is_array($value) ? 'Array' : get_class($value)?></span>
                        </a>
                        <?php
                    } else {
                        echo $prop;
                    }
                    ?>
                </td>
                <td class="value_cell">
                    <?php
                    if (!$is_complex) {
                        echo htmlspecialchars($value);
                    } elseif ($is_recursion) {
                        ?><span class="fx_help_recursion">* recursion *</span><?php
                    }
                    ?>
                </td>
            </tr>
            <?php
            if ($is_complex && !$is_recursion) {
                if (! ($value instanceof Loop)) {
                    echo $this->get_item_help($value, $level+1, $c_path);
                }
            }
        }
        if ($level === 0) {
            ?></table>
            </div>
            <?php
        }
        return ob_get_clean();
    }
    
    protected function get_var_meta($var_name = null, $source = null) {
        if ($var_name === null) {
            return array();
        }
        if ($source && $source instanceof Entity) {
            $meta = $source->get_field_meta($var_name);
            return is_array($meta) ? $meta : array();
        }
        for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
            if ( !($this->context_stack[$i] instanceof Entity) ) {
                continue;
            }
            if ( ($meta = $this->context_stack[$i]->get_field_meta($var_name))) {
                return $meta;
            }
        }
        if ($this->_parent && $this->_inherit_context) {
            return $this->_parent->get_var_meta($var_name);
        }
        return array();
    }
    
    protected $is_wrapper = false;
    public function is_wrapper($set = null){
        if (func_num_args() == 0) {
            return $this->is_wrapper ? true : ($this->_parent ? $this->_parent->is_wrapper() : false);
        }
        $this->is_wrapper = (bool) $set;
    }
    
    protected $context_stack = array();
    
    
    public static $v_count = 0;
    public function v($name = null, $context_offset = null) {
        $need_local = false;
        if ($context_offset === 'local') {
            $need_local = true;
            $context_offset = null;
        }
        // neither var name nor context offset - return current context
        if (!$name && !$context_offset ) {
            for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
                $c_meta = $this->context_stack_meta[$i];
                if (!$c_meta['transparent']) {
                    return $this->context_stack[$i];
                }
            }
            return end($this->context_stack);
        }
        
        if (!is_null($context_offset)) {
            $context_position = -1;
            for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
                $cc = $this->context_stack[$i];
                $c_meta = $this->context_stack_meta[$i];
                //if ( ! $cc instanceof fx_template_loop) {
                if (!$c_meta['transparent']) {
                    $context_position++;
                }
                if ($context_position == $context_offset) {
                    if (!$name) {
                        return $cc;
                    }
                    
                    if (is_array($cc)) {
                        if (array_key_exists($name, $cc)) {
                            return $cc[$name];
                        }
                    } elseif ($cc instanceof \ArrayAccess) {
                        if (isset($cc[$name])) {
                            return $cc[$name];
                        }
                    } elseif (is_object($cc) && isset($cc->$name)) {
                        return $cc->$name;
                    }
                    continue;
                } 
                if ($context_position > $context_offset) {
                    return null;
                }
            }
            if ($this->_parent) {
                return $this->_parent->v($name, $context_offset - $context_position - 1);
            }
            return null;
        }
        
        for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
            $cc = $this->context_stack[$i];
            if (is_array($cc)) {
                if (array_key_exists($name, $cc)) {
                    return $cc[$name];
                }
            } elseif ($cc instanceof \ArrayAccess) {
                if (isset($cc[$name])) {
                    return $cc[$name];
                }
            } elseif (is_object($cc) && isset($cc->$name)) {
                return $cc->$name;
            }
        }
        if ($this->_parent && $this->_inherit_context && !$need_local) {
            return $this->_parent->v($name);
        }
        return null;
    }
    
    public static function beautify_html($html) {
        $level = 0;
        $html = preg_replace_callback(
            '~\s*?<(/?)([a-z0-9]+)[^>]*?(/?)>\s*?~', 
            function($matches) use (&$level) {
                $is_closing = $matches[1] == '/';
                $is_single = in_array(strtolower($matches[2]), array('img', 'br', 'link')) || $matches[3] == '/';
                    
                if ($is_closing) {
                    $level = $level == 0 ? $level : $level - 1;
                }
                $tag = trim($matches[0]);
                $tag = "\n".str_repeat(" ", $level*4).$tag;
                
                if (!$is_closing && !$is_single) {
                    $level++;
                }
                return $tag;
            }, 
            $html
        );
        return $html;
    }
    
    protected function _get_template_sign() {
        // todo: psr0 need fix
        $template_name = preg_replace("~^fx_template_~", '', get_class($this));
        return $template_name.'.'.$this->action;
    }
    
    public static $area_replacements = array();

    /*
     * @param $mode - marker | data | both
     */
    public function render_area($area, $mode = 'both') {
    	$is_admin =  fx::is_admin();
        if ($mode != 'marker') {
            fx::trigger('render_area', array('area' => $area));
            if ($this->v('_idle')) {
                return;
            }
        }
        
        
        if ($is_admin) {
            ob_start();
        }
        if (
            $mode != 'marker' && 
            (!isset($area['render']) || $area['render'] != 'manual')
        ) {
            $area_blocks = fx::page()->get_area_infoblocks($area['id']);
            $pos = 1;
            foreach ($area_blocks as $ib) {
                $ib->add_params(array('infoblock_area_position' => $pos));
                $result = $ib->render();
                echo $result;
                $pos++;
            }
        }
        if ($is_admin) {
            $area_result = ob_get_clean();
            self::$area_replacements []= array($area, $area_result);
            $marker = '###fxa'.(count(self::$area_replacements)-1);
            if ($mode != 'both') {
                $marker .= '|'.$mode;
            }
            $marker .= '###';
            echo $marker;
        }
    }

    public function get_areas() {
        $areas = array();
        ob_start();
        fx::listen('render_area.get_areas', function($e) use (&$areas) {
            $areas[$e->area['id']]= $e->area;
        });
        $this->render(array('_idle' => true));
        fx::unlisten('render_area.get_areas');
        // hm, since IB render res is cached, we can not just remove added files
        // because they won't be added again
        // may be we should switch off caching for idle mode
        //fx::page()->clear_files();
        ob_get_clean();
        return $areas;
    }
    
    public function has_action($action = null) {
        if (is_null($action)) {
            $action = $this->action;
        }
        return method_exists($this, self::_get_action_method($action));
    }
    
    protected static function _get_action_method($action) {
        return 'tpl_'.$action;
    }


    public function render($data = array()) {
        if ($this->_level > 10) {
            return '<div class="fx_template_error">bad recursion?</div>';
        }
        if (count($data) > 0) {
            $this->push_context($data);
        }
        ob_start();
        $method = self::_get_action_method($this->action);
        if ($this->has_action()) {
            try {
                $this->$method();
            } catch (\Exception $e) {
                fx::log('template exception', $e);
            }
        } else {
            say(debug_backtrace());die();
            fx::debug('No template: '.get_class($this).'.'.$this->action);
        }
        $result = ob_get_clean();
        
        if ($this->v('_idle')) {
            return $result;
        }
        if (fx::is_admin() && !$this->_parent) {
            self::$count_replaces++;
            $result = Template::replace_areas($result);
            $result = Field::replace_fields($result);
        }
        return $result;
    }
    public static $count_replaces = 0;
    
    // is populated when compiling
    protected $_templates = array();


    public function get_template_variants() {
        return $this->_templates;
    }
    
    public function get_info() {
        if (!$this->action) {
            throw new \Exception('Specify template action/variant before getting info');
        }
        foreach ($this->_templates as $tpl) {
            if ($tpl['id'] == $this->action) {
                return $tpl;
            }
        }
    }
    
    public static function replace_areas($html) {
        if (!strpos($html, '###fxa')) {
            return $html;
        }
        $html = self::_replace_areas_wrapped_by_tag($html);
        $html = self::_replace_areas_in_text($html);
        return $html;
    }
    
    protected static function _replace_areas_wrapped_by_tag($html) {
    	//$html = preg_replace("~<!--.*?-->~s", '', $html);
    	$html = preg_replace_callback(
            /*"~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)###\s*(</[a-z0-9_-]+>)~s",*/
            "~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)\|?(.*?)###~s",
            function($matches) use ($html) {
                $replacement = Template::$area_replacements[$matches[2]];
                $mode = $matches[3];
                if ($mode == 'data') {
                    Template::$area_replacements[$matches[2]] = null;
                    $res = $matches[1].$replacement[1];
                    if (!$replacement[1]) {
                        $res .= '<span class="fx_area_marker"></span>';
                    }
                    return $res;
                }
                
                $tag = HtmlToken::create_standalone($matches[1]);
                $tag->add_meta(array(
                    'class' => 'fx_area',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();
                
                if ($mode == 'marker') {
                    return $tag;
                }
                
                Template::$area_replacements[$matches[2]] = null;
                return $tag.$replacement[1].$matches[3]; 
            },
            $html
        );
        return $html;
    }
    
    protected static function _replace_areas_in_text($html) {
    	$html = preg_replace_callback(
            "~###fxa(\d+)\|?(.*?)###~",
            function($matches) {
                $mode = $matches[2];
                $replacement = Template::$area_replacements[$matches[1]];
                if ($mode == 'data') {
                    if (!$replacement[1]) {
                        return '<span class="fx_area_marker"></span>';
                    }
                    return $replacement[1];
                }
                $tag_name = 'div';
                $tag = HtmlToken::create_standalone('<'.$tag_name.'>');
                $tag->add_meta(array(
                    'class' => 'fx_area fx_wrapper',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();
                Template::$area_replacements[$matches[1]] = null;
                return $tag.$replacement[1].'</'.$tag_name.'>';
            },
            $html
        );
        return $html;
    }
}