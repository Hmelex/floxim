<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Helper\Form;

/**
 * Layer between the table and the object
 */
abstract class Data {

    protected $table;
    protected $pk = 'id';
    protected $order = array();
    //protected $classname;
    protected $serialized = array();
    protected $sql_function = array();
    
    protected $limit;
    protected $where = array();
    
    protected $with = array();
    
    const BELONGS_TO = 0;
    const HAS_MANY = 1;
    const HAS_ONE = 2;
    const MANY_MANY = 3;
    
    protected function _livesearch_apply_terms($terms) {
        foreach ($terms as $tp) {
            $this->where('name', '%'.$tp.'%', 'LIKE');
        }
    }

    public function livesearch($term = null, $limit = null) {
        if (!isset($term)) {
            return;
        }
        $term = trim($term);
        if (!empty($term)) {
            $terms = explode(" ", $term);
            $this->_livesearch_apply_terms($terms);
        }
        if ($limit) {
            $this->limit($limit);
        }
        $this->calc_found_rows(true);
        $items = $this->all();
        if (!$items) {
            return;
        }
        $count = $this->get_found_rows();
        $res = array('meta' => array('total'=>$count), 'results' => array());
        
        $props = array('name', 'id');
        if (isset($this->_livesearch_props) && is_array($this->_livesearch_props)) {
            $props = array_merge($props, $this->_livesearch_props);
        }
        foreach ($items as $i) {
            if (!$i['name']) {
                continue;
            }
            $c_res = array();
            foreach ($props as $prop) {
                $c_res[$prop] = $i[$prop];
            }
            $res['results'][]= $c_res;
        }
        return $res;
    }

    public function relations() {
        return array();
    }
    
    public function get_relation($name) {
        $rels = $this->relations();
        return isset($rels[$name]) ? $rels[$name] : null;
    }

    public function get_multi_lang_fields() {
        return array();
    }

    /*
     * @return \Floxim\Floxim\System\Collection
     */
    public function all() {
        $data = $this->_get_essences();
        return $data;
    }

    public function one() {
        $this->limit = 1;
        $data = $this->_get_essences();
        return isset($data[0]) ? $data[0] : false;
    }
    
    public function limit() {
        $args = func_get_args();
        if (count($args) == 1) {
            $this->limit = $args[0];
        } elseif (count($args) == 2) {
            $this->limit = $args[0].', '.$args[1];
        }
        return $this;
    }
    
    /**
     * For relational fields: join related item and prepare real field name
     * @param string $field
     */
    protected function _prepare_complex_field($field, $operator = 'where') {
        list($rel, $field_name) = explode('.', $field, 2);
        if (preg_match("~^\{\{.+\}\}$~", $rel)) {
            return $field;
        }
        $relation = $this->get_relation($rel);
        if (!$relation) {
            return '`'.$rel.'`.`'.$field_name.'`';
        }
        $with_type = $operator == 'where' ? 'inner' : 'left';
        $this->with($rel, null, $with_type);
        $c_with = $this->with[$rel];
        $with_name = $c_with[0];
        $with_finder = $c_with[1];
        
        
        $table = $with_finder->get_col_table($field_name, false);
        $field = $with_name.'__'.$table.'.'.$field_name;
        return $field;
    }
    
    protected function _prepare_condition($field, $value, $type) {
        if (is_array($field)) {
            foreach ($field as $n => $c_cond) {
                $field[$n] = $this->_prepare_condition($c_cond[0], $c_cond[1], $c_cond[2]);
            }
            return array($field, $value, $type);
        }
        $original_field = $field;
        if (strstr($field, '.')) {
            $field = $this->_prepare_complex_field($field, 'where');
        } elseif (preg_match("~^[a-z0-9_-]~", $field)) {
            $table = $this->get_col_table($field, false);
            $field = '{{'.$table.'}}.'.$field;
        }
        if (is_array($value) && count($value) == 1 && ($type == '=' || $type == 'IN')) {
            $value = current($value);
            $type = '=';
        }
        return array($field, $value, $type, $original_field);
    }
    
