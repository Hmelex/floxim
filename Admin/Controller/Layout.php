<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Layout extends Admin
{

    /**
     * List all of layout design in development
     */
    public function all()
    {
        $items = array();

        $layouts = fx::data('layout')->all();
        foreach ($layouts as $layout) {
            $layout_id = $layout['id'];
            $items[$layout_id] = $layout;
        }

        $layout_use = array(); // [room layout][number of website] => 'website Name'
        foreach (fx::data('site')->all() as $site) {
            $layout_use[$site['layout_id']][$site['id']] = '<a href="#admin.site.settings(' . $site['id'] . ')">' . $site['name'] . '</a>';
        }

        $ar = array('type' => 'list', 'filter' => true, 'entity' => 'layout');
        $ar['labels'] = array('name'    => fx::alang('Name', 'system'),
                              'use'     => fx::alang('Used on', 'system'),
                              'buttons' => array('type' => 'buttons')
        );

        foreach ($items as $item) {
            $submenu = self::getTemplateSubmenu($item);
            $submenu_first = current($submenu);
            $name = array(
                'name' => $item['name'],
                'url'  => $submenu_first['url']
            );
            $el = array('id' => $item['id'], 'name' => $name);
            if ($layout_use[$item['id']]) {
                $el['use'] = join(', ', $layout_use[$item['id']]);
                $el['fx_not_available_buttons'] = array('delete');
            } else {
                $el['use'] = ' - ';
            }
            $el['buttons'] = array();

            foreach ($submenu as $submenu_item) {
                $el['buttons'][] = array(
                    'label' => $submenu_item['title'],
                    'url'   => $submenu_item['url']
                );
            }

            $ar['values'][] = $el;
        }

        $fields[] = $ar;

        $this->response->addButtons(array(
            array(
                'key'   => 'add',
                'title' => 'Add new layout',
                'url'   => '#admin.layout.add'
            ),
            'delete'
        ));

        $result = array('fields' => $fields);
        $this->response->submenu->setMenu('layout');
        return $result;
    }

    public function add($input)
    {
        $input['source'] = 'new';
        $fields = array(
            $this->ui->hidden('action', 'add'),
            $this->ui->hidden('entity', 'layout'),
            array('name' => 'name', 'label' => fx::alang('Layout name', 'system')),
            $this->getVendorField(),
            array('name' => 'keyword', 'label' => fx::alang('Layout keyword', 'system')),
            $this->ui->hidden('source', $input['source']),
            $this->ui->hidden('posting')
        );
        $this->response->submenu->setMenu('layout');
        $this->response->breadcrumb->addItem(fx::alang('Layouts', 'system'), '#admin.layout.all');
        $this->response->breadcrumb->addItem(fx::alang('Add new layout', 'system'));
        $this->response->addFormButton('save');
        $result['fields'] = $fields;
        return $result;
    }


    public function addSave($input)
    {
        $result = array('status' => 'ok');
        $keyword = trim($input['keyword']);
        $name = trim($input['name']);
        $vendor = trim($input['vendor']);

        if (empty($keyword)) {
            $keyword = fx::util()->strToKeyword($name);
        }

        //$keyword = $vendor.'.'.fx::util()->underscoreToCamel($keyword,true);
        $keyword = fx::util()->camelToUnderscore($vendor) . '.' . $keyword;

        $existing = fx::data('layout')->where('keyword', $keyword)->one();
        if ($existing) {
            return array(
                'status' => 'error',
                'text'   => sprintf(fx::alang('Layout %s already exists'), $keyword)
            );
        }

        $data = array('name' => $name, 'keyword' => $keyword);
        $layout = fx::data('layout')->create($data);
        try {
            $layout->save();
            $layout->scaffold();
            $result['reload'] = '#admin.layout.all';
        } catch (Exception $e) {
            $result['status'] = 'error';
        }
        return $result;
    }

    public function deleteSave($input)
    {
        $result = array('status' => 'ok');
        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            try {
                $layout = fx::data('layout', $id);
                $layout->delete();
            } catch (Exception $e) {
                $result['status'] = 'ok';
                $result['text'][] = $e->getMessage();
            }
        }
        return $result;
    }


    public function operating($input)
    {
        $layout = fx::data('layout', $input['params'][0]); //->get_by_id($input['params'][0]);
        $action = isset($input['params'][1]) ? $input['params'][1] : 'layouts';

        if (!$layout) {
            $fields[] = $this->ui->error(fx::alang('Layout not found', 'system'));
            return array('fields' => $fields);
        }

        self::makeBreadcrumb($layout, $action, $this->response->breadcrumb);

        if (method_exists($this, $action)) {
            $result = call_user_func(array($this, $action), $layout);
        }

        $this->response->submenu->setMenu('layout-' . $layout['id'])->setSubactive($action);
        return $result;
    }

    public static function makeBreadcrumb($template, $action, $breadcrumb)
    {
        $tpl_submenu = self::getTemplateSubmenu($template);
        $tpl_submenu_first = current($tpl_submenu);

        $breadcrumb->addItem(fx::alang('Layouts', 'system'), '#admin.layout.all');
        $breadcrumb->addItem($template['name'], $tpl_submenu_first['url']);
        $breadcrumb->addItem($tpl_submenu[$action]['title'], $tpl_submenu[$action]['url']);
    }

    public function layouts($template)
    {
        $items = $template->get_layouts();

        $ar = array('type' => 'list', 'filter' => true);
        $ar['labels'] = array('name' => fx::alang('Name', 'system'));

        foreach ($items as $item) {
            $name = array('name' => $item['name'], 'url' => 'layout.edit(' . $item['id'] . ')');
            $el = array('id' => $item['id'], 'name' => $name);
            $ar['values'][] = $el;
        }

        $fields[] = $ar;
        $buttons = array("add", "delete");
        $buttons_action['add']['options']['parent_id'] = $template['id'];
        $result = array('fields'         => $fields,
                        'buttons'        => $buttons,
                        'buttons_action' => $buttons_action,
                        'entity'         => 'layout'
        );
        return $result;
    }

    public static function getTemplateSubmenu($layout)
    {
        $titles = array(
            'settings' => fx::alang('Settings', 'system'),
            'source'   => "Source"
        );

        $layout_id = $layout['id'];
        $items = array();
        foreach ($titles as $key => $title) {
            $items [$key] = array(
                'title' => $title,
                'code'  => $key,
                'url'   => 'layout.operating(' . $layout_id . ',' . $key . ')'
            );
        }
        return $items;
    }

    // todo: not used method?
    public function files($template)
    {
        $params = isset($this->input['params']) ? $this->input['params'] : array();

        $fm_action = isset($params[2]) ? $params[2] : 'ls';
        $fm_path = isset($params[3]) ? $params[3] : '';
        // todo: psr0 need verify - class fx_controller_admin_module_filemanager not found
        $filemanager = new fx_controller_admin_module_filemanager($fm_input, $fm_action, true);
        $path = $template->getPath();
        $fm_input = array(
            'base_path'         => $path,
            'path'              => $fm_path,
            'base_url_template' => '#admin.template.operating(' . $template['id'] . ',files,#action#,#params#)',
            'root_name'         => $template['name'],
            'file_filters'      => array('!~^\.~', '!~\.php$~'),
            'breadcrumb_target' => $this->response->breadcrumb
        );
        $result = $filemanager->process();
        $result['buttons_entity'] = 'module_filemanager';
        return $result;
    }

    public function settings($template)
    {
        $fields[] = $this->ui->input('name', fx::alang('Layout name', 'system'), $template['name']);
        $fields[] = array(
            'name'     => 'keyword',
            'label'    => fx::alang('Layout keyword', 'system'),
            'value'    => $template['keyword'],
            'disabled' => true
        );
        $fields[] = $this->ui->hidden('action', 'settings');
        $fields[] = $this->ui->hidden('id', $template['id']);

        $this->response->submenu->setMenu('layout');
        $result = array('fields' => $fields, 'form_button' => array('save'));
        return $result;
    }

    public function settingsSave($input)
    {
        $name = trim($input['name']);
        if (!$name) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Enter the layout name', 'system');
            $result['fields'][] = 'name';
        } else {
            $template = fx::data('template')->getById($input['id']);
            if ($template) {
                $result['status'] = 'ok';
                $template->set('name', $name)->save();
            } else {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Layout not found', 'system');
            }
        }

        return $result;
    }


    public function source($layout)
    {
        $template = fx::template('theme.' . $layout['keyword']);
        $vars = $template->getTemplateVariants();
        $files = array();
        foreach ($vars as $var) {
            $files[preg_replace("~^.+/~", '', $var['file'])] = $var['file'];
        }
        foreach ($files as $file => $path) {
            $tab_code = md5($file);//preg_replace("~\.~", '_', $file);
            $tab_name = fx::path()->fileName($file);
            $source = file_get_contents($path);
            $this->response->addTab($tab_code, $tab_name);
            $this->response->addFields(array(
                array(
                    'type'  => 'text',
                    'code'  => 'htmlmixed',
                    'name'  => 'source_' . $tab_code,
                    'value' => $source
                )
            ), $tab_code);
        }
        $fields = array(
            $this->ui->hidden('entity', 'layout'),
            $this->ui->hidden('action', 'source')
        );
        $this->response->submenu->setMenu('layout');
        $this->response->addFormButton('save');
        return array('fields' => $fields, 'form_button' => array('save'));
    }
}