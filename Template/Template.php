<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Template
{

    public $action = null;
    protected $parent = null;
    protected $level = 0;
    protected $admin_disabled = false;
    
    public $context;

    public function __construct($action = null, $data = array())
    {
        if ($data instanceof Context) {
            $context = $data;
        } else {
            $context = new Context();
            if (count($data) > 0) {
                $context->push($data);
            }
        }
        $this->context = $context;
        $this->action = $action;
        //fx::trigger('initTemplate', array('template' => $this));
    }

    public function setParent($parent_template)
    {
        $this->parent = $parent_template;
        $this->level = $parent_template->getLevel() + 1;
        return $this;
    }

    public function isAdmin($set = null)
    {
        if ($set === null) {
            return !$this->admin_disabled && fx::isAdmin();
        }
        $this->admin_disabled = !$set;
        return $this;
    }

    public function getLevel()
    {
        return $this->level;
    }
    
    protected $mode_stack = array();

    public function pushMode($mode, $value)
    {
        if (!isset($this->mode_stack[$mode])) {
            $this->mode_stack[$mode] = array();
        }
        $this->mode_stack[$mode] [] = $value;
    }

    public function popMode($mode)
    {
        if (isset($this->mode_stack[$mode])) {
            array_pop($this->mode_stack[$mode]);
        }
    }

    public function getMode($mode)
    {
        if (isset($this->mode_stack[$mode])) {
            return end($this->mode_stack[$mode]);
        }
        if ($this->parent) {
            return $this->parent->getMode($mode);
        }
    }

    protected function printVar($val, $meta = null)
    {
        $tf = null;
        if ($meta && isset($meta['var_type'])) {
            $tf = new Field($val, $meta);
        }
        $res = $tf ? $tf : $val;
        return (string)$res;
    }

    public function getHelp()
    {
        //ini_set('memory_limit', '1G');
        return $this->context->getHelp();
    }
    
    protected function getVarMeta($var_name = null, $source = null)
    {
        return $this->context->getVarMeta($var_name, $source);
    }
    
    public function v($v = null, $l = null) {
        return $this->context->get($v, $l);
    }

    protected $is_wrapper = false;

    public function isWrapper($set = null)
    {
        if (func_num_args() === 0) {
            return $this->is_wrapper ? true : ($this->parent ? $this->parent->isWrapper() : false);
        }
        $this->is_wrapper = (bool)$set;
    }

    protected $context_stack = array();

    public static $v_count = 0;

    public static function beautifyHtml($html)
    {
        $level = 0;
        $html = preg_replace_callback(
            '~\s*?<(/?)([a-z0-9]+)[^>]*?(/?)>\s*?~',
            function ($matches) use (&$level) {
                $is_closing = $matches[1] == '/';
                $is_single = in_array(strtolower($matches[2]), array('img', 'br', 'link')) || $matches[3] == '/';

                if ($is_closing) {
                    $level = $level == 0 ? $level : $level - 1;
                }
                $tag = trim($matches[0]);
                $tag = "\n" . str_repeat(" ", $level * 4) . $tag;

                if (!$is_closing && !$is_single) {
                    $level++;
                }
                return $tag;
            },
            $html
        );
        return $html;
    }

    protected function getTemplateSign()
    {
        $template_name = get_class($this);
        return $template_name . ':' . $this->action;
    }

    public static $area_replacements = array();

    /*
     * @param $mode - marker | data | both
     */
    public static function renderArea($area, $context, $mode = 'both')
    {
        $is_admin = fx::isAdmin();
        if ($mode != 'marker') {
            fx::trigger('render_area', array('area' => $area));
            if ($is_admin && $context->get('_idle')) {
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
            $area_blocks = fx::page()->getAreaInfoblocks($area['id']);
            $pos = 1;
            foreach ($area_blocks as $ib) {
                $ib->addParams(array('infoblock_area_position' => $pos));
                $result = $ib->render();
                echo $result;
                $pos++;
            }
        }
        if ($is_admin) {
            $area_result = ob_get_clean();
            self::$area_replacements [] = array($area, $area_result);
            $marker = '###fxa' . (count(self::$area_replacements) - 1);
            if ($mode != 'both') {
                $marker .= '|' . $mode;
            }
            $marker .= '###';
            echo $marker;
        }
    }

    public function getAreas()
    {
        $areas = array();
        ob_start();
        fx::listen('render_area.get_areas', function ($e) use (&$areas) {
            $areas[$e->area['id']] = $e->area;
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
    
    protected $forced_method = null;
    
    public function forceMethod($method) {
        $this->forced_method = $method;
    }

    public static function getActionMethod($action, $context, $tags = null, $with_priority = false)
    {
        if (!isset(static::$action_map[$action])) {
            return false;
        }
        $method = static::$action_map[$action];
        if (is_string($method)) {
            return !$with_priority ? $method : array($method, 0.5);
        }
        //$res = call_user_func( array($this, 'solve_'.$action), $context, $tags);
        $res = call_user_func(get_called_class().'::solve_'.$action, $context, $tags);
        return !$with_priority ? $res[0] : $res;
    }


    public function render($data = array())
    {
        if ($this->level > 10) {
            return '<div class="fx_template_error">bad recursion?</div>';
        }
        if (count($data) > 0) {
            $this->context->push($data);
        }
        
        ob_start();
        if (!is_null($this->forced_method)){
            $method = $this->forced_method;
        } else {
            $method = self::getActionMethod($this->action, $this->context);
            if (!$method) {
                throw new \Exception('No template: ' . get_class($this) . '.' . $this->action);
            }
        }
        try {
            $this->$method($this->context);
        } catch (\Exception $e) {
            fx::log(
                'template exception', 
                $e
                //debug_backtrace(null,6)
            );
        }
        $result = ob_get_clean();

        
        if (fx::isAdmin()) {
            if ($this->context->get('_idle')) {
                return $result;
            }
            if (!$this->parent) {
                self::$count_replaces++;
                $result = Template::replaceAreas($result);
                $result = Field::replaceFields($result);
            }
        }
        return $result;
    }

    public static $count_replaces = 0;

    // is populated when compiling
    protected static $templates = array();


    public function getTemplateVariants()
    {
        return static::$templates;
    }

    public function getInfo()
    {
        if (!$this->action) {
            throw new \Exception('Specify template action/variant before getting info');
        }
        foreach (static::$templates as $tpl) {
            if ($tpl['id'] == $this->action) {
                return $tpl;
            }
        }
    }

    public static function replaceAreas($html)
    {
        if (!strpos($html, '###fxa')) {
            return $html;
        }
        $html = self::replaceAreasWrappedByTag($html);
        $html = self::replaceAreasInText($html);
        return $html;
    }

    protected static function replaceAreasWrappedByTag($html)
    {
        //$html = preg_replace("~<!--.*?-->~s", '', $html);
        $html = preg_replace_callback(
        /*"~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)###\s*(</[a-z0-9_-]+>)~s",*/
            "~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)\|?(.*?)###~s",
            function ($matches) use ($html) {
                $replacement = Template::$area_replacements[$matches[2]];
                $mode = $matches[3];
                if ($mode == 'data') {
                    Template::$area_replacements[$matches[2]] = null;
                    $res = $matches[1] . $replacement[1];
                    if (!$replacement[1]) {
                        $res .= '<span class="fx_area_marker"></span>';
                    }
                    return $res;
                }

                $tag = HtmlToken::createStandalone($matches[1]);
                $tag->addMeta(array(
                    'class'        => 'fx_area',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();

                if ($mode == 'marker') {
                    return $tag;
                }

                Template::$area_replacements[$matches[2]] = null;
                return $tag . $replacement[1] . $matches[3];
            },
            $html
        );
        return $html;
    }

    protected static function replaceAreasInText($html)
    {
        $html = preg_replace_callback(
            "~###fxa(\d+)\|?(.*?)###~",
            function ($matches) {
                $mode = $matches[2];
                $replacement = Template::$area_replacements[$matches[1]];
                if ($mode == 'data') {
                    if (!$replacement[1]) {
                        return '<span class="fx_area_marker"></span>';
                    }
                    return $replacement[1];
                }
                $tag_name = 'div';
                $tag = HtmlToken::createStandalone('<' . $tag_name . '>');
                $tag->addMeta(array(
                    'class'        => 'fx_area fx_wrapper',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();
                Template::$area_replacements[$matches[1]] = null;
                return $tag . $replacement[1] . '</' . $tag_name . '>';
            },
            $html
        );
        return $html;
    }
}