    public function where($field, $value, $type = '=') {
        $cond = $this->_prepare_condition($field, $value, $type);
        $this->where []= $cond;
        return $this;
    }
    
    public function where_or() {
        $conditions = func_get_args();
        $this->where []= array($conditions, null, 'OR');
        return $this;
    }
    
    public function clear_where($field, $value = null) {
        foreach ($this->where as $where_num => $where_props) {
            if ($where_props[0] == $field) {
                if (func_num_args() == 1 || $value == $where_props[1]) {
                    unset($this->where[$where_num]);
                }
            }
        }
        return $this;
    }
    
    public function order($field, $direction = 'ASC') {
        // clear order by passing null
        if ($field === null) {
            $this->order = array();
            return $this;
        }
        if (!preg_match("~asc|desc~i", $direction)) {
            $direction = 'ASC';
        }
        if (strstr($field, '.')) {
            $this->order []= $this->_prepare_complex_field($field, 'order').' '.$direction;
        } else {
            $table = $this->get_col_table($field);
            $this->order []= "{{".$table."}}.`".$field."` ".$direction;
        }
        return $this;
    }
        
    public function with($relation, $finder = null, $only = false) {
        if ( is_callable($finder) || is_null($finder) ) {
            $rel = $this->get_relation($relation);
            $default_finder = $this->_get_default_relation_finder($rel);
            if (is_callable($finder)) {
                call_user_func($finder, $default_finder);
            }
            $finder = $default_finder;
        }
        $with = array($relation, $finder, $only);
        $this->with [$relation]= $with;
        if ($only !== false) {
            $join_type = is_string($only) ? $only : 'inner';
            $this->_join_with($with, $join_type);
        }
        return $this;
    }
    
    public function only_with($relation, $finder = null) {
        $this->with($relation, $finder, true);
        return $this;
    }

    protected $calc_found_rows = false;
    public function calc_found_rows($on = true) {
        $this->calc_found_rows = (bool) $on;
    }
    
    public function get_found_rows() {
        return isset($this->found_rows) ? $this->found_rows : null;
    }
    
    protected $select = null;
    
    public function select($what) {
        // reset: $finder->select(null)
        if (func_num_args() == 1 && is_null($what)) {
            $this->select = null;
            return $this;
        }
        if (is_null($this->select)) {
            $this->select = array();
        }
        foreach (func_get_args() as $arg) {
            if ($arg === 'id') {
                $tables = $this->get_tables();
                $arg = '{{'.$tables[0].'}}.id';
            }
            $this->select []= $arg;
        }
        return $this;
    }
    
    protected $group = array();
    public function group($by) {
        if (func_num_args() == 1 && is_null($by)) {
            $this->group = array();
            return $this;
        }
        $this->group []= $by;
        return $this;
    }
    
    public function build_query() {
        // 1. To get tables-parents
        $tables = $this->get_tables();
        if (is_null($this->select)) {
            foreach ($tables as $t) {
                $this->select []= '{{'.$t.'}}.*';
            }
        }
        $base_table = array_shift($tables);
        $q = 'SELECT ';
        if ($this->calc_found_rows) {
            $q .= 'SQL_CALC_FOUND_ROWS ';
        }
        
        $q .= join(", ", $this->select);
        $q .= ' FROM `{{'.$base_table."}}`\n";
        foreach ($tables as $t) {
            $q .= 'INNER JOIN `{{'.$t.'}}` ON `{{'.$t.'}}`.id = `{{'.$base_table."}}`.id\n";
        }
        foreach ($this->joins as $join) {
            $q .= $join['type'].' ';
            $q .= 'JOIN ';
            $q .= $join['table'].' ON '.$join['on'].' ';
        }
        if (count($this->where) > 0) {
            $conds = array();
            foreach ($this->where as $cond) {
                $conds []= $this->_make_cond($cond, $base_table);
            }
            $q .= "\nWHERE ".join(" AND ", $conds);
        }
        if (count($this->group) > 0) {
            $q .= "\n GROUP BY ".join(", ", $this->group);
        }
        if (is_string($this->order)) {
            $this->order = array($this->order);
        }
        if (is_array($this->order) && count($this->order) > 0) {
            $q .= "\n ORDER BY ".join(", ", $this->order);
        }
        if ($this->limit){
            $q .= "\n LIMIT ".$this->limit;
        }
        //fx::debug(fx::db()->prepare_query($q));
        return $q;
    }
    
