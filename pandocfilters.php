<?php

/**
 * Class Pandoc_Filter
 * 
 * Methods to aid writing PHP scripts that process
 * the pandoc AST serialized JSON
 * 
 * Ported from https://github.com/jgm/pandocfilters/blob/master/pandocfilters.py
 */
class Pandoc_Filter
{
    public static $source = 'php://stdin';
    
    /**
     * Walk a tree, applying an action to every object.
     * Returns a modified tree.
     */
    public static function walk($x, $action, $format, $meta)
    {
        if (is_array($x)) {
            $array = array();
            foreach ($x as $item) {
                if (is_object($item) && isset($item->t)) {
                    $res = $action($item->t, $item->c, $format, $meta);
                    if (is_null($res)) {
                        $array[] = self::walk($item, $action, $format, $meta);
                    } elseif (is_array($res)) {
                        foreach ($res as $z) {
                            $array[] = self::walk($z, $action, $format, $meta);
                        }
                    } elseif (is_object($res)) {
                        $array[] = self::walk($res, $action, $format, $meta);
                    } else {
                        $obj = clone $item;
                        $obj->c = "$res";
                        $array[] = $obj;
                    }
                } else {
                    $array[] = self::walk($item, $action, $format, $meta);
                }
            }
            return $array;
        } elseif (is_object($x)) {
            $obj = clone $x;
            foreach (get_object_vars($x) as $k => $v) {
                $obj->{$k} = self::walk($v, $action, $format, $meta);
            }
            return $obj;
        } else {
            return $x;
        }
    }

    /**
     * Converts an action into a filter that reads a JSON-formatted
     * pandoc document from stdin, transforms it by walking the tree
     * with the action, and returns a new JSON-formatted pandoc document
     * to stdout.
     * The argument is a function action(key, value, format, meta),
     * where key is the type of the pandoc object (e.g. 'Str', 'Para'),
     * value is the contents of the object (e.g. a string for 'Str',
     * a list of inline elements for 'Para'), format is the target
     * output format (which will be taken for the first command line
     * argument if present), and meta is the document's metadata.
     * If the function returns NULL, the object to which it applies
     * will remain unchanged. If it returns an object, the object will
     * be replaced. If it returns a list, the list will be spliced in to
     * the list to which the target object belongs. (So, returning an
     * empty list deletes the object.)
     * 
     * @param $action
     */
    public static function toJSONFilter($action)
    {
        
        $doc = json_decode(file_get_contents(self::$source));
        if (count($GLOBALS['argv']) > 1) {
            $format = $GLOBALS['argv'][1];
        } else {
            $format = '';
        }
        $altered = self::walk($doc, $action, $format, $doc[0]->unMeta);
        $json = json_encode($altered, JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        
        echo $json . PHP_EOL;
    }

    /**
     * Walks the tree x and returns concatenated string content,
     * leaving out all formatting.
     * 
     * @param $x
     * @return string
     */
    public static function stringify($x)
    {
        $result = array();
        $go = function($key, $val, $format, $meta) use ($result) {
            if ('Str' == $key) {
                $result[] = $val;
            } elseif ('Code' == $key) {
                $result[] = $val[1];
            } elseif ('Math' == $key) {
                $result[] = $val[1];
            } elseif ('LineBreak' == $key) {
                $result[] = " ";
            } elseif ('Space' == $key) {
                $result[] = " ";
            }
        };
        self::walk($x, $go, '', array());
        return implode('', $result);
    }
    
    /**
     * Returns an attribute list, constructed from the
     * attrs array.
     * 
     * @param object $attrs
     * @return array
     */
    public function attributes($attrs)
    {
        $ident = @$attrs->id ?: '';
        $classes = @$attrs->classes ?: array();
        $keyvals = array();
        foreach (get_object_vars($attrs) as $k => $v) {
            if ('id' != $k && 'classes' != $k) {
                $keyvals[$k] = $v;
            }
        }
        return array($ident, $classes, $keyvals);
    }
    
    /**
     * @param string $eltType
     * @param int $numArgs
     * @return callable
     */
    public function elt($eltType, $numArgs)
    {
        $fun = function() use ($eltType, $numArgs) {
            $lenargs = func_num_args();
            if ($lenargs != $numArgs) {
                throw new BadMethodCallException(sprintf(
                    "%s expects %d arguments, but given %d", $eltType, $numArgs, $lenargs
                ));
            }
            if ($lenargs == 1) {
                $xs = func_get_arg(0);
            } else {
                $xs = func_get_args();
            }
            return (object) array('t' => $eltType, 'c' => $xs);
        };
        return $fun;
    }
}
