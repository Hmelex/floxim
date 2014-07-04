<?php

class fx_hook_manager {

    public function create($name=null,$type=null,$params=array()) {
        if (is_null($name) and !is_null($type)) {
            $name=$type;
        }
        $name='h'.date('Ymd_His').(!is_null($name) ? "_$name" : '');
        // get code for before
        if (!is_null($type) and method_exists($this,"create_before_for_{$type}")) {
            $code_before=call_user_func(array($this,"create_before_for_{$type}"),$params);
        } else {
            $code_before='';
        }
        // get code for after
        if (!is_null($type) and method_exists($this,"create_after_for_{$type}")) {
            $code_after=call_user_func(array($this,"create_after_for_{$type}"),$params);
        } else {
            $code_after='';
        }
        $content="<?php
        class {$name} {

            // Run before patch
            public function before() {
                {$code_before}
            }

            // Run after patch
            public function after() {
                {$code_after}
            }

        }";

        $dir=fx::path('floxim', '/update/hook');
        try {
            if (file_exists($dir)) {
                fx::files()->mkdir($dir);
            }
            fx::files()->writefile($dir.'/'.$name.'.php', $content);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate code for creating component
     *
     * @param $params
     *
     * @return string
     */
    protected function create_after_for_component_create($params) {
        $data_export=var_export($params['data'],true);
        $code='$data='.$data_export.";\n";
        $data=$params['data'];

        // replace parent_id by search keyword
        if ($data['parent_id']) {
            if ($component_parent=fx::data("component",$data['parent_id'])) {
                $code.='if ($component_parent=fx::data("component")->where("keyword", "'.$component_parent['keyword'].'")->one()) {
                    $data["parent_id"]=$component_parent["id"];
                }'."\n";
            }
        }

        $code.='
        $component=fx::data("component")->create($data);
        $component->save();
        ';
        return $code;
    }

    /**
     * Generate code for delete component
     *
     * @param $params
     *
     * @return string
     */
    protected function create_after_for_component_delete($params) {
        $component=$params['component'];
        $code='
            $component=fx::data("component")->where("keyword","'.$component['keyword'].'")->one();
            if ($component) {
                $component->delete();
            }
        ';
        return $code;
    }

    /**
     * Generate code for creating field
     *
     * @param $params
     *
     * @return string
     */
    protected function create_after_for_field_create($params) {
        $data_export=var_export($params['data'],true);
        $code='$data='.$data_export.";\n";
        $data=$params['data'];

        /**
         * Convert field values: type, component_id, priority
         */
        if ($component=fx::data("component",$data['component_id'])) {
            $code.='if ($component=fx::data("component")->where("keyword", "'.$component['keyword'].'")->one()) {
                    $data["component_id"]=$component["id"];
                }'."\n";
        }
        if ($type=fx::data("component",$data['type'])) {
            $code.='if ($type=fx::data("datatype")->where("name", "'.$type['name'].'")->one()) {
                    $data["type"]=$type["id"];
                }'."\n";
        }
        $code.='$data["priority"] = fx::data("field")->next_priority();'."\n";

        $code.='
        $field = fx::data("field")->create($data);
        $field->save();
        ';
        return $code;
    }
    /**
     * Generate code for delete field
     *
     * @param $params
     *
     * @return string
     */
    protected function create_after_for_field_delete($params) {
        $field=$params['field'];

        $component=fx::data("component",$field['component_id']);
        $code='
            $component=fx::data("component")->where("keyword", "'.$component['keyword'].'")->one();
            $field=fx::data("field")->where("keyword","'.$field['keyword'].'")->where("component_id",$component["id"])->one();
            $field->delete();
        ';
        return $code;
    }

}