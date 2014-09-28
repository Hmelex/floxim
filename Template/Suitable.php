<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System;

class Suitable {
    
    public static function unsuit($site_id = null, $layout_id = null) {
        if (is_null($site_id)) {
            $site_id = fx::env('site')->get('id');
        }
        if (is_null($layout_id)) {
            $layout_id = fx::data('site', $site_id)->get('layout_id');
        }
        $infoblocks_query = fx::data('infoblock')
            ->where('site_id', $site_id)
            ->onlyWith(
                'visuals', 
                function($q) use ($layout_id) {
                    $q->where('layout_id', $layout_id);
                }
            );
        
        
        $infoblocks = $infoblocks_query->all();
        $infoblocks->apply(function($ib) {
            $visual = $ib['visuals']->first();
            if ($visual) {
                $visual->delete();
            }
        });
    }
    
    public function suit(System\Collection $infoblocks, $layout_id) {
        $layout = fx::data('layout', $layout_id);
        $layout_ib = null;
        $stub_ibs = new System\Collection();
        // Collect all Infoblox without the visual part
        // Find the InfoBlock-layout
        foreach ($infoblocks as $ib) {
            if ($ib->getVisual()->get('is_stub')) {
                $stub_ibs[]= $ib;
            }
            if ($ib->isLayout()) {
                $layout_ib = $ib;
            }
        }
        $layout_rate = array();
        $all_visual = fx::data('infoblock_visual')->getForInfoblocks($stub_ibs, false);
        
        foreach ($all_visual as $c_vis) {
            $c_layout_id = $c_vis['layout_id'];
            $infoblocks->
                    findOne('id', $c_vis['infoblock_id'])->
                    setVisual($c_vis, $c_layout_id);
            if (!isset($layout_rate[$c_layout_id])) {
                $layout_rate[$c_layout_id] = 0;
            }
            $layout_rate[$c_layout_id]++;
        }
        
        $source_layout_id = $c_layout_id;
        
        if (!$layout_ib) {
            $layout_ib = fx::env('page')->getLayoutInfoblock();
        }
        
        if ($layout_ib->getVisual()->get('is_stub')) {
            $this->adjustLayoutVisual($layout_ib, $layout_id, $source_layout_id);
        }
        
        $layout_visual = $layout_ib->getVisual();
        $area_map = $layout_visual['area_map'];
        
        $layout_template_name = $layout_ib->getPropInherited('visual.template');
        $c_areas = fx::template($layout_template_name)->getAreas();
        
        foreach ($infoblocks as $ib) {
            $ib_visual = $ib->getVisual($layout_id);
            if (!$ib_visual['is_stub'] ) {
                continue;
            }
            $old_area = $ib->getPropInherited('visual.area', $source_layout_id);
            if ($old_area && isset($area_map[$old_area])) {
                $ib_visual['area'] = $area_map[$old_area];
                $ib_visual['priority'] = $ib->getPropInherited('visual.priority', $source_layout_id);
            }
            $ib_controller = fx::controller(
                    $ib->getPropInherited('controller'),
                    $ib->getPropInherited('params'),
                    $ib->getPropInherited('action')
            );
            $controller_templates = $ib_controller->getAvailableTemplates($layout['keyword']);
            $old_template = $ib->getPropInherited('visual.template', $source_layout_id);
            $used_template_props = null;
            foreach ($controller_templates as $c_tpl) {
                if ($c_tpl['full_id'] == $old_template) {
                    $ib_visual['template'] = $c_tpl['full_id'];
                    $used_template_props = $c_tpl;
                    break;
                }
            }
            if (!isset($ib_visual['template'])) {
                $ib_visual['template'] = $controller_templates[0]['full_id'];
                $used_template_props = $controller_templates[0];
            }
            
            if (!$ib_visual['area']) {
                $block_size = self::getSize( $used_template_props['size']);
                $c_area = null;
                $c_area_count = 0;
                foreach ($c_areas as $ca) {
                    $area_size = self::getSize($ca['size']);
                    $area_count = self::checkSizes($block_size, $area_size);
                    if ($area_count >= $c_area_count) {
                        $c_area_count = $area_count;
                        $c_area = $ca['id'];
                    }
                }
                $ib_visual['area'] = $c_area;
            }
            
            unset($ib_visual['is_stub']);
            $ib_visual->save();
        }
    }
    
