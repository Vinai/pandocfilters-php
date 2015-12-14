#!/usr/bin/env php
<?php

/**
 * Performs manipulations of Markdown text to help make a manuscript.
 *
 * Requirements
 *
 * The file pandocfilters.php must be in the current or the parent directory.
 *
 * The code in this file is considered public domain, thanks to the author Dave Jarvis.
 */

// Use project specific pandocfilters.php if present
if (file_exists(__DIR__ . '/pandocfilters.php')) {
    require_once __DIR__ . '/pandocfilters.php';
} else {
    require_once __DIR__ . '/../pandocfilters.php';
}


Pandoc_Filter::toJSONFilter(function ($key, $value, $format, $meta)
use ($Str, $Header) {

    if ($key === 'Image') {
        // Images are not allowed inside manuscripts.
        return $Str('');
    } else {
        if ($key === 'Link') {
            // Extract the hyperlink text, discard the URL.
            return $Str(Pandoc_Filter::stringify($value));
        } else {
            if ($key === 'Header' && $value[0] == 2) {
                // Make the header titlecase.
                $s = $Str(ucwords(Pandoc_Filter::stringify($value[2])));

                // Replace the old header with the new header.
                return $Header($value[0], $value[1], [$s]);
            }
        }
    }
});
