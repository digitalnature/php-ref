
REF, or `r()` is a nicer alternative to PHP's [`print_r`](http://php.net/manual/en/function.print-r.php) / [`var_dump`](http://php.net/manual/en/function.var-dump.php) functions.

[**DEMO**](http://dev.digitalnature.eu/php-ref/) (Same example inside the index.php file)

<h2>Requirements</h2>

- (server) PHP 5.3+ (5.4+  displays additional info)
- (client) Any browser, except IE 8 and lower of course

Some limitations:

- currently HTML output only
- the source expression "parser" assumes the expression doesn't span across multiple lines (this is not meant to be a PHP parser, so don't expect this to change in the future)



<h2>Usage</h2>

    <?php

       require '/full/path/to/ref.php';

       // display info about defined classes
       r(get_declared_classes());

       // display info about global variables
       r($GLOBALS);