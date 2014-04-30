<?php
class fx_controller_component_user extends fx_controller_component {
    public function do_auth_form() {
        $user = fx::user();
        
        if (!$user->is_guest()) {
            if (!fx::is_admin()) {
                return false;
            }
            $this->_meta['hidden'] = true;
        }
        
        $form = $user->get_auth_form();
        
        if ($form->is_sent() && !$form->has_errors()) {
            $vals = $form->get_values();
            if (!$user->login($vals['email'], $vals['password'])) {
                $form->add_error('User not found or password is wrong', 'email');
            } else {
                fx::http()->refresh();
            }
        }
        
        return array(
            'form' => $form
        );
    }
    
    public function do__crossite_auth() {
        
        $sites = fx::data('site')->where('id', fx::env('site')->get('id'), '!=')->all();
        if (count($sites) == 0) {
            return;
        }
        
        return array('sites' => $sites);
    }
    
    public function do_greet() {
        $user = fx::user();
        if ($user->is_guest()) {
            return false;
        }
        return array(
            'user' => $user,
            'logout_url' => $user->get_logout_url()
        );
    }
    
    public function do_recover_form() {
        $form = new fx_form();
        $form->add_fields(array(
            'email' => array(
                'label' => 'E-mail',
                'validators' => 'email -l',
                'value' => $this->get_param('email')
            ),
            'submit' => array(
                'type' => 'submit',
                'label' => 'Send me new password'
            )
        ));
        if ($form->is_sent() && !$form->has_errors()) {
            $user = fx::data('content_user')->get_by_login($form->email);
            if (!$user) {
                $form->add_error(fx::lang('User not found'), 'email');
            } else {
                $password = $user->generate_password();
                $user['password'] = $password;
                $user->save();
                fx::data('session')->where('user_id', $user['id'])->delete();
                $form->add_message('New password is sent to '.$form->email);
                fx::mail()
                    ->to($form->email)
                    ->subject( fx::lang('New %s password', 'component_user', fx::env('host') ) )
                    ->message( fx::lang(
                        'Hello, %s! Your new password: %s', 
                        'component_user',
                        $user['name'], 
                        $password
                    ))
                    ->send();
            }
        }
        return array('form' => $form);
    }
    
    public function do__logout() {
        $user = fx::user();
        $user->logout();
        $back_url = $this->get_param('back_url', '/');
        fx::http()->redirect($back_url);
    }
}