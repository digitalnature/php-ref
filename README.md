
REF, or `r()` is a nicer alternative to PHP's [`print_r`](http://php.net/manual/en/function.print-r.php) / [`var_dump`](http://php.net/manual/en/function.var-dump.php) functions.

## [DEMO](http://dev.digitalnature.eu/php-ref/) ##

## Requirements ##

- (server) PHP 5.3+ (5.4+  displays additional info)
- (client) Any browser, except IE 8 and lower of course

## Usage and Modifiers ##

To print the information as HTML, call the function normally:
       
    require '/full/path/to/ref.php';

    // display info about defined classes
    r(get_declared_classes());

    // display info about global variables
    r($GLOBALS);

For convenience reasons, basic display options can be set by prepending modifiers (operators) to the `r()` function name:

  - To print the information in plain text, call the shortcut function with the `\` modifier (namespace separator), like

        \r($subject1, $subject2 ...);

    If no headers were sent, the Content-Type header will be set to `text/plain` in order for the browser to interpret the output correctly.

  - To return the information as HTML, call the function with the `@` modifier (the error control operator), for example

        echo @r($subject1, $subject2 ...);

  - To return the info as text, combine them both

        echo @\r($subject1, $subject2 ...);

  - To stop the script after printing the info, use the `~` modifier (bitwise 'not' operator)

        ~r($subject1, $subject2 ...);  // html
        ~\r($subject1, $subject2 ...); // text

If you wish to avoid these weird-ass function calling methods, you can always create your own output function that uses `ref::build()` :)
