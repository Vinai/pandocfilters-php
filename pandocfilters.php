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
     * 
     * @param array|object|string $x
     * @param callable $action
     * @param mixed $format
     * @param mixed $meta
     * @return array|object|string
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
     * @param callable $action
     * @param string $source (For debugging purposes)
     */
    public static function toJSONFilter($action, $source = null)
    {
        if (! $source) $source = self::$source;
        $doc = json_decode(file_get_contents($source));
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
     * @param array|object|string $x
     * @return string
     */
    public static function stringify($x)
    {
        $o = (object) array('result' => array());
        $go = function($key, $val, $format, $meta) use ($o) {
            if ('Str' == $key) {
                $o->result[] = $val;
            } elseif ('Code' == $key) {
                $o->result[] = $val[1];
            } elseif ('Math' == $key) {
                $o->result[] = $val[1];
            } elseif ('LineBreak' == $key) {
                $o->result[] = " ";
            } elseif ('Space' == $key) {
                $o->result[] = " ";
            }
        };
        self::walk($x, $go, '', array());
        return implode('', $o->result);
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
     * @throws BadMethodCallException
     */
    public static function elt($eltType, $numArgs)
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

# Constructors for block elements

$Plain = Pandoc_Filter::elt('Plain',1);
$Para = Pandoc_Filter::elt('Para',1);
$CodeBlock = Pandoc_Filter::elt('CodeBlock',2);
$RawBlock = Pandoc_Filter::elt('RawBlock',2);
$BlockQuote = Pandoc_Filter::elt('BlockQuote',1);
$OrderedList = Pandoc_Filter::elt('OrderedList',2);
$BulletList = Pandoc_Filter::elt('BulletList',1);
$DefinitionList = Pandoc_Filter::elt('DefinitionList',1);
$Header = Pandoc_Filter::elt('Header',3);
$HorizontalRule = Pandoc_Filter::elt('HorizontalRule',0);
$Table = Pandoc_Filter::elt('Table',5);
$Div = Pandoc_Filter::elt('Div',2);
$Null = Pandoc_Filter::elt('Null',0);

# Constructors for inline elements

$Str = Pandoc_Filter::elt('Str',1);
$Emph = Pandoc_Filter::elt('Emph',1);
$Strong = Pandoc_Filter::elt('Strong',1);
$Strikeout = Pandoc_Filter::elt('Strikeout',1);
$Superscript = Pandoc_Filter::elt('Superscript',1);
$Subscript = Pandoc_Filter::elt('Subscript',1);
$SmallCaps = Pandoc_Filter::elt('SmallCaps',1);
$Quoted = Pandoc_Filter::elt('Quoted',2);
$Cite = Pandoc_Filter::elt('Cite',2);
$Code = Pandoc_Filter::elt('Code',2);
$Space = Pandoc_Filter::elt('Space',0);
$LineBreak = Pandoc_Filter::elt('LineBreak',0);
$Math = Pandoc_Filter::elt('Math',2);
$RawInline = Pandoc_Filter::elt('RawInline',2);
$Link = Pandoc_Filter::elt('Link',2);
$Image = Pandoc_Filter::elt('Image',2);
$Note = Pandoc_Filter::elt('Note',1);
$Span = Pandoc_Filter::elt('Span',2);