    protected $joins = array();
    
    public function join($table, $on, $type = 'inner') {
        if (is_array($table)) {
            $table = '{{'.$table[0].'}} as '.$table[1];
        }
        $this->joins[$table]= array(
            'table' => $table, 
            'on' => $on, 
            'type' => strtoupper($type)
        );
        return $this;
    }
    
    // inner join fx_content as user__fx_content on fx_content.user_id = user__fx_content.id
    // todo: psr0 need fix
    protected function _join_with($with, $join_type = 'inner') {
        $rel_name = $with[0]; 
        $finder = $with[1];
        $rel = $this->get_relation($rel_name);
        $finder_tables = $finder->get_tables();
        
        // column-link
        $link_col = $rel[2];
        
        switch ($rel[0]) {
            case Data::BELONGS_TO:
                $joined_table = array_shift($finder_tables);
                $joined_alias = $rel_name.'__'.$joined_table;
                // table of current finder containing the page, link
                $our_table = $this->get_col_table($link_col, false);
                $this->join(
                    array($joined_table, $joined_alias),
                    $joined_alias.'.id = {{'.$our_table.'}}.'.$link_col,
                    $join_type
                );
                foreach ($finder_tables as $t) {
                    $alias = $rel_name.'__'.$t;
                    $this->join(
                        array($t, $alias),
                        $alias.'.id = '.$joined_alias.'.id',
                        $join_type
                    );
                }
                break;
            case Data::HAS_MANY:
                $their_table = $finder->get_col_table($link_col, false);
                $joined_alias = $rel_name.'__'.$their_table;
                $their_table_key = array_keys($finder_tables, $their_table);
                unset($finder_tables[$their_table_key[0]]);
                $this->join(
                    array($their_table, $joined_alias),
                    $joined_alias.'.'.$link_col.' = {{'.$this->table.'}}.id',
                    $join_type
                );
                $this->group('{{'.$this->table.'}}.id');
                foreach ($finder_tables as $t) {
                    $alias = $rel_name.'__'.$t;
                    $this->join(
                        array($t, $alias),
                        $alias.'.id = '.$joined_alias.'.id',
                        $join_type
                    );
                }
                break;
            case Data::MANY_MANY:
                $linker_table = $finder->get_col_table($link_col, false);
                $joined_alias = $rel_name.'_linker__'.$linker_table;
                $linker_table_key = array_keys($finder_tables, $linker_table);
                unset($finder_tables[$linker_table_key[0]]);
                $this->join(
                    array($linker_table, $joined_alias),
                    $joined_alias.'.'.$link_col.' = {{'.$this->table.'}}.id',
                    $join_type
                );
                $this->group('{{'.$this->table.'}}.id');
                foreach ($finder_tables as $t) {
                    $alias = $rel_name.'_linker__'.$t;
                    $this->join(
                        array($t, $alias),
                        $alias.'.id = '.$joined_alias.'.id',
                        $join_type
                    );
                }
                $link_table_alias = $rel_name.'_linker__'.$finder->get_col_table($rel[5], false);
                
                $end_finder = fx::data($rel[4]);
                $end_tables = $end_finder->get_tables();
                $first_end_table = array_shift($end_tables);
                $first_end_alias = $rel_name.'__'.$first_end_table;
                $this->join(
                    array($first_end_table, $first_end_alias),
                    $first_end_alias.'.id = '.$link_table_alias.'.'.$rel[5],
                    $join_type
                );
                foreach ($end_tables as $et) {
                    $et_alias = $rel_name.'__'.$et;
                    $this->join(
                        array($et, $et_alias),
                        $et_alias.'.id = '.$first_end_alias.'.id',
                        $join_type
                    );
                }
                break;
        }
    }
    
