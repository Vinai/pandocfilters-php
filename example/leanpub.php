#!/usr/bin/env php
<?php

/**
 * Pandoc filter providing leanpub block emulation.
 *
 * Supported block types: D>, E>, X>, I>, Q>, T>, W>
 * Supported output formats are latex, html and epub.
 * For LaTeX (and PDF) the corresponding \lp... commands
 * have to be added to the .latex template (please refer
 * to the issue comment link below for an example).
 *
 * Example markdown:
 *
 * W> This is a **warning**
 * W> that will be _displayed_ in its
 * W>
 * W> own block containing other inline `elements`.
 *
 * Issue comment containing the leanpub LaTeX:
 * https://github.com/Vinai/pandocfilters-php/issues/2#issuecomment-47204574
 *
 * @author Vinai Kopp
 * @contributor Gary Jones
 * @copyright Copyright (c) 2014, Vinai Kopp
 * @license http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 */

namespace Pandocfilters\Leanpub;

// Use project specific pandocfilters.php if present
if (file_exists(__DIR__ . '/pandocfilters.php')) {
    require_once __DIR__ . '/pandocfilters.php';
} else {
    require_once __DIR__ . '/../pandocfilters.php';
}

/**
 * Register Block Types
 *
 * @var BlockEmulation[] $blocks
 */
$blocks[] = new BlockEmulation('D>', array(
    'latex' => array('\\lpdiscussion{', '}'),
    'epub' => array('<div class="lpdiscussion">', '</div>'),
    'html' => array('<div class="lpdiscussion">', '</div>'),
));
$blocks[] = new BlockEmulation('E>', array(
    'latex' => array('\\lperror{', '}'),
    'epub' => array('<div class="lperror">', '</div>'),
    'html' => array('<div class="lperror">', '</div>'),
));
$blocks[] = new BlockEmulation('X>', array(
    'latex' => array('\\lpexercise{', '}'),
    'epub' => array('<div class="lpexercise">', '</div>'),
    'html' => array('<div class="lpexercise">', '</div>'),
));

$blocks[] = new BlockEmulation('I>', array(
    'latex' => array('\\lpinformation{', '}'),
    'epub' => array('<div class="lpinformation">', '</div>'),
    'html' => array('<div class="lpinformation">', '</div>'),
));

$blocks[] = new BlockEmulation('Q>', array(
    'latex' => array('\\lpquestion{', '}'),
    'epub' => array('<div class="lpquestion">', '</div>'),
    'html' => array('<div class="lpquestion">', '</div>'),
));

$blocks[] = new BlockEmulation('T>', array(
    'latex' => array('\\lptip{', '}'),
    'epub' => array('<div class="lptip">', '</div>'),
    'html' => array('<div class="lptip">', '</div>'),
));

$blocks[] = new BlockEmulation('W>', array(
    'latex' => array('\\lpwarning{', '}'),
    'epub' => array('<div class="lpwarning">', '</div>'),
    'html' => array('<div class="lpwarning">', '</div>'),
));

/**
 * Class BlockEmulation
 * @package Pandocfilters\Leanpub
 */
class BlockEmulation
{
    private $name;
    private $formats;

    /**
     * Leanpub Block Type
     *
     * @param string $name Block Identifier, e.g. 'W>'
     * @param array $formats List of opening and closing RawBlock contents by format
     */
    public function __construct($name, array $formats) {
        $this->name = $name;
        $this->formats = $formats;
    }

    public function matches($value) {
        return is_object($value) && $value->t === 'Str' && $value->c === $this->name;
    }

    public function matchesBlockElement($type, $value) {
        if ('Para' == $type && count($value) >= 2) {
            return $this->matches($value[0]) && 'Space' === $value[1]->t;
        }
        return false;
    }

    public function matchesSpace($value) {
        return is_object($value) && $value->t === 'Space';
    }

    public function open($format) {
        return @$this->formats[$format][0] ? : '';
    }

    public function close($format) {
        return @$this->formats[$format][1] ? : '';
    }

    public function replaceSelf(array $value, $replacement) {
        foreach ($value as $k => $v) {
            if ($this->matches($v)) $value[$k] = $replacement;
        }
        return $value;
    }

    public function getLines(array $value) {
        $lines = array();
        $lnum = -1;
        foreach ($value as $item) {
            if ($this->matches($item)) {
                $lnum++; // start new line, also leave out the block identifier
            } elseif (! isset($lines[$lnum]) && $this->matchesSpace($item)){
                // leave out spaces at the beginning of the line
            } else {
                $lines[$lnum][] = $item;
            }
        }
        return $lines;
    }
}

