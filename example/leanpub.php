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

    public function matchesElement($type, $value) {
        if ('Para' == $type && count($value) >= 2) {
            return $this->matches($value[0]) && 'Space' === $value[1]->t;
        }
        return false;
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
}

// Pandoc Block Factories
$LineBreak = \Pandoc_Filter::elt('LineBreak', 0);
$Para = \Pandoc_Filter::elt('Para', 1);
$RawBlock = \Pandoc_Filter::elt('RawBlock', 2);

\Pandoc_Filter::toJSONFilter(function ($type, $value, $format, $meta)
use ($blocks, $LineBreak, $Para, $RawBlock) {

    // default format, mainly for testing
    if (!$format) $format = 'latex';

    foreach ($blocks as $block) {
        if ($block->matchesElement($type, $value)) {
            $res[] = $RawBlock($format, $block->open($format));
            $res[] = $Para($block->replaceSelf(array_slice($value, 2), $LineBreak()));
            $res[] = $RawBlock($format, $block->close($format));
            return $res;
        }
    }
});