<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Template;

class Collection implements \ArrayAccess, \IteratorAggregate, \Countable
{

    /**
     * The data array itself
     * @type array
     */
    protected $data = array();

    /**
     * Array of filteres applied to the collection
     * @type array
     */
    public $filtered_by = array();

    /**
     * Array of sorters apply to the collection
     * @type array
     */
    public $sorted_by = array();

    /**
     * Link to the finder (fx_data_*) instance which created this collection
     * @type fx_data
     */
    public $finder = null;

    public function fork($data = array())
    {
        $collection = new Collection($data);
        $collection->is_sortable = $this->is_sortable;
        $collection->finder = $this->finder;
        return $collection;
    }
    
    public function copy() 
    {
        return clone $this;
    }

    protected function load($data)
    {
        $this->data = $data;
        return $this;
    }

    public function addFilter($field, $value)
    {
        $this->filtered_by[] = array($field, $value);
    }

    public function getFilters()
    {
        return $this->filtered_by;
    }

    public function __construct($data = array())
    {
        if (is_array($data)) {
            $this->data = $data;
        } else {
            $this->data = array($data);
        }
    }

    public function __get($name)
    {
        if ($name == 'length') {
            return $this->count();
        }
    }

    public function count()
    {
        return count($this->data);
    }

    public function getData()
    {
        return $this->data;
    }

    /*
     * Get the first element of the collection
     */
    public function first()
    {
        reset($this->data);
        return current($this->data);
    }