    protected function _make_cond($cond, $base_table) {
        if (strtoupper($cond[2]) === 'OR') {
            $parts = array();
            foreach ($cond[0] as $sub_cond) {
                if (!isset($sub_cond[2])) {
                    $sub_cond[2] = '=';
                }
                $parts []= $this->_make_cond($sub_cond, $base_table);
            }
            if (count($parts) == 0) {
                return ' FALSE';
            }
            return " (".join(" OR ", $parts).") ";
        }
        if (strtoupper($cond[2]) === 'RAW') {
            $field_name = $cond[0];
            if (!$field_name) {
                return $cond[1];
            }
            if (!preg_match("~^\`~", $field_name)) {
                $field_name = '`'.(join("`.`", explode(".", $field_name))).'`';
            }
            return $field_name.' '.$cond[1];
        }
        list($field, $value, $type) = $cond;
        if ($field == 'id') {
            $field = "`{{".$base_table."}}`.id";
        }
        if ($value instanceof Collection) {
            $value = $value->column(function($i) {
                return $i instanceof Essence ? $i['id'] : (int) $i;
            })->unique()->get_data();
        }
        if (is_array($value)) {
            if (count($value) == 0) {
                return 'FALSE';
            }
            if ($type == '=') {
                $type = 'IN';
            }
            $value = " ('".join("', '", $value)."') ";
        } elseif (in_array(strtolower($type), array('is null', 'is not null'))) {
            $value = '';
        } else {
            $value = "'".$value."'";
        }
        return $field.' '.$type.' '.$value;
    }
    
    public function show_query() {
        return fx::db()->prepare_query($this->build_query());
    }
    
     /*
     * Method collects flat data
     */
    public function get_data() {
        $query = $this->build_query();
        $res = fx::db()->get_results($query);

        if (fx::db()->is_error()) {
            throw new Exception("SQL ERROR");
        }
        
        if ($this->calc_found_rows) {
            $this->found_rows = fx::db()->get_var('SELECT FOUND_ROWS()');
        }

        $objs = array();
        foreach ($res as $v) {
            // don't forget serialized
            foreach ($this->serialized as $serialized_field_name) {
                if (isset($v[$serialized_field_name])) {
                    $v[$serialized_field_name] = unserialize($v[$serialized_field_name]);
                }
            }
            $objs[] = $v;
        }
        $collection = new Collection($objs);
        $collection->finder = $this;
        if (is_array($this->order)) {
            foreach ($this->order as $sorting) {
                if (preg_match("~priority~", $sorting)) {
                    $collection->is_sortable = true;
                    break;
                }
            }
        }
        return $collection;
    }
    
    /*
     * Method call $this->get_data(),
     * from the collection of the flat data collects essence
     */
    protected function _get_essences() {
        //fx::config('dev.on', fx::env('console'));
        $data = $this->get_data();
        //fx::debug('start filless', $data);
        foreach ($data as $dk => $dv) {
            $data[$dk] = $this->essence($dv);
        }
        //fx::debug('start adrels');
        $this->_add_relations($data);
        //fx::debug('ready');
        return $data;
    }
    
    protected function _get_default_relation_finder($rel) {
        return fx::data($rel[1]);
    }
    
