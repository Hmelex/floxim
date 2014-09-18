<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Console extends Admin {
    public function show($input) {
        $this->response->breadcrumb->add_item(fx::alang('Console'), '#admin.console.show');
        $this->response->submenu->set_menu('console');
        $fields = array(
            'console_text' => array(
                'name' => 'console_text',
                'type' => 'text',
                'code' => 'htmlmixed',
                'value' => isset($input['console_text']) ? $input['console_text'] : '<?php'."\n"
            )
        );
        $fields []= $this->ui->hidden('essence', 'console');
        $fields []= $this->ui->hidden('action', 'execute');
        $this->response->add_form_button(
            array(
                'label' => fx::alang('Execute').' (Ctrl+Enter)',
                'is_submit' => false,
                'class' => 'execute'
            )
        );
        $this->response->add_fields($fields);
        fx::page()->add_js_file(fx::path('floxim').'/admin/js/console.js');
        return array('show_result' => 1);
    }
    
    public function execute($input) {
        if ($input['console_text']) {
            ob_start();
            $code = $input['console_text'];
            $code = preg_replace("~^<\?(?:php)?~", '', $code);
            fx::env('console', true);
            eval($code);
            $res = ob_get_clean();
            return array(
                'result'=> $res
            );
        }
    }
}