    protected function adjustLayoutVisual($layout_ib, $layout_id, $source_layout_id) {
        $layout = fx::data('layout', $layout_id);
        
        
        $layout_tpl = fx::template('layout_'.$layout['keyword']);
        $template_variants = $layout_tpl->getTemplateVariants();
        
        if ($source_layout_id) {
            $source_template = $layout_ib->getPropInherited('visual.template', $source_layout_id);
            $old_areas = fx::template($source_template)->getAreas();
            $c_relevance = 0;
            $c_variant = null;
            foreach ($template_variants as $tplv) {
                if ($tplv['of'] !== 'layout.show' && $tplv['id'] !== '_layout_body') {
                    continue;
                }
                $test_layout_tpl = fx::template($tplv['full_id']);
                $tplv['real_areas'] = $test_layout_tpl->getAreas();
                $map = $this->mapAreas($old_areas, $tplv['real_areas']);
                if ( !$map ) {
                    continue;
                }
                if ($map['relevance'] > $c_relevance) {
                    $c_relevance = $map['relevance'];
                    $c_variant = $map + array(
                        'full_id' => $tplv['full_id'],
                        'areas' => $tplv['real_areas']
                    );
                }
            }
        }
        
        if (!$source_layout_id || !$c_variant) {
            foreach ($template_variants as $tplv) {
                if ($tplv['of'] == 'layout.show') {
                    $c_variant = $tplv;
                    break;
                }
            }
            if (!$c_variant) {
                $c_variant = array('full_id' => 'layout_'.$layout['keyword'].'._layout_body');
            }
        }
        
        $layout_vis = $layout_ib->getVisual();
        $layout_vis['template'] = $c_variant['full_id'];
        if ($c_variant['areas']) {
            $layout_vis['areas'] = $c_variant['areas'];
            $layout_vis['area_map'] = $c_variant['map'];
        }
        unset($layout_vis['is_stub']);
        $layout_vis->save();
    }
    
    /*
     * Compares two sets of fields
     * Considers the relevance of size, title and employment
     * Returns an array with the keys in the map and relevance
     */
    protected function mapAreas($old_set, $new_set) {
        $total_relevance = 0;
        foreach ($old_set as &$old_area) {
            $old_size = $this->_getSize($old_area);
            $c_match = false;
            $c_match_index = 1;
            foreach ($new_set as $new_area_id => $new_area) {
                $new_size = $this->_getSize($new_area);
                $area_match = 0;
                
                // if one of the areas arbitrary width - existent, 1
                if ($new_size['width'] == 'any' || $old_size['width'] == 'any') {
                    $area_match += 1;
                } 
                // if the width is the same as - good, 2
                elseif ($new_size['width'] == $old_size['width']) {
                    $area_match += 2;
                } 
                // if no width is matched, no good
                else {
                    continue;
                }
                
                // if one of the areas of arbitrary height - existent, 1
                if ($new_size['height'] == 'any' || $old_size['height'] == 'any') {
                    $area_match += 1;
                } 
                // if the height voityla - good, 2
                elseif ($new_size['height'] == $old_size['height']) {
                    $area_match += 2;
                } 
                // new area - high, old - low, you can replace, 1
                elseif ($new_size['height'] == 'high') {
                    $area_match += 1;
                } 
                // a new low, old - high, no good
                else {
                    continue;
                }
                // if the names coincide areas: 2
                if ($old_area['id'] == $new_area['id']) {
                    $area_match += 2;
                }
                
                // if the field is already another: -2
                if ($new_area['used']) {
                    $area_match -= 2;
                }
                
                // if the current index is larger than the previous - remember
                if ($area_match > $c_match_index) {
                    $c_match = $new_area_id;
                    $c_match_index = $area_match;
                }
            }
            if ($c_match_index == 0) {
                return false;
            }
            $old_area['analog'] = $c_match;
            $old_area['relevance'] = $c_match_index;
            $new_set[$c_match]['used'] = true;
            $total_relevance += $c_match_index;
        }
        // for each unused lower the score 2
        foreach ($new_set as $new_area) {
            if (!isset($new_area['used'])) {
                $total_relevance -= 2;
            }
        }
        $map = array();
        foreach ($old_set as $old_set_item) {
            $map[$old_set_item['id']] = $old_set_item['analog'];
        }
        $res = array('relevance' => $total_relevance, 'map' => $map);
        return $res;
    }
    