    public function add_related($rel_name, $essences, $rel_finder = null) {
        
        $relations = $this->relations();
        if (!isset($relations[$rel_name])) {
            return;
        }
        $rel = $relations[$rel_name];
        list($rel_type, $rel_datatype, $rel_field) = $rel;
        
        if (!$rel_finder){
            $rel_finder = $this->_get_default_relation_finder($rel);
        }
        
        // e.g. $rel = array(fx_data::HAS_MANY, 'field', 'component_id');
        switch ($rel_type) {
            case self::BELONGS_TO:
                $rel_items = $rel_finder->where('id', $essences->get_values($rel_field))->all();
                $essences->attach($rel_items, $rel_field, $rel_name);
                break;
            case self::HAS_MANY:
                //echo fx_debug('has manu', $rel_finder);
                $rel_items = $rel_finder->where($rel_field, $essences->get_values('id'))->all();
                $essences->attach_many($rel_items, $rel_field, $rel_name);
                break;
            case self::HAS_ONE:
                break;
            case self::MANY_MANY:
                $end_rel = $rel[3];
                // removed to related entities
                // only with a non-empty field in which relate
                $end_rel_data = $rel_finder->relations();
                $end_rel_field = $end_rel_data[$end_rel][2];
                
                // $rel[4] is the data type for many-many
                $end_finder = null;
                if (isset($rel[4])) {
                    $end_rel_datatype = $rel[4];
                    $end_finder = fx::data($end_rel_datatype);
                }
                
                
                $rel_finder
                        ->with($end_rel, $end_finder)
                        ->where($rel_field, $essences->get_values('id'));
                if ($end_rel_field) {
                    $rel_finder->where($end_rel_field, 0, '!=');
                }
                $rel_items = $rel_finder->all()->find($end_rel, null, '!=');
                $essences->attach_many($rel_items, $rel_field, $rel_name, 'id', $end_rel);
                break;
        }
    }
    
    /*
     * Method adds related-entity to the collection
     * uses $this->with & $this->relations
     */
    protected function _add_relations(\Floxim\Floxim\System\Collection $essences) {
        if (count($this->with) == 0) {
            return;
        }
        if (count($essences) == 0) {
            return;
        }
        $relations = $this->relations();
        foreach ($this->with as $with) {
            list($rel_name, $rel_finder) = $with;
            if (!isset($relations[$rel_name])) {
                continue;
            }
            $this->add_related($rel_name, $essences, $rel_finder);
        }
    }
    
    public function __construct($table = null) {
        if (get_class($this) == 'Floxim\\Floxim\\System\\Data') {
            say(debug_backtrace());
            die();
        }
        if (!$table) {
            $class = get_class($this);
            if ($class[0] == '\\') {
                $class = substr($class, 1);
            }

            /**
             * vendor.module.component - finder component - \Vendor\Module\Component\[Name]\Finder
             * component - finder system component - \Floxim\Floxim\Component\[Name]\Finder
             */
            if (preg_match('#^Floxim\\\Floxim\\\Component\\\([\w]+)\\\Finder$#i', $class, $match)) {
                // component
                $table = fx::util()->camelToUnderscore($match[1]);
            } elseif(preg_match('#^([\w]+)\\\([\w]+)\\\Component\\\([\w]+)\\\Finder$#i', $class, $match)) {
                // vendor_module_component
                // todo: psr0 need verify
                $table = strtolower($match[1]).'_'.strtolower($match[2]).'_'.strtolower($match[3]);
            }
        }
        $this->table = $table;
    }
    
    public function get_tables() {
        return array($this->table);
    }
    
    /**
     * Get name of the table wich contains specified $column
     * @param string $column Column name
     * @param bool $validate Check if the column really exists (for one-table models)
     * @return string Table name
     */
    public function get_col_table($column, $validate = true) {
        $tables = $this->get_tables();
        
        if (count($tables) == 1 && !$validate) {
            return $tables[0];
        }
        
        foreach ($tables as $t) {
            $cols = $this->_get_columns($t);
            if (in_array($column, $cols)) {
                return $t;
            }
        }
        return null;
    }

    public function get_pk() {
        return $this->pk;
    }

    /**
     *
     * @param type $id
     * @return \Floxim\Floxim\System\Essence
     */
    public function get_by_id($id) {
        return $this->where('id', $id)->one();
    }
    
    /**
     * Get the objects on the list id
     * @param type $ids
     * @return array
     */
    public function get_by_ids($ids) {
        return $this->where('id', $ids)->all();
    }
    
