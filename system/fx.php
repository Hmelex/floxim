<?php

/**
"Static" class, just a helpers collection
*/ 

class fx {
    protected function __construct() {

    }

    /**
     * Force complete run script
     */
    static public function complete($data=null) {
        fx::env('complete_ok',true);
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_clean();
        }
        if (!is_null($data)) {
            if (is_scalar($data)) {
                echo($data);
            } else {
                echo(json_encode($data));
            }
            die();
        }
    }

    /* Get config data */
    static public function config($k = null, $v = null) {
        static $config = false;
        if ($config === false) {
            $config = new fx_config();
        }
        $argc = func_num_args();
        if ($argc == 0) {
            return $config;
        }
        if ($argc == 1) {
            return $config->get($k);
        }
        $config->set($k, $v);
        return $config;
    }
    
    /**
     * Access a database object
     * @return fx_db
     */
    public static function db() {
        static $db = false;
    	if ($db === false) {
            $db = new fx_db();
            $db->query("SET NAMES '".fx::config()->DB_CHARSET."'");
    	}
    	return $db;
    }
    
    protected static $data_cache = array();
    /* Get data finder for the specified type content_id data or the object(s) by id
     * @param string $datatype name of a data type 'component', 'content_news'
     * @param mixed [$id] IDs or ids array
     */
    public static $data_stat = array();
    public static function  data($datatype, $id = null) {
    	
    	static $data_classes_cache = array();
        
        if (is_array($datatype)) {
            $datatype = join("_", $datatype);
        }
        
        // fx::data($page) instead of $page_id
        if (is_object($id) && $id instanceof fx_essence) {
            return $id;
        }
        if (
            !is_null($id) && 
            !is_array($id) && 
            isset(self::$data_cache[$datatype]) &&  
            isset(self::$data_cache[$datatype][$id])
        ) {
                return self::$data_cache[$datatype][$id];
        }
        
        $data_finder = null;
        
        $component = null;
        
        if (preg_match("~^content~", $datatype)) {
            if ($datatype == 'content_content') {
                $datatype = 'content';
            }
            if ($datatype == 'content') {
                $component = fx::data('component', 'content');
            } else {
                $component = fx::data('component', preg_replace("~^content_~", '', $datatype));
            }
        }
        
        // look for data-* class in cache
        if (isset($data_classes_cache[$datatype])) {
            $finder_class = $data_classes_cache[$datatype];
            if ($finder_class == 'fx_data') {
                $data_finder = new fx_data($datatype);
            } else {
                $data_finder = new $finder_class();
            }
        } else {
            try {
                $classname = 'fx_data_'.$datatype;
                $data_finder = new $classname();
                $data_classes_cache[$datatype] = $classname;
            } catch (Exception $e) {
                // Finder for the content that the class is not defined
                if ($component) {
                    $not_existing = array($datatype);
                    foreach ($component->get_ancestors() as $parent_com) {
                        try {
                            $keyword = $parent_com['keyword'];
                            $c_datatype = 'content'.($keyword == 'content' ? '' : '_'.$keyword);
                            $classname = 'fx_data_'.$c_datatype;
                            $data_finder = new $classname;
                            foreach ($not_existing as $ne) {
                                $data_classes_cache[$ne] = $classname;
                            }
                            break;
                        } catch (Exception $ex) {
                            $not_existing []= $c_datatype;
                        }
                    }
                } elseif (preg_match("~^field_~", $datatype)) {
                    $data_finder = new fx_data_field();
                    $data_classes_cache[$datatype] = 'fx_data_field';
                }
            }
            if (is_null($data_finder)) {
                $data_finder = new fx_data($datatype);
                $data_classes_cache[$datatype] = 'fx_data';
            }
        }
		
        if ($component) {
            $data_finder->set_component($component['id']);
        }
		
        if (func_num_args() == 2) {
            if (is_numeric($id) || is_string($id)) {
                $res = $data_finder->get_by_id($id);
                self::$data_cache[$datatype][$id] = $res;
                return $res;
            }
            if (is_array($id)) {
                return $data_finder->get_by_ids($id);
            }
            return null;
        }
    	return $data_finder;
    }
    
    public static function content($type = null, $id = null) {
        if (is_numeric($type)) {
            return fx::data('content', $type);
        }
        $type = 'content'. (!$type ? '' : '_'.$type);
        $args = func_get_args();
        $args[0] = $type;
        return call_user_func_array('fx::data', $args); 
    }
    
    protected static $router = null;
    /**
     * Get a basic routing Manager or router $router_name
     * @param $router_name = null
     * @return fx_router_manager
     */
    public static function router($router_name = null) {
    	if (self::$router === null) {
            self::$router = new fx_router_manager();
    	}
        if (func_num_args() == 1) {
            return self::$router->get_router($router_name);
        }
    	return self::$router;
    }
    
    public static function is_admin() {
        static $is_admin = null;
        if (is_null($is_admin)) {
            $is_admin = (bool) self::env('is_admin');
        }
        return $is_admin;
    }
    
    /**
     * Call without parameters to return the object with the parameters - get/set property
     * @param string $property prop_name
     * @param mixed $value set value
     */
    public static function env() {
        static $env = false;
        if ($env === false) {
            $env = new fx_system_env();
        }
    	
        $args = func_get_args();
    	if (count($args) == 0) {
            return $env;
    	}
    	if (count($args) == 1) {
            if ($args[0] == 'is_admin') {
                $method = array($env, 'is_admin');
            } else {
                $method = array($env, 'get_'.$args[0]);
            }
            if (is_callable($method)) {
                return call_user_func($method);
            }
            return $env->get($args[0]);
    	}
    	if (count($args) == 2) {
            $method = array($env, 'set_'.$args[0]);
            if (is_callable($method)) {
                return call_user_func($method, $args[1]);
            }
            return call_user_func(array($env, 'set'), $args[0], $args[1]);
    	}
    	return null;
    }
    
    /**
     * to create a controller, install options
     * @param string $controller 'controller_name' or 'controller_name.action_name'
     * @param array $input
     * @param string $action
     * @return fx_controller initialized controller
     */
    public static function controller($controller, $input = null, $action = null) {
    	$c_parts = explode(".", $controller);
        if (count($c_parts) == 2) {
            $controller = $c_parts[0];
            $action = $c_parts[1];
    	}
    	$c_class = 'fx_controller_'.$controller;
    	try {
            $controller_instance = new $c_class($input, $action);
            return $controller_instance;
    	} catch (Exception $e) {
            if ($controller === 'component_content') {
                $controller_instance = new fx_controller_component($input, $action);
                $controller_instance->set_content_type('content');
                return $controller_instance;
            }
            if (preg_match("~^(component|widget|layout)_(.+)$~", $controller, $c_parts)) {
                $ctr_type = $c_parts[1];
                $ctr_name = $c_parts[2];
                if ($ctr_type === 'component') {
                    $component = fx::data('component', $ctr_name);
                    if ($component) {
                        foreach ($component->get_ancestors() as $parent_com) {
                            try {
                                $keyword = $parent_com['keyword'];
                                $c_class = 'fx_controller_component'.($keyword == 'content' ? '' : '_'.$keyword);
                                $controller_instance = new $c_class($input, $action);
                                $controller_instance->set_content_type($ctr_name);
                                return $controller_instance;
                            } catch (Exception $ex) {
                                
                            }
                        }
                    } else {
                        fx::log("no com", $ctr_name, debug_backtrace());
                    }
                }
                $c_class = 'fx_controller_'.$ctr_type;
                try {
                    $controller_instance = new $c_class($input, $action);
                    switch ($ctr_type) {
                        case 'widget':
                            $controller_instance->set_keyword($ctr_name);
                            break;
                    }
                    return $controller_instance;
                } catch (exception $e) {
                    
                }
            }
            die("Failed loading controller class ".$c_class);
    	}
    }

    public static function template($template = null, $data = array()) {
        if (func_num_args() == 0) {
            return new fx_template_loader();
        }
        $parts= explode(".", $template);
        if (count($parts) == 2) {
            $template = $parts[0];
            $action = $parts[1];
        } else {
            $action = null;
        }
        $class_name = 'fx_template_'.$template;
        if (!class_exists($class_name)) {
            $class_name = 'fx_template';
        }
        return new $class_name($action, $data);
    }
    
    protected static $page = null;
    
    /**
     * @return fx_system_page page instance
     */
    public static function page() {
        if (!self::$page) {
            self::$page = new fx_system_page();
        }
        return self::$page;
    }
    
    /*
     * Utility for accessing deep array indexes
     * @param ArrayAccess $collection
     * @param $var_path
     * @param [$index_2] etc.
     * @example $x = fx::dig(array('y' => array('x' => 2)), 'y.x');
     * @example $x = fx::dig(array('y' => array('x' => 2)), 'y', 'x');
     */
    public static function dig($collection, $var_path) {
        if (func_num_args() > 2) {
            $var_path = func_get_args();
            array_shift($var_path);
        } else {
            $var_path = explode(".", $var_path);
        }
        $arr = $collection;
        foreach ($var_path as $pp) {
            if (is_array($arr) || $arr instanceof ArrayAccess) {
                if (!isset($arr[$pp])) {
                    return null;
                }
                $arr = $arr[$pp];
            } elseif (is_object($arr) && isset($arr->$pp)) {
                if (!isset($arr->$pp)) {
                    return null;
                }
                $arr = $arr->$pp;
            } else {
                return null;
            }
        }
        return $arr;
    }
    
    public static function dig_set(&$collection, $var_path, $var_value, $merge = false) {
        $var_path = explode('.', $var_path);
        
        $arr =& $collection;
        $total = count($var_path);
        foreach ($var_path as $num => $pp) {
            $is_arr = is_array($arr);
            $is_aa = $arr instanceof ArrayAccess;
            if (!$is_arr && !$is_aa) {
                return null;
            }
            if (empty($pp)) {
                $arr[]= $var_value;
                return;
            }
            if (($is_arr && !array_key_exists($pp, $arr)) || ($is_aa && !isset($arr[$pp])) ) {
                if ($num + 1 === $total && !$merge) {
                    $arr[$pp] = $var_value;
                    return;
                }
                $arr[$pp]= array(); //fx::collection();
            }
            $arr =&  $arr[$pp];
        }
        
        if ($merge && is_array($arr) && is_array($var_value)) {
            $arr = array_merge_recursive($arr, $var_value);
        } else {
            $arr = $var_value;
        }
    }
    
    /**
     * 
     * @param fx_collection $data
     * @return fx_collection
     */
    public static function collection($data = array()) {
        return $data instanceof fx_collection ? $data : new fx_collection($data);
    }
    
    /*
     * @return fx_system_input
     */
    public static function input() {
        static $input = false;
        if ($input === false) {
            $input = new fx_system_input();
        }
        if (func_num_args() === 0) {
            return $input;
        }
        $superglobal = strtolower(func_get_arg(0));
        if (!in_array($superglobal, array('get', 'post', 'cookie','session'))) {
            return $input;
        }
        $callback = array($input, 'fetch_'.$superglobal);
        if (func_num_args() === 1) {
            return call_user_func($callback);
        }
        return call_user_func($callback, func_get_arg(1));
    }
    
    /*
     * @return fx_core
     */
    public static function load($config = null) {
        
        if ($config !== null) {
            require_once DOCUMENT_ROOT.'/floxim/system/config.php';
            self::config()->load($config);
        }
        
        static $loader = false;
        if ($loader === false) {
            require_once fx::config()->SYSTEM_FOLDER . 'loader.php';
            $loader = new fx_loader();
        }
        
        if (fx::config('cache.meta')) {
            self::_load_meta_cache();
        }
        return $loader;
    }
    
    protected static function _load_meta_cache() {
        // preload meta info
        $cache_file = fx::path('files', 'cache/meta_cache.php');
        
        if (!file_exists($cache_file)) {
            $coms = fx::data('component')->with('fields')->all();
            $com_cache = array();
            $field_cache = array();
            foreach ($coms as $com) {
                $com_cache[$com['keyword']] = $com;
                $com_cache[$com['id']] = $com;
                foreach ($com->fields() as $com_field) {
                    $field_cache[$com_field['id']] = $com_field;
                }
            }
            fx::files()->writefile(
                $cache_file, serialize(array(
                    'component' => $com_cache,
                    'field' => $field_cache
                ))
            );
        } else {
            $cache = unserialize(fx::files()->readfile($cache_file));
            $com_cache = $cache['component'];
            $field_cache = $cache['field'];
        }
        self::$data_cache['component'] = $com_cache;
    }

    public static function lang ( $string = null, $dict = null) {
        static $lang = null;
        if (!$lang) {
            $lang = fx::data('lang_string');
            $lang->set_lang(fx::env()->get_site()->get('language'));
        }
        if ($string === null) {
            return $lang;
        }
        
        if (!($res = $lang->get_string($string, $dict))) {
            try {
                $lang->add_string($string, $dict);
            } catch (Exception $e) {
                fx::log('exc', $e);
            }
            $res = $string;
        }
        if (func_num_args() > 2) {
            $replacements = array_slice(func_get_args(), 2);
            array_unshift($replacements, $res);
            $res = call_user_func_array('sprintf', $replacements);
        }
        return $res;
    }

    public static function alang ( $string = null, $dict = null) {
        static $lang = null;
        if (!$lang) {
            $lang = fx::data('lang_string');
            $lang->set_lang();
        }
        if ($string === null) {
            return $lang;
        }
        
        if (!($res = $lang->get_string($string, $dict))) {
            try {
                $lang->add_string($string, $dict);
            } catch (Exception $e) {
                fx::log('exc', $e);
            }
            $res = $string;
        }
        if (func_num_args() > 2) {
            $replacements = array_slice(func_get_args(), 2);
            array_unshift($replacements, $res);
            $res = call_user_func_array('sprintf', $replacements);
        }
        return $res;
    }

    

    protected static $http = null;
    /**
     * http helper
     * @return fx_http
     */
    public static function http() {
        if (!self::$http) {
            self::$http = new fx_http();
        }
        return self::$http;
    }

    protected static $migration_manager = null;
    /**
     * migration manager
     * @param array $params
     *
     * @return fx_migration_manager
     */
    public static function migrations($params=array()) {
        if (!self::$migration_manager) {
            self::$migration_manager = new fx_migration_manager($params);
        }
        return self::$migration_manager;
    }

    protected static $hook_manager = null;
    /**
     * hook manager
     *
     * @return fx_hook_manager
     */
    public static function hooks() {
        if (!self::$hook_manager) {
            self::$hook_manager = new fx_hook_manager();
        }
        return self::$hook_manager;
    }
    
    /**
     * Get current user or new empty essence (with no id) if not logged in
     * @return fx_content_user
     */
    public static function user() {
        return self::env()->get_user();
    }
    
    protected static function _get_event_manager() {
        static $event_manager = null;
        if (is_null($event_manager)) {
            $event_manager = new fx_system_eventmanager();
        }
        return $event_manager;
    }
    public static function listen($event_name, $callback) {
        self::_get_event_manager()->listen($event_name, $callback);
    }
    
    public static function unlisten($event_name) {
        self::_get_event_manager()->unlisten($event_name);
    }
    
    public static function trigger($event, $params = null) {
        self::_get_event_manager()->trigger($event, $params);
    }
    
    
    protected static $_cache = null;
    /*
     * until very blunt local cache,
     * not to get from the database is the same for single execution
     */
    public static function cache($key = null, $value = null) {
        if (!self::$_cache) {
            self::$_cache = new fx_cache();
        }
        $count_args = func_num_args();
        switch ($count_args) {
            case 0:
                return self::$_cache;
                break;
            case 1:
                return self::$_cache->get($key);
                break;
            case 2:
                self::$_cache->set($key, $value);
                break;
        }
    }
    
    public static function files() {
        static $files = false;
        if ($files === false) {
            $files = new fx_system_files();
        }
        
        if (func_num_args() == 0) {
            return $files;
        }
        $args = func_get_args();
        switch ($args[1]) {
            case 'size':
                $path = fx::path()->to_abs($args[0]);
                return $files->readable_size($path);
            case 'name':
                return fx::path()->file_name($args[0]);
            case 'type':
                return trim(fx::path()->file_extension($args[0]), '.');
        }
    }
    
    public static function util() {
        static $util = false;
        if ($util === false) {
            $util = new fx_system_util();
        }
        return $util;
    }
    
    public static function date($value, $format) {
        if (!is_numeric($value)) {
            $value = strtotime($value);
        }
        return date($format, $value);
    }
    
    public static function image($value, $format) {
        try {
            $thumber = new fx_thumb($value, $format);
            $res = $thumber->get_result_path();
        } catch (Exception $e) {
            $res = '';
        }
        return $res;
    }
    
    public static function version() {
        return fx::config('fx.version');
    }

    public static function changelog($version=null) {
        $file = fx::config('ROOT_FOLDER').'changelog.json';
        if (file_exists($file)) {
            if ($changelog = @json_decode(file_get_contents($file),true)) {
                if (is_null($version)) {
                    return $changelog;
                } else {
                    if (isset($changelog[$version])) {
                        return $changelog[$version];
                    }
                }
            }
        }
        return null;
    }
    
    protected static $debugger = null;
    
    public static function debug($what = null) {
        if (!fx::config('dev.on') && func_num_args() > 0) {
            return;
        }
        if (is_null(self::$debugger)) {
            self::$debugger = new fx_debug();
        }
        if (func_num_args() == 0) {
            return self::$debugger;
        }
        call_user_func_array(array(self::$debugger, 'debug'), func_get_args());
    }
    
    public static function log($what) {
        if (is_null(self::$debugger)) {
            self::$debugger = new fx_debug();
        }
        call_user_func_array(array(self::$debugger, 'log'), func_get_args());
    }
    
    public static function profiler() {
        static $profiler = null;
        if (is_null($profiler)) {
            $profiler = new fx_profiler();
        }
        return $profiler;
    }
    
    public static function path($key = null, $tale = null) {
        static $path = null;
        if (!$path) {
            // we can not autoload path because it gonna be used by autoloader itself
            require_once (dirname(__FILE__).'/path.php');
            $path = new fx_path();
        }
        switch(func_num_args()) {
            case 0: default:
                return $path;
            case 1:
                return $path->abs($key);
            case 2:
                return $path->abs($key, $tale);
        }
    }
    
    /**
     * Get mailer service
     * @param array $params 
     * @param array $data
     * @return \fx_system_mail
     */
    public static function mail($params = null, $data = null) {
        $mailer = new fx_system_mail($params, $data);
        return $mailer;
    }
}