# PHP pandocfilters

This is a PHP port of the python module for writing pandoc filters found at
https://github.com/jgm/pandocfilters

The purpose is simply to make it easier to write filters in PHP

```{.php}
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../pandocfilters.php';

Pandoc_Filter::toJSONFilter(function ($type, $value, $format, $meta) {
    if ('Str' == $type) {
        return ucwords($value);
    }
});

```

More examples and more information can be found in the original reporitory.