    public static function getSize($size) {
        $res = array('width' => 'any', 'height' => 'any');
        if (empty($size)) {
            return $res;
        }
        $width = null;
        $height = null;
        if (preg_match('~wide|narrow~', $size, $width)) {
            $res['width'] = $width[0];
        }
        if (preg_match('~high|low~', $size, $height)) {
            $res['height'] = $height[0];
        }
        return $res;
    }
    
    public static function checkSizes($block, $area) {
        if ($area['width'] === 'narrow' && $block['width'] ==='wide') {
            return 0;
        }
        if ($area['height'] === 'low' && $block['height'] === 'high') {
            return 0;
        }
        $n = 1;
        if ($block['height'] !== 'any' && $area['height'] === $block['height']) {
            $n++;
        } elseif ($block['height'] === 'any' && $area['height'] === 'high') {
            $n += 0.5;
        }
        if ($block['width'] !== 'any' && $area['width'] === $block['width']) {
            $n++;
        } elseif ($block['width'] === 'any' && $area['width'] === 'wide') {
            $n++;
        }
        return $n;
    }
    
    protected function _getSize($block) {
        $res = array('width' => 'any', 'height' => 'any');
        if (!isset($block['size'])) {
            return $res;
        }
        if (preg_match('~wide|narrow~', $block['size'], $width)) {
            $res['width'] = $width[0];
        }
        if (preg_match('~high|low~', $block['size'], $height)) {
            $res['height'] = $height[0];
        }
        return $res;
    }
    
    // suit props that should contain templates
    protected static $tpl_suit_props = array('force_wrapper', 'force_template','default_wrapper');
    
    public static function parseAreaSuitProp($suit) {
        $res = array();
        $suit = explode(";", $suit);
        foreach ($suit as $v) {
            $v = trim($v);
            if (empty($v)) {
                continue;
            }
            $v = explode(':', $v);
            if (count($v) == 1) {
                $res[trim($v[0])] = true;
            } else {
                $p = trim($v[0]);
                if (empty($p)) {
                    continue;
                }
                $res[$p] = array();
                foreach (explode(",", $v[1]) as $rv) {
                    $res[$p][]= trim($rv);
                }
                if (count($res[$p]) == 1) {
                    $res[$p] = $res[$p][0];
                }
            }
        }
        foreach (self::$tpl_suit_props as $prop) {
            if (!isset($res[$prop])) {
                $res[$prop] = false;
            } elseif (!is_array($res[$prop])) {
                $res[$prop] = array($res[$prop]);
            }
        }
        return $res;
    }
    
    public static function compileAreaSuitProp($suit, $local_templates, $set_name) {
        $suit = self::parseAreaSuitProp($suit);
        foreach (self::$tpl_suit_props as $prop) {
            if (!$suit[$prop]) {
                continue;
            }
            $local_key = array_keys($suit[$prop], 'local');
            if ($local_key) {
                $suit[$prop] = array_merge($suit[$prop], $local_templates);
                unset($suit[$local_key[0]]);
            }
            foreach ($suit[$prop] as &$tpl_name) {
                $tpl_name = trim($tpl_name, '.');
                if (!strstr($tpl_name, '.')) {
                    $tpl_name = $set_name.'.'.$tpl_name;
                }
            }
        }
        $res_suit = '';
        foreach ($suit as $p => $v) {
            if (is_bool($v) && !$v) {
                continue;
            }
            $res_suit .= $p;
            if (!is_bool($v)) {
                $res_suit .= ':';
                $res_suit .= is_array($v) ? join(',', $v) : $v;
            }
            $res_suit .= '; ';
        }
        return $res_suit;
    }
}
