
REF, or `r()` is a nicer alternative to PHP's [`print_r`](http://php.net/manual/en/function.print-r.php) / [`var_dump`](http://php.net/manual/en/function.var-dump.php) functions.

## [DEMO](http://dev.digitalnature.eu/php-ref/) ##

## Requirements ##

- (server) PHP 5.3+ (5.4+  displays additional info)
- (client) Any browser, except IE 8 and lower of course

## Usage and Options ##

Basic usage:
       
    // include the class
    require '/full/path/to/ref.php';

    // display info about defined classes
    r(get_declared_classes());

    // display info about global variables
    r($GLOBALS);

To print in text mode you can use the `rt()` function instead:

    rt($var);

To terminate the script after the info is dumped, prepend the bitwise NOT operator:

    ~r($var);   // html
    ~rt($var);  // text

Prepending the error control operator (@) will return the information:

    $output = @r($var);   // html
    $output = @rt($var);  // text
