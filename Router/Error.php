<?php

namespace Floxim\Floxim\Router;

use Floxim\Floxim\System\Fx as fx;

class Error extends Front
{
    public function route($url = null, $context = null)
    {
        $site_id = isset($context['site_id']) ? $context['site_id'] : fx::env('site');
        $error_page = $this->getErrorPage($site_id);
        $ctr = fx::router('front')->route($error_page['url'], $context);
        fx::http()->status('404');
        return $ctr;
    }
    
    public function getErrorPage($site_id) 
    {
        $error_page = fx::data(
            'page',
            fx::data('site', $site_id)->get('error_page_id')
        );
        return $error_page;
    }
}