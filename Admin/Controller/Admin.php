<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System;
use Floxim\Floxim\Admin as FxAdmin;
use Floxim\Floxim\System\Fx as fx;

class Admin extends System\Controller {

    /** @var string the default action for the controller to return the html markup */
    protected $action = 'admin_office';
    
    /** @var bool the process() method should return the result? */
    protected $process_do_return = false;
    
    protected $essence_type;
    protected $save_history = true;

    /** @var fx_admin_response */
    protected $response;

    /** @var fx_admin_ui */
    protected $ui;

    public function __construct($input = array(), $action = null, $do_return = false) {
        parent::__construct($input, $action);
        $this->essence_type = strtolower(fx::getClassNameFromNamespaceFull(get_class($this)));
        $this->ui = new FxAdmin\Ui;
        
        $this->process_do_return = isset($input['do_return']) ? $input['do_return'] : $do_return;
    }

    public function process() {

        $input = $this->input;
        $action = $this->action;
        
        if (!fx::is_admin()) {
            $result = $this->admin_office($input);
            if (is_string($result)) {
                return $result;
            }    
        }
        
        if (!$action || !is_callable(array($this, $action))) {
            die("Error! Class:".get_class($this).", action:".htmlspecialchars($action));
        }
        
        $this->response = new FxAdmin\Response($input);
        $result = $this->$action($input);
        if (is_string($result)) {
            return $result;
        }

        if ($input['posting']) {
            if (!$result['text']) {
                $result['text'] = $this->get_status_text();
            }
        }

        if ($this->response) {
            $result = array_merge(
                $result ? $result : array(), 
                $this->response->to_array()
            );
        }
        // force numeric indexes for fields to preserve order
        if (isset($result['fields']) && is_array($result['fields'])) {
            $result['fields'] = array_values($result['fields']);
        }
        
        if ($this->process_do_return) {
            return $result;
        }
        return json_encode($result);
    }

    protected function get_status_text() {
        return fx::alang('Saved','system');
    }

    protected function get_active_tab() {
        return $this->input['params'][1];
    }
    
    public static function add_admin_files() {
        $path_floxim = fx::path('floxim');
        $js_files = array(
            FX_JQUERY_PATH,
            $path_floxim.'/admin/js/fxj.js',
            $path_floxim.'/admin/js/fx.js',
            $path_floxim.'/admin/js/js-dictionary-'.fx::config()->ADMIN_LANG.'.js',
            FX_JQUERY_UI_PATH,
            $path_floxim.'/lib/js/jquery.nestedSortable.js',
            $path_floxim.'/lib/js/jquery.ba-hashchange.min.js',
            $path_floxim.'/lib/js/jquery.json-2.3.js',
            $path_floxim.'/lib/js/ajaxfileupload.js',
            $path_floxim.'/admin/js-templates/jstx.js',
            'http://'.getenv("HTTP_HOST").'/vendor/Floxim/Floxim/admin/js-templates/compile.php',
            $path_floxim.'/admin/js/lib.js',
            $path_floxim.'/admin/js/front.js',
            $path_floxim.'/admin/js/buttons.js',
            $path_floxim.'/admin/js/form.js',
            $path_floxim.'/admin/js/patch.js',
            $path_floxim.'/admin/js/debug.js',
            $path_floxim.'/admin/js/livesearch.js',
            $path_floxim.'/admin/js/fields.js',
            $path_floxim.'/admin/js/edit-in-place.js',
            $path_floxim.'/admin/js/panel.js',
            $path_floxim.'/admin/js/popup.js',
            $path_floxim.'/admin/js/admin.js',
            $path_floxim.'/admin/js/nav.js',
            $path_floxim.'/lib/editors/redactor/redactor.js',
            $path_floxim.'/lib/editors/redactor/fontcolor.js',
            $path_floxim.'/lib/codemirror/codemirror.all.min.js',
            $path_floxim.'/lib/js/jquery.form.js',
            $path_floxim.'/lib/js/jquery.cookie.js',
            $path_floxim.'/lib/js/jquery.ba-resize.min.js',
            $path_floxim.'/lib/js/jquery.scrollTo.js',
            $path_floxim.'/admin/js/map.js',
            $path_floxim.'/admin/js/infoblock.js' // infoblock form overrides
        );
        $page = fx::page();
        
        
        
        $page->add_js_bundle($js_files, array('name' => 'fx_admin'));
        // todo: need fix path for css - now used server path
        $page->add_css_bundle(array(
            $path_floxim.'/lib/editors/redactor/redactor.css',
        ));
        
        $page->add_css_bundle(array(
            $path_floxim.'/admin/style/main.less',
            $path_floxim.'/admin/style/forms.less',
            $path_floxim.'/admin/style/front.less',
            $path_floxim.'/admin/style/debug.less',
            $path_floxim.'/lib/codemirror/codemirror.css'
        ), array('name' => 'admin_less'));
    }
    
    /**
     * @return string
     */
    public function admin_office()
    {   
        self::add_admin_files();
        
        if (fx::is_admin()) {
            $res = fx::template('helper_admin:back_office')->render();
            $js_config = new FxAdmin\Configjs();
            fx::page()->add_js_text("\$fx.init(".$js_config->get_config().");");
        } else {
            $auth_form = fx::controller('user:auth_form')
                            ->render('user:auth_form');
            
            $recover_form = fx::controller('user:recover_form', array('email' => $_POST['email']))
                            ->render('user:recover_form');
            $res = fx::template('helper_admin:authorize')->render(array(
                'auth_form' => $auth_form,
                'recover_form' => $recover_form
            ));
        }
        return fx::page()->post_process($res);
    }
    
    
    public function move_save($input) {
        
        $essence = $this->essence_type;

        $positions = $input['positions'] ? $input['positions'] : $input['pos'];
        if ($positions) {
            $priority = 0;
            foreach ($positions as $id) {
                $item = fx::data($essence)->get_by_id($id);
                if ($item) {
                    $item->set('priority', $priority++)->save();
                }
            }
        }

        return array('status' => 'ok');
    }

    public function on_save($input) {

        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es)->get_by_id($id)->checked();
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }

        return $result;
    }

    public function off_save($input) {
        
        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es)->get_by_id($id)->unchecked();
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }

        return $result;
    }

    public function delete_save($input) {
        
        $es = $this->essence_type;
        $result = array('status' => 'ok');

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                fx::data($es, $id)->delete();
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }
}
