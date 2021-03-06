<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class Select extends Baze
{

    public function getJsField($content)
    {

        parent::getJsField($content);

        $this->_js_field['values'] = $this->getSelectValues();

        if ($this->format['show'] == 'radio') {
            $this->_js_field['type'] = 'radio';
        }

        $this->_js_field['value'] = $content[$this['keyword']];
        return $this->_js_field;
    }
    
    public function getSelectValues()
    {
        $values = $this->getOptions();
        if (!$this->isNotNull() && is_array($values)) {
            $values = array_merge(array(array('', fx::alang('-- choose something --', 'system'))), $values);
        }
        return $values;
    }

    public function formatSettings()
    {
        $fields = array();

        $fields[] = array(
            'id'    => 'format[source]',
            'name'  => 'format[source]',
            'type'  => 'hidden',
            'value' => 'manual'
        );

        $fields[] = array(
            'name'   => 'format[values]',
            'label'  => fx::alang('Elements', 'system'),
            'type'   => 'set',
            'tpl'    => array(
                array('name' => 'id', 'type' => 'string'),
                array('name' => 'value', 'type' => 'string')
            ),
            'values' => $this['format']['values'] ? $this['format']['values'] : array(),
            'labels' => array('id', 'value')
        );

        return $fields;
    }

    public function getOptions()
    {
        $values = array();
        if ($this->format['values']) {
            foreach ($this->format['values'] as $v) {
                $values[] = array($v['id'], $v['value']);
            }
        }
        return $values;
    }

    public function getValues()
    {
        $values = array();
        if ($this->format['values']) {
            foreach ($this->format['values'] as $v) {
                $values[$v['id']] = $v['value'];
            }
        }
        return $values;
    }

    public function getSqlType()
    {
        return "VARCHAR (255)";
    }
}