
REF, or `r()` is a nicer alternative to PHP's [`print_r`](http://php.net/manual/en/function.print-r.php) / [`var_dump`](http://php.net/manual/en/function.var-dump.php) functions.

## [DEMO](http://dev.digitalnature.eu/php-ref/) ##

## Requirements ##

- (server) PHP 5.3+ (5.4+  displays additional info)
- (client) Any browser, except IE 8 and lower of course

## Usage ##

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

Keyboard shortcuts (javascript must be enabled):

- `X` - collapses / expands all levels

To modify the global configuration call `ref::config()`:

    // example: initially expand first 3 levels
    ref::config('expandDepth', 3);

Currently available options and their default values:

| Option name       | Default value       | Description
|:----------------- |:------------------- |:-----------------------------------------------
| `'expandDepth'`   | `1`                 | Initial expand depth (for HTML mode only). A negative value will expand all levels
| `'extendedInfo'`  | `true`              | When this is set to `true`, additional information is returned. **Note that this seriously affects performance for queries that involve large amounts of data** (like arrays/iterators with many string elements)
| `'formatter'`     | `array()`           | Callbacks for custom/external formatters (as associative array: format => callback)
| `'shortcutFunc'`  | `array('r', 'rt')`  | Shortcut functions used to detect the input expression. If they are namespaced, the namespace must be present as well (methods are not  supported) 
| `'stylePath'`     | `'{:dir}/ref.css'`  | Local path to a custom stylesheet (HTML only); `false` means that no CSS is included.
| `'scriptPath'`    | `'{:dir}/ref.js'`   | Local path to a custom javascript (HTML only); `false` means no javascript (tooltips / toggle / kbd shortcuts require JS)