/**
 * @param callable $prev
 * @param callable $current
 * @global callable $Header Header Element Factory
 * @return bool
 */
$isContextComplete = function($prev, $current)
use ($Header){
    if ($prev && $prev !== $current) {
        return true;
    }
    if ($Header === $prev) {
        return true;
    }
    return false;
};

/**
 * @param array $line
 * @param callable $default
 * @global callable $BulletList Bullet List Element Factory
 * @global callable $Header Header Element Factory
 * @return callable
 */
$getLineContext = function (array $line, $default)
use($BulletList, $Header) {
    if ($line[0]->t == 'Str' && $line[0]->c == '*' && (! isset($line[1]->c) || $line[1]->t == 'Space')) {
        return $BulletList;
    }
    if ($line[0]->t == 'Str' && $line[0]->c == '#' && (! isset($line[1]->c) || $line[1]->t == 'Space')) {
        return $Header;
    }
    return $default;
};

/**
 * @param array $line
 * @param string $context
 * @global callable $LineBreak Linebreak Element Factory
 * @global callable $Para Paragraph Element Factory
 * @global callable $RawBlock Raw Block Element Factory
 * @global callable $BulletList Bullet List Element Factory
 * @global callable $Plain Plain Element Factory (Non-Paragraph String)
 * @global callable $Header Header Element Factory
 * @return array|object
 */
$processLineInContext = function(array $line, $context)
use ($LineBreak, $Para, $RawBlock, $BulletList, $Plain, $Header) {
    switch ($context) {
        case $Para:
        case $Plain:
            $line[] = $LineBreak();
            break;
        case $BulletList:
            $line = array_slice($line, 2);
            $line = array($Plain($line));
            break;
        case $Header:
            $line = array_slice($line, 2);
            $anchor = \Pandoc_Filter::stringify($line);
            $anchor = trim(preg_replace('/[^0-9a-z]/', '-', strtolower($anchor)), '-');
            $line = $Header(2, array($anchor, array(), array()), $line);
            break;
    }
    return $line;
};

/**
 * @param callable $context
 * @param array $content
 * @global callable $Para Para Element Factory
 * @global callable $BulletList Bullet List Element Factory
 * @global callable $Header Header Element Factory
 * @global callable $Plain Plain Element Factory
 * @return object|bool
 */
$closeContext = function($context, array $content)
use ($Para, $BulletList, $Header, $Plain) {
    switch ($context) {
        case $Plain:
        case $Para:
            // Merge all Para content into one array (with LineBreaks)
            $c = array();
            foreach ($content as $line) {
                $c = array_merge($c, $line);
            }
            return $context(array_slice($c, 0, -1));
        case $BulletList: return $context($content);
        case $Header: return $content[0];
    }
};

/**
 * Variables declared in pandocfilters.php and a few closures from this file.
 *
 * @global BlockEmulation[] $blocks List of leanpub block definitions instances
 * @global callable $getLineContext Return context callable for current line
 * @global callable $processLineInContext Process line according to context
 * @global callable $isContextComplete Return true if current context needs closing
 * @global callable $closeContext Close current context and return element
 * @global callable $RawBlock Raw Block Element Factory
 * @global callable $Plain Default Block Element Factory (Para or Plain)
 */
\Pandoc_Filter::toJSONFilter(function ($type, $value, $format, $meta)
use ($blocks, $getLineContext, $processLineInContext, $isContextComplete, $closeContext, $RawBlock, $Para) {

    // default format, mainly for testing
    if (!$format) $format = 'latex';
    $defaultContext = $Para;

    foreach ($blocks as $block) {
        if ($block->matchesBlockElement($type, $value)) {
            $res[] = $RawBlock($format, $block->open($format));
            $ccontext = ''; // current block element context
            $clines = array(); // lines in current block

            foreach ($block->getLines($value) as $line) {
                $lcontext = $getLineContext($line, $defaultContext);
                if ($isContextComplete($ccontext, $lcontext) && $clines) {
                    if ($elt = $closeContext($ccontext, $clines)) {
                        $res[] = $elt;
                    }
                    $clines = array();
                }
                $clines[] = $processLineInContext($line, $lcontext);
                $ccontext = $lcontext;
            }
            if ($clines && ($elt = $closeContext($ccontext, $clines))) {
                $res[] = $elt;
            }
            $res[] = $RawBlock($format, $block->close($format));
            return $res;
        }
    }
});