    public function next()
    {
        return next($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function last()
    {
        return end($this->data);
    }

    /**
     * Set internal array pointer to the specified key
     * @param string $key
     * @return Collection Returns collection itself
     */
    public function setPosition($key)
    {
        reset($this->data);
        while (key($this->data) != $key) {
            next($this->data);
        }
        return $this;
    }

    /*
     * Creates a new collection with the results
     * $collection->find('price', '10', '>')
     */
    public function find($field, $prop = null, $compare_type = null)
    {
        $fork = $this->fork();
        if (count($this->data) == 0) {
            //return new Collection();
            return $fork;
        }
        if (is_null($prop)) {
            $compare_type = is_callable($field) ? self::FILTER_CALLBACK : self::FILTER_EXISTS;
        } elseif (is_null($compare_type)) {
            if (is_array($prop)) {
                $compare_type = self::FILTER_IN;
            } else {
                $compare_type = self::FILTER_EQ;
            }
        } elseif ($compare_type == '!=') {
            $compare_type = self::FILTER_NEQ;
        }
        $initial_key = key($this->data);
        $res = array();
        if ($compare_type == self::FILTER_EQ) {
            foreach ($this->data as $item) {
                if ($item[$field] == $prop) {
                    $res [] = $item;
                }
            }
            $this->setPosition($initial_key);
            return $fork->load($res);
        }
        if ($compare_type == self::FILTER_NEQ) {
            foreach ($this->data as $item) {
                if ($item[$field] != $prop) {
                    $res [] = $item;
                }
            }
            $this->setPosition($initial_key);
            return $fork->load($res);
        }
        if ($compare_type == self::FILTER_IN) {
            foreach ($this->data as $item) {
                if (in_array($item[$field], $prop)) {
                    $res [] = $item;
                }
            }
            $this->setPosition($initial_key);
            return $fork->load($res);
        }
        if ($compare_type == self::FILTER_EXISTS) {
            foreach ($this->data as $item) {
                if (isset($item[$field]) && $item[$field]) {
                    $res [] = $item;
                }
            }
            $this->setPosition($initial_key);
            return $fork->load($res);
        }
        if ($compare_type == self::FILTER_CALLBACK) {
            foreach ($this->data as $item) {
                if (call_user_func($field, $item)) {
                    $res [] = $item;
                }
            }
            $this->setPosition($initial_key);
            return $fork->load($res);
        }
        $this->setPosition($initial_key);
        return $fork->load($res);
    }

    public function findOne($field, $prop = null, $compare_type = null)
    {
        if (count($this->data) == 0) {
            return false;
        }
        if (is_null($prop)) {
            $compare_type = is_callable($field) ? self::FILTER_CALLBACK : self::FILTER_EXISTS;
        } elseif (is_null($compare_type)) {
            if (is_array($prop)) {
                $compare_type = self::FILTER_IN;
            } else {
                $compare_type = self::FILTER_EQ;
            }
        }
        $initial_key = key($this->data);
        if ($compare_type === self::FILTER_EQ) {
            if (isset($this->unique_indexes[$field])) {
                if (!isset($this->unique_indexes[$field][$prop])) {
                    return false;
                }
                return $this->unique_indexes[$field][$prop];
            }
            foreach ($this->data as $item) {
                if ($item[$field] == $prop) {
                    $this->setPosition($initial_key);
                    return $item;
                }
            }
            $this->setPosition($initial_key);
            return false;
        }
        if ($compare_type == self::FILTER_NEQ) {
            foreach ($this->data as $item) {
                if ($item[$field] != $prop) {
                    $this->setPosition($initial_key);
                    return $item;
                }
            }
            $this->setPosition($initial_key);
            return false;
        }
        if ($compare_type == self::FILTER_IN) {
            foreach ($this->data as $item) {
                if (in_array($item[$field], $prop)) {
                    $this->setPosition($initial_key);
                    return $item;
                }
            }
            $this->setPosition($initial_key);
            return false;
        }
        if ($compare_type == self::FILTER_EXISTS) {
            foreach ($this->data as $item) {
                if (isset($item[$field]) && $item[$field]) {
                    $this->setPosition($initial_key);
                    return $item;
                }
            }
            $this->setPosition($initial_key);
            return false;
        }
        if ($compare_type == self::FILTER_CALLBACK) {
            foreach ($this->data as $item) {
                if (call_user_func($field, $item)) {
                    $this->setPosition($initial_key);
                    return $item;
                }
            }
            $this->setPosition($initial_key);
            return false;
        }
        $this->setPosition($initial_key);
        return false;
    }
    
    protected $unique_indexes = array();
    public function indexUnique($field, $use_first = true)
    {
        $index = array();
        foreach ($this->data as $item) {
            $val = $item[$field];
            if (!$use_first || !isset($index[$val])) {
                $index[$val] = $item;
            }
        }
        $this->unique_indexes[$field] = $index;
        return $this;
    }
    
    public function setKeyField($field) 
    {
        $new_data = array();
        foreach ($this->data as $item) {
            $new_data[$item[$field]] = $item;
        }
        $this->data = $new_data;
        return $this;
    }
    
    const FILTER_EQ = 1;
    const FILTER_EXISTS = 2;
    const FILTER_CALLBACK = 3;
    const FILTER_IN = 4;
    const FILTER_NEQ = 5;

    public function unique($field = null)
    {
        $res = array();
        if (is_null($field) && $this->first() instanceof Entity) {
            $field = 'id';
        }
        if (!is_null($field)) {
            foreach ($this->data as $item) {
                $res[$item[$field]] = $item;
            }
            $this->data = $res;
            return $this;
        }

        $this->data = array_unique($this->data);
        return $this;
    }


    /*
     * Sorts the current collection
     * $c->sort('id')
     * $c->sort(function($a,$b) {})
     */
    public function sort($sorter)
    {
        @ uasort($this->data, $sorter);
        return $this;
    }

    public function slice($offset, $length = null)
    {
        $collection = $this->fork(array_slice($this->data, $offset, $length));
        return $collection;
    }

    public function eq($offset)
    {
        return $this->slice($offset, 1)->first();
    }

    /**
     * Get new collection groupped by $groupper argument
     * If the $grupper
     * @param int|string|callable $groupper
     * @param bool $force_property Force to handle $groupper as property name
     * @return Collection
     */
    public function group($groupper, $force_property = false)
    {
        $res = new Collection();
        $initial_key = key($this->data);
        if (!$force_property) {
            if (is_numeric($groupper)) {
                $c = 0;
                $r = 0;
                foreach ($this as $item) {
                    if ($c % $groupper == 0) {
                        $r++;
                    }
                    if (!isset($res[$r])) {
                        $res[$r] = new Collection();
                    }
                    $res[$r] [] = $item;
                    $c++;
                }
                $this->setPosition($initial_key);
                return $res;
            }
            if (is_callable($groupper)) {
                foreach ($this as $item) {
                    $key = call_user_func($groupper, $item);
                    if (!isset($res[$key])) {
                        $res[$key] = new Collection();
                    }
                    $res[$key] [] = $item;
                }
                $this->setPosition($initial_key);
                return $res;
            }
        }
        if (is_string($groupper)) {
            $modifiers = array();
            if (preg_match("~\|~", $groupper)) {

                $groupper_parts = explode("|", $groupper, 2);
                $groupper = trim($groupper_parts[0]);

                $p = new Template\ModifierParser();
                $parsed_modifiers = $p->parse('|' . $groupper_parts[1]);
                if ($parsed_modifiers) {
                    foreach ($parsed_modifiers as $pmod) {
                        $callback = $pmod['name'];
                        $args = $pmod['args'];
                        if (!is_callable($callback)) {
                            continue;
                        }
                        $self_key = array_keys($args, "self");
                        if (isset($self_key[0])) {
                            $self_key = $self_key[0];
                        } else {
                            array_unshift($args, '');
                            $self_key = 0;
                        }
                        foreach ($args as &$arg_v) {
                            $arg_v = trim($arg_v, '"\'');
                        }
                        $modifiers [] = array(
                            $callback,
                            $args,
                            $self_key
                        );
                    }
                }
            }
            foreach ($this as $item) {
                $key = $item[$groupper];
                if (is_null($key)) {
                    $key = '';
                } else {
                    foreach ($modifiers as $mod) {
                        $callback = $mod[0];
                        $self_key = $mod[2];
                        $args = $mod[1];
                        $args[$self_key] = $key;
                        $key = call_user_func_array($callback, $args);
                    }
                }

                if ($key instanceof Entity) {
                    $key_index = $key['id'];
                } else {
                    $key_index = $key;
                }
                if (!isset($res[$key_index])) {
                    $group_collection = new Collection();
                    $group_collection->is_sortable = $this->is_sortable;
                    $group_collection->group_key = $key;
                    $res[$key_index] = $group_collection;
                }
                $res[$key_index] [] = $item;
            }
            $this->setPosition($initial_key);
            return $res;
        }
        $this->setPosition($initial_key);
    }

    /*
     * To apply a function to all elements
     */
    public function apply($callback)
    {
        $initial_key = key($this->data);
        foreach ($this->data as &$di) {
            call_user_func_array($callback, array(&$di));
        }
        $this->setPosition($initial_key);
        return $this;
    }

    /*
     * Remove element from collection
     */
    public function remove($item)
    {
        foreach ($this->data as $dk => $di) {
            if ($item === $di) {
                unset($this->data[$dk]);
                return;
            }
        }
    }

    public function reverse($preserve_keys = true)
    {
        $this->data = array_reverse($this->data, $preserve_keys);
        return $this;
    }


    /*
     * Find elemenets and remove them from the collection
     */
    public function findRemove($field, $prop = null, $compare_type = null)
    {
        $items = $this->find($field, $prop, $compare_type);
        foreach ($items as $i) {
            $this->remove($i);
        }
        return $this;
    }

    // alias for get_values()
    public function column($field, $key_field = null, $as_collection = true)
    {
        return $this->getValues($field, $key_field, $as_collection);
    }
    
    /**
     * Extract values ready to use for select input: array( array(id, name), array(id1, name1) )
     * @param string $id_prop
     * @param string $name_prop
     */
    public function getSelectValues($id_prop, $name_prop) 
    {
        $vals = $this->getValues(array($id_prop, $name_prop));
        foreach ($vals as &$set) {
            $set = array_values($set);
        }
        return $vals;
    }

    public function getValues($field, $key_field = null, $as_collection = false)
    {
        $result = array();
        if ($field instanceof \Closure) {
            foreach ($this->data as $k => $v) {
                $res_key = $key_field ? $v[$key_field] : $k;
                $result[$res_key] = call_user_func($field, $v, $k);
            }
            if ($as_collection) {
                $result = new Collection($result);
            }
            return $result;
        }
        if (is_array($field)) {
            foreach ($field as $fd) {
                foreach ($this->data as $k => $v) {
                    $res_key = $key_field ? $v[$key_field] : $k;
                    if ((is_array($v) || $v instanceof \ArrayAccess) && isset($v[$fd])) {
                        $result[$res_key][$fd] = $v[$fd];
                    } elseif (is_object($v) && isset($v->$fd)) {
                        $result[$res_key][$fd] = $v->$fd;
                    } else {
                        $result[$res_key][$fd] = null;
                    }
                }
            }

            if ($as_collection) {
                $result = new Collection($result);
            }
            return $result;
        }
        foreach ($this->data as $k => $v) {
            $res_key = $key_field ? $v[$key_field] : $k;
            if ((is_array($v) || $v instanceof \ArrayAccess) && isset($v[$field])) {
                $result[$res_key] = $v[$field];
            } elseif (is_object($v) && isset($v->$field)) {
                $result[$res_key] = $v->$field;
            } else {
                $result[$res_key] = null;
            }
        }
        if ($as_collection) {
            $result = new Collection($result);
        }
        return $result;
    }

    /*
     * $users = fx::data("user")->all();
     * $posts = fx::data("post")->all();
     * $user['posts'] = Collection(1,2,3);
     * $users->attach_many($posts, 'author_id', 'posts');
     * 
     * $post['author'] = $user;
     * $posts->attach($users, 'this.creator_id=author.user_id')
     */
    public function attach(Collection $what, $cond_field, $res_field = null, $check_field = 'id')
    {
        if ($res_field === null) {
            $res_field = preg_replace("~_id$~", '', $cond_field);
        }

        $res_index = array();
        foreach ($what as $what_item) {
            $res_index[$what_item[$check_field]] = $what_item;
        }
        foreach ($this as $our_item) {
            $index_val = $our_item[$cond_field];
            $our_item[$res_field] = isset($res_index[$index_val]) ? $res_index[$index_val] : null;
        }
        return $this;
    }


    /**
     * For each $item of the current collection find corresponging $other from $what collection,
     * which have $cond_field equal to $item[$check_field]
     * and append them to $item[$res_field] as a new collection.
     * If $extract_field argument is specified, $other[$extract_field] is used instead if $other
     * @param Collection $what
     * @param string $cond_field
     * @param string $res_field
     * @param string $check_field
     * @param string $extract_field
     * @return \Floxim\Floxim\System\Collection
     */
    public function attachMany(
        Collection $what,
        $cond_field,
        $res_field,
        $check_field = 'id',
        $extract_field = null
    ) {
        // what = [post1,post2]
        // this = [user1, user2]
        // cond_field = 'author'
        // res_field = 'posts'


        $res_index = array();
        foreach ($what as $what_item) {
            $index_key = $what_item[$cond_field];
            if (!isset($res_index[$index_key])) {
                $new_collection = $what->fork();
                $new_collection->addFilter($cond_field, $index_key);
                $res_index[$index_key] = $new_collection;
                if ($extract_field) {
                    $res_index[$index_key]->linker_map = new Collection();
                }
            }
            if (!$extract_field) {
                $res_index[$index_key] [] = $what_item;
            } else {
                $end_value = $what_item[$extract_field];
                $res_index[$index_key][] = $end_value;
                $res_index[$index_key]->linker_map[] = $what_item;
            }
            //$res_index[$index_key][]= $extract_field ? $what_item[$extract_field] : $what_item;
        }
        foreach ($this as $our_item) {
            $check_value = $our_item[$check_field];
            $our_item[$res_field] = isset($res_index[$check_value]) ?
                $res_index[$check_value] : fx::collection();
        }
        return $this;
    }

    public function concat($collection)
    {
        if (!is_array($collection) && !($collection instanceof \Traversable)) {
            return $this;
        }
        foreach ($collection as $item) {
            $this[] = $item;
        }
        return $this;
    }

    public function add()
    {
        $this->concat(func_get_args());
        return $this;
    }

    public function makeTree($parent_field = 'parent_id', $children_field = 'nested', $id_field = 'id')
    {
        $index_by_parent = array();

        foreach ($this as $item) {
            $pid = $item[$parent_field];
            if (!isset($index_by_parent[$pid])) {
                $index_by_parent[$pid] = fx::collection();
                $index_by_parent[$pid]->is_sortable = $this->is_sortable;
            }
            $index_by_parent[$pid] [] = $item;
        }
        foreach ($this as $item) {
            if (isset($index_by_parent[$item[$id_field]])) {
                $item[$children_field] = $index_by_parent[$item[$id_field]];
                $this->findRemove(
                    $id_field,
                    $index_by_parent[$item[$id_field]]->getValues($id_field)
                );
            } else {
                $item[$children_field] = null;
            }
        }
        return $this;
    }

    /* IteratorAggregate */

    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    public function set($offset, $value)
    {
        if (is_null($offset)) {
            $this->data [] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function keys()
    {
        return array_keys($this->data);
    }


    /* Array access */

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
}