    /**
     * To create a new essence instance, to fill in default values
     * @param array $data
     * @return \Floxim\Floxim\System\Essence
     */
    public function create($data = array()) {
        if ($data instanceof Form\Form) {
            $essence = $this->essence();
            $essence->load_from_form($data);
        } else {
            $essence = $this->essence($data);
        }
        return $essence;
    }
    
    /**
     * To initialize essence
     * @param array $data
     * @return \Floxim\Floxim\System\Essence
     */
    public function essence($data = array()) {
        $classname = $this->get_class_name($data);
        if (!class_exists($classname)) {
            say($this);
        }
        $obj = new $classname(array('data' => $data));
        // todo: psr0 verify
        if ($classname == '\\Floxim\\Floxim\\System\\Simplerow') {
            $obj->table = $this->table;
        }
        return $obj;
    }

    public function insert($data) {
        $set = $this->_set_statement($data);
        if ($set) {
            fx::db()->query("INSERT INTO `{{".$this->table."}}` SET ".join(",", $set));
            $id = fx::db()->insert_id();
        }

        return $id;
    }

    public function update($data, $where = array()) {
        $wh = array();
        $update = $this->_set_statement($data);
        
        foreach ($where as $k => $v) {
            $wh[] = "`".fx::db()->escape($k)."` = '".fx::db()->escape($v)."' ";
        }
        
        if ($update) {
            fx::db()->query(
                "UPDATE `{{".$this->table."}}` SET ".join(',', $update)." ".
                        ( $wh ? "\n WHERE ".join(' AND ', $wh) : "")." "
            );
        }
    }

    public function delete() {
        $argc = func_num_args();
        if ($argc === 0) {
            $this->all()->apply(function($i) {
                $i->delete();
            });
            return;
        }
        
        $argv = func_get_args();

        $where = array();
        for ($i = 0; $i < $argc; $i = $i + 2) {
            $where[] = "`".$argv[$i]."` = '".fx::db()->escape($argv[$i + 1])."'";
        }
        if ($where) {
            $where = "\n WHERE ".join(" AND ", $where);
        }

        fx::db()->get_results("DELETE FROM `{{".$this->table."}}`".$where);
    }

    public function get_parent($item) {
        $id = $item;
        if ($item instanceof Essence || is_array($item)) {
            $id = $item['parent_id'];
        }

        return $this->get_by_id($id);
    }

    public function next_priority() {
        return fx::db()->get_var("SELECT MAX(`priority`)+1 FROM `{{".$this->table."}}`");
    }
    
    /**
     * Get the name of the class to essence
     * @param array $data data essence'and
     * @return string
     */
    public function get_class_name() {
        $class = explode("\\", get_class($this));
        $class[count($class)-1]= 'Essence';
        $class = join("\\", $class);
        return $class;
        // todo: psr0 need fix
        $classname = 'fx_'.str_replace('fx_data_', '', get_class($this));
        try {
            if (class_exists($classname)) {
                return $classname;
            }
        } catch (Exception $e) {}
        return '\\Floxim\\Floxim\\System\\Simplerow';
    }
    
    protected function _get_columns($table = null) {
        if (!$table) {
            $table = $this->table;
        }
        $cache_key = 'table_columns_'.$table;
        if ( ($columns = fx::cache($cache_key)) ) {
            return $columns;
        }
        $columns = fx::db()->get_col('SHOW COLUMNS FROM {{'.$table.'}}', 0);
        fx::cache($cache_key, $columns);
        return $columns;
    }

    protected function _set_statement($data) {
        
        $cols = $this->_get_columns();
        
        $set = array();

        foreach ($data as $k => $v) {
            if (!in_array($k, $cols)) {
                continue;
            }
            if (in_array($k, $this->serialized) && is_array($v)) {
                $v = serialize($v);
            }
            $str = "'".fx::db()->escape($v)."' ";
            if (isset($this->sql_function[$k])) {
                $str = $this->sql_function[$k]."(".$str.")";
            }

            $set[] = "`".fx::db()->escape($k)."` = ".$str;
        }

        return $set;
    }
}