<?php

/**
 * Shortcut to ref, HTML mode
 *
 * @param   mixed $args
 * @return  void|string
 */
function r(){

  // arguments passed to this function
  $args = func_get_args();

  // options (operators) gathered by the expression parser;
  // this variable gets passed as reference to getInputExpressions(), which will store the operators in it
  $options = array();

  // names of the arguments that were passed to this function
  $expressions = ref::getInputExpressions($options);
  $capture = in_array('@', $options, true);

  // something went wrong while trying to parse the source expressions?
  // if so, silently ignore this part and leave out the expression info
  if(func_num_args() !== count($expressions))
    $expressions = null;

  // use HTML formatter only if we're not in CLI mode, or if return was requested
  $format = (php_sapi_name() !== 'cli') || $capture ? 'html' : 'cliText';

  // IE goes funky if there's no doctype
  if(!$capture && ($format === 'html') && !headers_sent() && (!ob_get_level() || ini_get('output_buffering')))
    print '<!DOCTYPE HTML><html><head><title>REF</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>';

  $ref = new ref($format);

  if($capture)
    ob_start();

  foreach($args as $index => $arg)
    $ref->query($arg, $expressions ? $expressions[$index] : null);

  // return the results if this function was called with the error suppression operator 
  if($capture)
    return ob_get_clean();
  
  // stop the script if this function was called with the bitwise not operator
  if(in_array('~', $options, true) && ($format === 'html')){
    print '</body></html>';
    exit(0);
  }  
}



/**
 * Shortcut to ref, plain text mode
 *
 * @param   mixed $args
 * @return  void|string
 */
function rt(){
  $args        = func_get_args();
  $options     = array();  
  $output      = '';
  $expressions = ref::getInputExpressions($options);
  $capture     = in_array('@', $options, true);  
  $ref         = new ref((php_sapi_name() !== 'cli') || $capture ? 'text' : 'cliText');  

  if(func_num_args() !== count($expressions))
    $expressions = null;

  if(!headers_sent())    
    header('Content-Type: text/plain; charset=utf-8');  

  if($capture)
    ob_start();  

  foreach($args as $index => $arg)
    $ref->query($arg, $expressions ? $expressions[$index] : null);

  if($capture)
    return ob_get_clean(); 

  if(in_array('~', $options, true))
    exit(0);  
}



/**
 * REF is a nicer alternative to PHP's print_r() / var_dump().
 *
 * @version  1.0
 * @author   digitalnature - http://digitalnature.eu
 */
class ref{

  const

    MARKER_KEY = '_phpRefArrayMarker_';
    


  protected static
  
    /**
     * CPU time used for processing
     *
     * @var  array
     */   
    $time = 0,

    /**
     * Configuration (+ default values)
     *
     * @var  array
     */     
    $config = array(

                // initially expanded levels (for HTML mode only)
                'expLvl'               => 1,

                // depth limit (0 = no limit);
                // this is not related to recursion
                'maxDepth'             => 6,

                // show the place where r() has been called from
                'showBacktrace'        => true,

                // display iterator contents
                'showIteratorContents' => false,

                // display extra information about resources
                'showResourceInfo'     => true,
                
                // display method and parameter list on objects
                'showMethods'          => true,

                // display private properties / methods
                'showPrivateMembers'   => false,

                // peform string matches (date, file, functions, classes, json, serialized data, regex etc.)
                // note: seriously slows down queries on large amounts of data
                'showStringMatches'    => true,

                // shortcut functions used to access the query method below;
                // if they are namespaced, the namespace must be present as well (methods are not supported)
                'shortcutFunc'         => array('r', 'rt'),

                // custom/external formatters (as associative array: format => className)
                'formatters'           => array(),                

                // stylesheet path (for HTML only);
                // 'false' means no styles
                'stylePath'            => '{:dir}/ref.css',

                // javascript path (for HTML only);
                // 'false' means no js                      
                'scriptPath'           => '{:dir}/ref.js',

                // display url info via cURL
                'showUrls'             => false,

                // stop evaluation after this amount of time (seconds)
                'timeout'              => 10,

                // whether to produce W3c-valid HTML,
                // or unintelligible, but optimized markup that takes less space
                'validHtml'            => false,
              ),

    /**
     * Some environment variables
     * used to determine feature support
     *
     * @var  array
     */ 
    $env = array(),

    /**
     * Timeout point
     *
     * @var  bool
     */ 
    $timeout = -1,

    $debug = array(
      'cacheHits' => 0,
      'objects'   => 0,
      'arrays'    => 0,
      'scalars'   => 0,
    );    


  protected

    /**
     * Output formatter of this instance
     *
     * @var  RFormatter
     */     
    $fmt = null,

    /**
     * Start time of the current instance
     *
     * @var  float
     */ 
    $startTime = 0,

    /**
     * Internally created objects
     *
     * @var  SplObjectStorage
     */ 
    $intObjects = null;



  /**
   * Constructor
   *
   * @param   string|RFormatter $format      Output format ID, or formatter instance defaults to 'html'
   */
  public function __construct($format = 'html'){

    static $didIni = false;

    if(!$didIni){
      $didIni = true;
      foreach(array_keys(static::$config) as $key){
        $iniVal = get_cfg_var('ref.' . $key);
        if($iniVal !== false)
          static::$config[$key] = $iniVal;
      }

    }

    if($format instanceof RFormatter){
      $this->fmt = $format;

    }else{
      $format = isset(static::$config['formatters'][$format]) ? static::$config['formatters'][$format] : 'R' . ucfirst($format) . 'Formatter';

      if(!class_exists($format, false))
        throw new \Exception(sprintf('%s class not found', $format));

      $this->fmt = new $format();
    }

    if(static::$env)
      return;

    static::$env = array(

      // php 5.4+ ?
      'is54'         => version_compare(PHP_VERSION, '5.4') >= 0,

      // php 5.4.6+ ?
      'is546'        => version_compare(PHP_VERSION, '5.4.6') >= 0,      

      // php 5.6+
      'is56'         => version_compare(PHP_VERSION, '5.6') >= 0,      

      // php 7.0+ ?
      'is7'          => version_compare(PHP_VERSION, '7.0') >= 0,      

      // curl extension running?
      'curlActive'   => function_exists('curl_version'),      

      // is the 'mbstring' extension active?
      'mbStr'        => function_exists('mb_detect_encoding'),

      // @see: https://bugs.php.net/bug.php?id=52469     
      'supportsDate' => (strncasecmp(PHP_OS, 'WIN', 3) !== 0) || (version_compare(PHP_VERSION, '5.3.10') >= 0),
    );
  }



  /**
   * Enforce proper use of this class
   *
   * @param   string $name
   */
  public function __get($name){
    throw new \Exception(sprintf('No such property: %s', $name));
  }



  /**
   * Enforce proper use of this class
   *
   * @param   string $name
   * @param   mixed $value
   */
  public function __set($name, $value){
    throw new \Exception(sprintf('Cannot set %s. Not allowed', $name));
  }  



  /**
   * Generate structured information about a variable/value/expression (subject)
   *
   * Output is flushed to the screen
   *   
   * @param   mixed $subject
   * @param   string $expression
   */
  public function query($subject, $expression = null){

    if(static::$timeout > 0)
      return;

    $this->startTime = microtime(true);

    $this->intObjects = new \SplObjectStorage();
    
    $this->fmt->startRoot();
    $this->fmt->startExp();
    $this->evaluateExp($expression);
    $this->fmt->endExp();
    $this->evaluate($subject);    
    $this->fmt->endRoot();
    $this->fmt->flush();

    static::$time += microtime(true) - $this->startTime;     
  }




  /**
   * Executes a function the given number of times and returns the elapsed time.
   *
   * Keep in mind that the returned time includes function call overhead (including
   * microtime calls) x iteration count. This is why this is better suited for
   * determining which of two or more functions is the fastest, rather than
   * finding out how fast is a single function.
   *
   * @param   int $iterations      Number of times the function will be executed
   * @param   callable $function   Function to execute
   * @param   mixed &$output       If given, last return value will be available in this variable
   * @return  double               Elapsed time
   */
  public static function timeFunc($iterations, $function, &$output = null){
    
    $time = 0;

    for($i = 0; $i < $iterations; $i++){
      $start  = microtime(true);
      $output = call_user_func($function);
      $time  += microtime(true) - $start;
    }
    
    return round($time, 4);
  }



  /**
   * Timer utility
   *
   * First call of this function will start the timer.
   * The second call will stop the timer and return the elapsed time
   * since the timer started.
   *
   * Multiple timers can be controlled simultaneously by specifying a timer ID.
   *
   * @since   1.0   
   * @param   int $id          Timer ID, optional
   * @param   int $precision   Precision of the result, optional
   * @return  void|double      Elapsed time, or void if the timer was just started
   */
  public static function timer($id = 1, $precision = 4){

    static
      $timers = array();

    // check if this timer was started, and display the elapsed time if so
    if(isset($timers[$id])){
      $elapsed = round(microtime(true) - $timers[$id], $precision);
      unset($timers[$id]);
      return $elapsed;
    }

    // ID doesn't exist, start new timer
    $timers[$id] = microtime(true);
  }



  /**
   * Parses a DocBlock comment into a data structure.
   *
   * @link    http://pear.php.net/manual/en/standards.sample.php
   * @param   string $comment    DocBlock comment (must start with /**)
   * @param   string|null $key   Field to return (optional)
   * @return  array|string|null  Array containing all fields, array/string with the contents of
   *                             the requested field, or null if the comment is empty/invalid
   */
  public static function parseComment($comment, $key = null){

    $description = '';
    $tags        = array();
    $tag         = null;
    $pointer     = '';
    $padding     = 0;
    $comment     = preg_split('/\r\n|\r|\n/', '* ' . trim($comment, "/* \t\n\r\0\x0B"));

    // analyze each line
    foreach($comment as $line){

      // drop any wrapping spaces
      $line = trim($line);

      // drop "* "
      if($line !== '')
        $line = substr($line, 2);      

      if(strpos($line, '@') !== 0){

        // preserve formatting of tag descriptions,
        // because they may span across multiple lines
        if($tag !== null){
          $trimmed = trim($line);

          if($padding !== 0)
            $trimmed = static::strPad($trimmed, static::strLen($line) - $padding, ' ', STR_PAD_LEFT);
          else
            $padding = static::strLen($line) - static::strLen($trimmed);

          $pointer .= "\n{$trimmed}";
          continue;
        }
        
        // tag definitions have not started yet; assume this is part of the description text
        $description .= "\n{$line}";        
        continue;
      }  

      $padding = 0;
      $parts = explode(' ', $line, 2);

      // invalid tag? (should we include it as an empty array?)
      if(!isset($parts[1]))
        continue;

      $tag = substr($parts[0], 1);
      $line = ltrim($parts[1]);

      // tags that have a single component (eg. link, license, author, throws...);
      // note that @throws may have 2 components, however most people use it like "@throws ExceptionClass if whatever...",
      // which, if broken into two values, leads to an inconsistent description sentence
      if(!in_array($tag, array('global', 'param', 'return', 'var'))){
        $tags[$tag][] = $line;
        end($tags[$tag]);
        $pointer = &$tags[$tag][key($tags[$tag])];
        continue;
      }

      // tags with 2 or 3 components (var, param, return);
      $parts    = explode(' ', $line, 2);
      $parts[1] = isset($parts[1]) ? ltrim($parts[1]) : null;
      $lastIdx  = 1;

      // expecting 3 components on the 'param' tag: type varName varDescription
      if($tag === 'param'){
        $lastIdx = 2;
        if(in_array($parts[1][0], array('&', '$'), true)){
          $line     = ltrim(array_pop($parts));
          $parts    = array_merge($parts, explode(' ', $line, 2));        
          $parts[2] = isset($parts[2]) ? ltrim($parts[2]) : null;
        }else{
          $parts[2] = $parts[1];
          $parts[1] = null;
        }
      }  

      $tags[$tag][] = $parts;
      end($tags[$tag]);
      $pointer = &$tags[$tag][key($tags[$tag])][$lastIdx];
    }

    // split title from the description texts at the nearest 2x new-line combination
    // (note: loose check because 0 isn't valid as well)
    if(strpos($description, "\n\n")){
      list($title, $description) = explode("\n\n", $description, 2);

    // if we don't have 2 new lines, try to extract first sentence
    }else{  
      // in order for a sentence to be considered valid,
      // the next one must start with an uppercase letter    
      $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z])/', $description, 2, PREG_SPLIT_NO_EMPTY);

      // failed to detect a second sentence? then assume there's only title and no description text
      $title = isset($sentences[0]) ? $sentences[0] : $description;
      $description = isset($sentences[1]) ? $sentences[1] : '';
    }

    $title = ltrim($title);
    $description = ltrim($description);

    $data = compact('title', 'description', 'tags');

    if(!array_filter($data))
      return null;

    if($key !== null)
      return isset($data[$key]) ? $data[$key] : null;

    return $data;
  }



  /**
   * Split a regex into its components
   * 
   * Based on "Regex Colorizer" by Steven Levithan (this is a translation from javascript)
   *
   * @link     https://github.com/slevithan/regex-colorizer
   * @link     https://github.com/symfony/Finder/blob/master/Expression/Regex.php#L64-74
   * @param    string $pattern
   * @return   array
   */
  public static function splitRegex($pattern){

    // detection attempt code from the Symfony Finder component
    $maybeValid = false;
    if(preg_match('/^(.{3,}?)([imsxuADU]*)$/', $pattern, $m)) {
      $start = substr($m[1], 0, 1);
      $end   = substr($m[1], -1);

      if(($start === $end && !preg_match('/[*?[:alnum:] \\\\]/', $start)) || ($start === '{' && $end === '}'))
        $maybeValid = true;
    }

    if(!$maybeValid)
      throw new \Exception('Pattern does not appear to be a valid PHP regex');

    $output              = array();
    $capturingGroupCount = 0;
    $groupStyleDepth     = 0;
    $openGroups          = array();
    $lastIsQuant         = false;
    $lastType            = 1;      // 1 = none; 2 = alternator
    $lastStyle           = null;

    preg_match_all('/\[\^?]?(?:[^\\\\\]]+|\\\\[\S\s]?)*]?|\\\\(?:0(?:[0-3][0-7]{0,2}|[4-7][0-7]?)?|[1-9][0-9]*|x[0-9A-Fa-f]{2}|u[0-9A-Fa-f]{4}|c[A-Za-z]|[\S\s]?)|\((?:\?[:=!]?)?|(?:[?*+]|\{[0-9]+(?:,[0-9]*)?\})\??|[^.?*+^${[()|\\\\]+|./', $pattern, $matches);

    $matches = $matches[0];

    $getTokenCharCode = function($token){
      if(strlen($token) > 1 && $token[0] === '\\'){
        $t1 = substr($token, 1);

        if(preg_match('/^c[A-Za-z]$/', $t1))
          return strpos("ABCDEFGHIJKLMNOPQRSTUVWXYZ", strtoupper($t1[1])) + 1;

        if(preg_match('/^(?:x[0-9A-Fa-f]{2}|u[0-9A-Fa-f]{4})$/', $t1))
          return intval(substr($t1, 1), 16);

        if(preg_match('/^(?:[0-3][0-7]{0,2}|[4-7][0-7]?)$/', $t1))
          return intval($t1, 8);

        $len = strlen($t1);

        if($len === 1 && strpos('cuxDdSsWw', $t1) !== false)
          return null;

        if($len === 1){
          switch ($t1) {
            case 'b': return 8;  
            case 'f': return 12; 
            case 'n': return 10; 
            case 'r': return 13; 
            case 't': return 9;  
            case 'v': return 11; 
            default: return $t1[0]; 
          }
        }
      }

      return ($token !== '\\') ? $token[0] : null;  
    };   

    foreach($matches as $m){

      if($m[0] === '['){
        $lastCC         = null;  
        $cLastRangeable = false;
        $cLastType      = 0;  // 0 = none; 1 = range hyphen; 2 = short class

        preg_match('/^(\[\^?)(]?(?:[^\\\\\]]+|\\\\[\S\s]?)*)(]?)$/', $m, $parts);

        array_shift($parts);
        list($opening, $content, $closing) = $parts;

        if(!$closing)
          throw new \Exception('Unclosed character class');

        preg_match_all('/[^\\\\-]+|-|\\\\(?:[0-3][0-7]{0,2}|[4-7][0-7]?|x[0-9A-Fa-f]{2}|u[0-9A-Fa-f]{4}|c[A-Za-z]|[\S\s]?)/', $content, $ccTokens);
        $ccTokens     = $ccTokens[0];
        $ccTokenCount = count($ccTokens);
        $output[]     = array('chr' => $opening);

        foreach($ccTokens as $i => $cm) {

          if($cm[0] === '\\'){
            if(preg_match('/^\\\\[cux]$/', $cm))
              throw new \Exception('Incomplete regex token');

            if(preg_match('/^\\\\[dsw]$/i', $cm)) {
              $output[]     = array('chr-meta' => $cm);
              $cLastRangeable  = ($cLastType !== 1);
              $cLastType       = 2;

            }elseif($cm === '\\'){
              throw new \Exception('Incomplete regex token');
              
            }else{
              $output[]       = array('chr-meta' => $cm);
              $cLastRangeable = $cLastType !== 1;
              $lastCC         = $getTokenCharCode($cm);
            }

          }elseif($cm === '-'){
            if($cLastRangeable){
              $nextToken = ($i + 1 < $ccTokenCount) ? $ccTokens[$i + 1] : false;

              if($nextToken){
                $nextTokenCharCode = $getTokenCharCode($nextToken[0]);

                if((!is_null($nextTokenCharCode) && $lastCC > $nextTokenCharCode) || $cLastType === 2 || preg_match('/^\\\\[dsw]$/i', $nextToken[0]))
                  throw new \Exception('Reversed or invalid range');

                $output[]       = array('chr-range' => '-');
                $cLastRangeable = false;
                $cLastType      = 1;
               
              }else{
                $output[] = $closing ? array('chr' => '-') : array('chr-range' => '-');
              }

            }else{
              $output[]        = array('chr' => '-');
              $cLastRangeable  = ($cLastType !== 1);
            }

          }else{
            $output[]       = array('chr' => $cm);
            $cLastRangeable = strlen($cm) > 1 || ($cLastType !== 1);
            $lastCC         = $cm[strlen($cm) - 1];
          }
        }

        $output[] = array('chr' => $closing);
        $lastIsQuant  = true;

      }elseif($m[0] === '('){
        if(strlen($m) === 2)
          throw new \Exception('Invalid or unsupported group type');
   
        if(strlen($m) === 1)
          $capturingGroupCount++;

        $groupStyleDepth = ($groupStyleDepth !== 5) ? $groupStyleDepth + 1 : 1;
        $openGroups[]    = $m; // opening
        $lastIsQuant     = false;
        $output[]        = array("g{$groupStyleDepth}" => $m);

      }elseif($m[0] === ')'){
        if(!count($openGroups)) 
          throw new \Exception('No matching opening parenthesis');

        $output[]        = array('g' . $groupStyleDepth => ')');
        $prevGroup       = $openGroups[count($openGroups) - 1];
        $prevGroup       = isset($prevGroup[2]) ? $prevGroup[2] : '';
        $lastIsQuant     = !preg_match('/^[=!]/', $prevGroup);
        $lastStyle       = "g{$groupStyleDepth}";
        $lastType        = 0;
        $groupStyleDepth = ($groupStyleDepth !== 1) ? $groupStyleDepth - 1 : 5;

        array_pop($openGroups);
        continue;
      
      }elseif($m[0] === '\\'){
        if(isset($m[1]) && preg_match('/^[1-9]/', $m[1])){
          $nonBackrefDigits = '';
          $num = substr(+$m, 1);

          while($num > $capturingGroupCount){
            preg_match('/[0-9]$/', $num, $digits);
            $nonBackrefDigits = $digits[0] . $nonBackrefDigits;
            $num = floor($num / 10); 
          }

          if($num > 0){
            $output[] = array('meta' =>  "\\{$num}", 'text' => $nonBackrefDigits);

          }else{
            preg_match('/^\\\\([0-3][0-7]{0,2}|[4-7][0-7]?|[89])([0-9]*)/', $m, $pts);
            $output[] = array('meta' => '\\' . $pts[1], 'text' => $pts[2]);
          }

          $lastIsQuant = true;

        }elseif(isset($m[1]) && preg_match('/^[0bBcdDfnrsStuvwWx]/', $m[1])){
   
          if(preg_match('/^\\\\[cux]$/', $m))
            throw new \Exception('Incomplete regex token');

          $output[]    = array('meta' => $m);
          $lastIsQuant = (strpos('bB', $m[1]) === false);

        }elseif($m === '\\'){
          throw new \Exception('Incomplete regex token');
            
        }else{
          $output[]    = array('text' => $m);
          $lastIsQuant = true;
        }

      }elseif(preg_match('/^(?:[?*+]|\{[0-9]+(?:,[0-9]*)?\})\??$/', $m)){
        if(!$lastIsQuant)
          throw new \Exception('Quantifiers must be preceded by a token that can be repeated');

        preg_match('/^\{([0-9]+)(?:,([0-9]*))?/', $m, $interval);

        if($interval && (+$interval[1] > 65535 || (isset($interval[2]) && (+$interval[2] > 65535))))
          throw new \Exception('Interval quantifier cannot use value over 65,535');
        
        if($interval && isset($interval[2]) && (+$interval[1] > +$interval[2]))
          throw new \Exception('Interval quantifier range is reversed');
        
        $output[]     = array($lastStyle ? $lastStyle : 'meta' => $m);
        $lastIsQuant  = false;

      }elseif($m === '|'){
        if($lastType === 1 || ($lastType === 2 && !count($openGroups)))
          throw new \Exception('Empty alternative effectively truncates the regex here');

        $output[]    = count($openGroups) ? array("g{$groupStyleDepth}" => '|') : array('meta' => '|');
        $lastIsQuant = false;
        $lastType    = 2;
        $lastStyle   = '';
        continue;

      }elseif($m === '^' || $m === '$'){
        $output[]    = array('meta' => $m);
        $lastIsQuant = false;

      }elseif($m === '.'){
        $output[]    = array('meta' => '.');
        $lastIsQuant = true;
   
      }else{
        $output[]    = array('text' => $m);
        $lastIsQuant = true;
      }

      $lastType  = 0;
      $lastStyle = '';    
    }

    if($openGroups)
      throw new \Exception('Unclosed grouping');

    return $output;
  }



  /**
   * Set or get configuration options
   *
   * @param   string $key
   * @param   mixed|null $value
   * @return  mixed
   */
  public static function config($key, $value = null){

    if(!array_key_exists($key, static::$config))
      throw new \Exception(sprintf('Unrecognized option: "%s". Valid options are: %s', $key, implode(', ', array_keys(static::$config))));

    if($value === null)
      return static::$config[$key];

    if(is_array(static::$config[$key]))
      return static::$config[$key] = (array)$value;

    return static::$config[$key] = $value;
  }



  /**
   * Total CPU time used by the class
   *   
   * @param   int precision
   * @return  double
   */
  public static function getTime($precision = 4){
    return round(static::$time, $precision);
  }



  /**
   * Get relevant backtrace info for last ref call
   *   
   * @return  array|false
   */
  public static function getBacktrace(){

    // pull only basic info with php 5.3.6+ to save some memory
    $trace = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) : debug_backtrace();

    while($callee = array_pop($trace)){

      // extract only the information we neeed
      $callee = array_intersect_key($callee, array_fill_keys(array('file', 'function', 'line'), false));
      extract($callee, EXTR_OVERWRITE);

      // skip, if the called function doesn't match the shortcut function name
      if(!$function || !in_array(mb_strtolower((string)$function), static::$config['shortcutFunc']))
        continue;

      return compact('file', 'function', 'line');
    }

    return false;
  }



  /**
   * Determines the input expression(s) passed to the shortcut function
   *
   * @param   array &$options   Optional, options to gather (from operators)
   * @return  array             Array of string expressions
   */
  public static function getInputExpressions(array &$options = null){    

    // used to determine the position of the current call,
    // if more queries calls were made on the same line
    static $lineInst = array();

    $trace = static::getBacktrace();

    if(!$trace)
      return array();

    extract($trace);

    $code     = file($file);
    $code     = $code[$line - 1]; // multiline expressions not supported!
    $instIndx = 0;
    $tokens   = token_get_all("<?php {$code}");

    // locate the caller position in the line, and isolate argument tokens
    foreach($tokens as $i => $token){

      // match token with our shortcut function name
      if(is_string($token) || ($token[0] !== T_STRING) || (strcasecmp($token[1], $function) !== 0))
        continue;

      // is this some method that happens to have the same name as the shortcut function?
      if(isset($tokens[$i - 1]) && is_array($tokens[$i - 1]) && in_array($tokens[$i - 1][0], array(T_DOUBLE_COLON, T_OBJECT_OPERATOR), true))
        continue;

      // find argument definition start, just after '('
      if(isset($tokens[$i + 1]) && ($tokens[$i + 1][0] === '(')){
        $instIndx++;

        if(!isset($lineInst[$line]))
          $lineInst[$line] = 0;

        if($instIndx <= $lineInst[$line])
          continue;

        $lineInst[$line]++;

        // gather options
        if($options !== null){
          $j = $i - 1;
          while(isset($tokens[$j]) && is_string($tokens[$j]) && in_array($tokens[$j], array('@', '+', '-', '!', '~')))
            $options[] = $tokens[$j--];
        }  
       
        $lvl = $index = $curlies = 0;
        $expressions = array();

        // get the expressions
        foreach(array_slice($tokens, $i + 2) as $token){

          if(is_array($token)){
            if($token[0] !== T_COMMENT)
              $expressions[$index][] = ($token[0] !== T_WHITESPACE) ? $token[1] : ' ';

            continue;
          }

          if($token === '{')
            $curlies++;

          if($token === '}')
            $curlies--;        

          if($token === '(')
            $lvl++;

          if($token === ')')
            $lvl--;

          // assume next argument if a comma was encountered,
          // and we're not insde a curly bracket or inner parentheses
          if(($curlies < 1) && ($lvl === 0) && ($token === ',')){
            $index++;
            continue;
          }  

          // negative parentheses count means we reached the end of argument definitions
          if($lvl < 0){         
            foreach($expressions as &$expression)
              $expression = trim(implode('', $expression));

            return $expressions;
          }

          $expressions[$index][] = $token;      
        }

        break;
      }    
    }

    return array();
  }



  /**
   * Get all parent classes of a class
   *
   * @param   Reflector $class   Reflection object
   * @return  array              Array of ReflectionClass objects (starts with the ancestor, ends with the given class)
   */
  protected static function getParentClasses(\Reflector $class){

    $parents = array($class);
    while(($class = $class->getParentClass()) !== false)
      $parents[] = $class;
   
    return array_reverse($parents);
  }



  /**
   * Generate class / function info
   *
   * @param   Reflector $reflector      Class name or reflection object
   * @param   string $single            Skip parent classes
   * @param   Reflector|null $context   Object context (for methods)
   * @return  string
   */
  protected function fromReflector(\Reflector $reflector, $single = '', \Reflector $context = null){

    // @todo: test this
    $hash = var_export(func_get_args(), true);
    //$hash = $reflector->getName() . ';' . $single . ';' . ($context ? $context->getName() : '');

    if($this->fmt->didCache($hash)){
      static::$debug['cacheHits']++;
      return;
    }

    $items = array($reflector);

    if(($single === '') && ($reflector instanceof \ReflectionClass))
      $items = static::getParentClasses($reflector);

    $first = true;
    foreach($items as $item){

      if(!$first)
        $this->fmt->sep(' :: ');

      $first    = false;
      $name     = ($single !== '') ? $single : $item->getName();
      $comments = $item->isInternal() ? array() : static::parseComment($item->getDocComment());
      $meta     = array('sub' => array());
      $bubbles  = array();

      if($item->isInternal()){
        $extension = $item->getExtension();
        $meta['title'] = ($extension instanceof \ReflectionExtension) ? sprintf('Internal - part of %s (%s)', $extension->getName(), $extension->getVersion()) : 'Internal';
      
      }else{
        $comments = static::parseComment($item->getDocComment()); 

        if($comments)
          $meta += $comments;

        $meta['sub'][] = array('Defined in', basename($item->getFileName()) . ':' . $item->getStartLine());        
      }

      if(($item instanceof \ReflectionFunction) || ($item instanceof \ReflectionMethod)){
        if(($context !== null) && ($context->getShortName() !== $item->getDeclaringClass()->getShortName()))
          $meta['sub'][] = array('Inherited from', $item->getDeclaringClass()->getShortName());

        // @note: PHP 7 seems to crash when calling getPrototype on Closure::__invoke()
        if(($item instanceof \ReflectionMethod) && !$item->isInternal()){
          try{
            $proto = $item->getPrototype();
            $meta['sub'][] = array('Prototype defined by', $proto->class);
          }catch(\Exception $e){}
        }  

        $this->fmt->text('name', $name, $meta, $this->linkify($item));          
        continue;
      }
      
      // @todo: maybe - list interface methods
      if(!($item->isInterface() || (static::$env['is54'] && $item->isTrait()))){

        if($item->isAbstract())
          $bubbles[] = array('A', 'Abstract');

        if(static::$env['is7'] && $item->isAnonymous())
          $bubbles[] = array('?', 'Anonymous');        

        if($item->isFinal())
          $bubbles[] = array('F', 'Final');

        // php 5.4+ only
        if(static::$env['is54'] && $item->isCloneable())
          $bubbles[] = array('C', 'Cloneable');          

        if($item->isIterateable())
          $bubbles[] = array('X', 'Iterateable');                    
      
      }

      if($item->isInterface() && $single !== '')
        $bubbles[] = array('I', 'Interface');                            

      if($bubbles)
        $this->fmt->bubbles($bubbles);

      if($item->isInterface() && $single === '')
        $name .= sprintf(' (%d)', count($item->getMethods()));

      $this->fmt->text('name', $name, $meta, $this->linkify($item));          
    }

    $this->fmt->cacheLock($hash);  
  }



  /**
   * Generates an URL that points to the documentation page relevant for the requested context
   *
   * For internal functions and classes, the URI will point to the local PHP manual
   * if installed and configured, otherwise to php.net/manual (the english one)
   *
   * @param   Reflector $reflector    Reflector object (used to determine the URL scheme for internal stuff)
   * @param   string|null $constant   Constant name, if this is a request to linkify a constant
   * @return  string|null             URL
   */
  protected function linkify(\Reflector $reflector, $constant = null){

    static $docRefRoot = null, $docRefExt = null;

    // most people don't have this set
    if(!$docRefRoot)
      $docRefRoot = ($docRefRoot = rtrim(ini_get('docref_root'), '/')) ? $docRefRoot : 'http://php.net/manual/en';

    if(!$docRefExt)
      $docRefExt = ($docRefExt = ini_get('docref_ext')) ? $docRefExt : '.php';

    $phpNetSchemes = array(
      'class'     => $docRefRoot . '/class.%s'    . $docRefExt,
      'function'  => $docRefRoot . '/function.%s' . $docRefExt,
      'method'    => $docRefRoot . '/%2$s.%1$s'   . $docRefExt,
      'property'  => $docRefRoot . '/class.%2$s'  . $docRefExt . '#%2$s.props.%1$s',
      'constant'  => $docRefRoot . '/class.%2$s'  . $docRefExt . '#%2$s.constants.%1$s',      
    );

    $url  = null;    
    $args = array();

    // determine scheme
    if($constant !== null){
      $type = 'constant';
      $args[] = $constant;
    
    }else{
      $type = explode('\\', get_class($reflector)); 
      $type = strtolower(ltrim(end($type), 'Reflection'));

      if($type === 'object')
        $type = 'class';
    }

    // properties don't have the internal flag;
    // also note that many internal classes use some kind of magic as properties (eg. DateTime);
    // these will only get linkifed if the declared class is internal one, and not an extension :(
    $parent = ($type !== 'property') ? $reflector : $reflector->getDeclaringClass();

    // internal function/method/class/property/constant
    if($parent->isInternal()){
      $args[] = $reflector->name;

      if(in_array($type, array('method', 'property'), true))
        $args[] = $reflector->getDeclaringClass()->getName();

      $args = array_map(function($text){
        return str_replace('_', '-', ltrim(strtolower($text), '\\_'));
      }, $args);

      // check for some special cases that have no links
      $valid = (($type === 'method') || (strcasecmp($parent->name, 'stdClass') !== 0))
            && (($type !== 'method') || (($reflector->name === '__construct') || strpos($reflector->name, '__') !== 0));

      if($valid)
        $url = vsprintf($phpNetSchemes[$type], $args);

    // custom
    }else{
      switch(true){      

        // WordPress function;
        // like pretty much everything else in WordPress, API links are inconsistent as well;
        // so we're using queryposts.com as doc source for API
        case ($type === 'function') && class_exists('WP', false) && defined('ABSPATH') && defined('WPINC'):
          if(strpos($reflector->getFileName(), realpath(ABSPATH . WPINC)) === 0){
            $url = sprintf('http://queryposts.com/function/%s', urlencode(strtolower($reflector->getName())));
            break;
          }

        // @todo: handle more apps
      }      

    }

    return $url;
  }


  public static function getTimeoutPoint(){
    return static::$timeout;
  }


  public static function getDebugInfo(){
    return static::$debug;
  }



  protected function hasInstanceTimedOut(){

    if(static::$timeout > 0)
      return true;

    $timeout = static::$config['timeout'];

    if(($timeout > 0) && ((microtime(true) - $this->startTime) > $timeout))
      return (static::$timeout = (microtime(true) - $this->startTime));

    return false;
  }



  /**
   * Evaluates the given variable
   *
   * @param   mixed &$subject   Variable to query
   * @param   bool $specialStr  Should this be interpreted as a special string?
   * @return  mixed             Result (both HTML and text modes generate strings)
   */
  protected function evaluate(&$subject, $specialStr = false){    

    switch($type = gettype($subject)){

      // https://github.com/digitalnature/php-ref/issues/13
      case 'unknown type':
        return $this->fmt->text('unknown');
    
      // null value
      case 'NULL':
        return $this->fmt->text('null');

      // integer/double/float
      case 'integer':
      case 'double':
        return $this->fmt->text($type, $subject, $type);

      // boolean
      case 'boolean':
        $text = $subject ? 'true' : 'false';
        return $this->fmt->text($text, $text, $type);

      // arrays
      case 'array':

        // empty array?
        if(empty($subject)){
          $this->fmt->text('array');          
          return $this->fmt->emptyGroup();
        }  

        if(isset($subject[static::MARKER_KEY])){
          unset($subject[static::MARKER_KEY]);
          $this->fmt->text('array');
          $this->fmt->emptyGroup('recursion');          
          return;
        }

        // first recursion level detection;
        // this is optional (used to print consistent recursion info)
        foreach($subject as $key => &$value){

          if(!is_array($value))
            continue;

          // save current value in a temporary variable
          $buffer = $value;

          // assign new value
          $value = ($value !== 1) ? 1 : 2;
          
          // if they're still equal, then we have a reference            
          if($value === $subject){
            $value = $buffer;                      
            $value[static::MARKER_KEY] = true;
            $this->evaluate($value);
            return;
          }

          // restoring original value
          $value = $buffer;
        }        

        $this->fmt->text('array');
        $count = count($subject);
        if(!$this->fmt->startGroup($count))
          return;               

        $max = max(array_map('static::strLen', array_keys($subject)));
        $subject[static::MARKER_KEY] = true;
     
        foreach($subject as $key => &$value){
          
          // ignore our temporary marker
          if($key === static::MARKER_KEY)
            continue;

          if($this->hasInstanceTimedOut())
            break;

          $keyInfo = gettype($key);

          if($keyInfo === 'string'){
            $encoding = static::$env['mbStr'] ? mb_detect_encoding($key) : '';
            $keyLen     = static::strLen($key);
            $keyLenInfo = $encoding && ($encoding !== 'ASCII') ? $keyLen . '; ' . $encoding : $keyLen;
            $keyInfo    = "{$keyInfo}({$keyLenInfo})";
          }else{
            $keyLen   = strlen($key);
          }

          $this->fmt->startRow();
          $this->fmt->text('key', $key, "Key: {$keyInfo}");
          $this->fmt->colDiv($max - $keyLen);
          $this->fmt->sep('=>');
          $this->fmt->colDiv();
          $this->evaluate($value, $specialStr);
          $this->fmt->endRow();
        }

        unset($subject[static::MARKER_KEY]);

        $this->fmt->endGroup();
        return;

      // resource
      case 'resource':
      case 'resource (closed)':
        $meta    = array();
        $resType = get_resource_type($subject);        

        $this->fmt->text('resource', strval($subject));

        if(!static::$config['showResourceInfo'])
          return $this->fmt->emptyGroup($resType);

        // @see: http://php.net/manual/en/resource.php
        // need to add more...
        switch($resType){

          // curl extension resource
          case 'curl':
            $meta = curl_getinfo($subject);
          break;

          case 'FTP Buffer':
            $meta = array(
              'time_out'  => ftp_get_option($subject, FTP_TIMEOUT_SEC),
              'auto_seek' => ftp_get_option($subject, FTP_AUTOSEEK),
            );

          break;

          // gd image extension resource
          case 'gd':            
            $meta = array(
               'size'       => sprintf('%d x %d', imagesx($subject), imagesy($subject)),
               'true_color' => imageistruecolor($subject),
            );

          break;

          case 'ldap link':
            $constants = get_defined_constants();

            array_walk($constants, function($value, $key) use(&$constants){
              if(strpos($key, 'LDAP_OPT_') !== 0)
                unset($constants[$key]);
            });

            // this seems to fail on my setup :(
            unset($constants['LDAP_OPT_NETWORK_TIMEOUT']);

            foreach(array_slice($constants, 3) as $key => $value)
              if(ldap_get_option($subject, (int)$value, $ret))
                $meta[strtolower(substr($key, 9))] = $ret;

          break;

          // mysql connection (mysql extension is deprecated from php 5.4/5.5)
          case 'mysql link':
          case 'mysql link persistent':
            $dbs = array();
            $query = @mysql_list_dbs($subject);
            while($row = @mysql_fetch_array($query))
              $dbs[] = $row['Database'];

            $meta = array(
              'host'             => ltrim(@mysql_get_host_info ($subject), 'MySQL host info: '),
              'server_version'   => @mysql_get_server_info($subject),
              'protocol_version' => @mysql_get_proto_info($subject),
              'databases'        => $dbs,
            );

          break;

          // mysql result
          case 'mysql result':
            while($row = @mysql_fetch_object($subject)){              
              $meta[] = (array)$row;

              if($this->hasInstanceTimedOut())
                break;
            }

          break;

          // stream resource (fopen, fsockopen, popen, opendir etc)
          case 'stream':
            $meta = stream_get_meta_data($subject);
          break;

        }

        if(!$meta)
          return $this->fmt->emptyGroup($resType);


        if(!$this->fmt->startGroup($resType))
          return;

        $max = max(array_map('static::strLen', array_keys($meta)));
        foreach($meta as $key => $value){
          $this->fmt->startRow();
          $this->fmt->text('resourceProp', ucwords(str_replace('_', ' ', $key)));
          $this->fmt->colDiv($max - static::strLen($key));
          $this->fmt->sep(':');
          $this->fmt->colDiv();
          $this->evaluate($value);          
          $this->fmt->endRow();
        }
        $this->fmt->endGroup();
        return;

      // string      
      case 'string':
        $length   = static::strLen($subject);       
        $encoding = static::$env['mbStr'] ? mb_detect_encoding($subject) : false;      
        $info     = $encoding && ($encoding !== 'ASCII') ? $length . '; ' . $encoding : $length;

        if($specialStr){
          $this->fmt->sep('"');
          $this->fmt->text(array('string', 'special'), $subject, "string({$info})");
          $this->fmt->sep('"');          
          return;
        }  
        
        $this->fmt->text('string', $subject, "string({$info})");        

        // advanced checks only if there are 3 characteres or more
        if(static::$config['showStringMatches'] && ($length > 2) && (trim($subject) !== '')){

          $isNumeric = is_numeric($subject);

          // very simple check to determine if the string could match a file path
          // @note: this part of the code is very expensive
          $isFile = ($length < 2048)
            && (max(array_map('strlen', explode('/', str_replace('\\', '/', $subject)))) < 128)
            && !preg_match('/[^\w\.\-\/\\\\:]|\..*\.|\.$|:(?!(?<=^[a-zA-Z]:)[\/\\\\])/', $subject);            

          if($isFile){
            try{
              $file  = new \SplFileInfo($subject);
              $flags = array();
              $perms = $file->getPerms();

              if(($perms & 0xC000) === 0xC000)       // socket
                $flags[] = 's';
              elseif(($perms & 0xA000) === 0xA000)   // symlink        
                $flags[] = 'l';
              elseif(($perms & 0x8000) === 0x8000)   // regular
                $flags[] = '-';
              elseif(($perms & 0x6000) === 0x6000)   // block special
                $flags[] = 'b';
              elseif(($perms & 0x4000) === 0x4000)   // directory
                $flags[] = 'd';
              elseif(($perms & 0x2000) === 0x2000)   // character special
                $flags[] = 'c';
              elseif(($perms & 0x1000) === 0x1000)   // FIFO pipe
                $flags[] = 'p';
              else                                   // unknown
                $flags[] = 'u';        

              // owner
              $flags[] = (($perms & 0x0100) ? 'r' : '-');
              $flags[] = (($perms & 0x0080) ? 'w' : '-');
              $flags[] = (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));

              // group
              $flags[] = (($perms & 0x0020) ? 'r' : '-');
              $flags[] = (($perms & 0x0010) ? 'w' : '-');
              $flags[] = (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));

              // world
              $flags[] = (($perms & 0x0004) ? 'r' : '-');
              $flags[] = (($perms & 0x0002) ? 'w' : '-');
              $flags[] = (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));

              $size = is_dir($subject) ? '' : sprintf(' %.2fK', $file->getSize() / 1024);
              
              $this->fmt->startContain('file', true);
              $this->fmt->text('file', implode('', $flags) . $size);
              $this->fmt->endContain();

            }catch(\Exception $e){
              $isFile = false;
            }
          }

          // class/interface/function
          if(!preg_match('/[^\w+\\\\]/', $subject) && ($length < 96)){
            $isClass = class_exists($subject, false);
            if($isClass){
              $this->fmt->startContain('class', true);
              $this->fromReflector(new \ReflectionClass($subject));
              $this->fmt->endContain();
            }  

            if(!$isClass && interface_exists($subject, false)){
              $this->fmt->startContain('interface', true);
              $this->fromReflector(new \ReflectionClass($subject));
              $this->fmt->endContain('interface');
            }  

            if(function_exists($subject)){
              $this->fmt->startContain('function', true);
              $this->fromReflector(new \ReflectionFunction($subject));
              $this->fmt->endContain('function');
            }  
          }


          // skip serialization/json/date checks if the string appears to be numeric,
          // or if it's shorter than 5 characters
          if(!$isNumeric && ($length > 4)){

            // url
            if(static::$config['showUrls'] && static::$env['curlActive'] && filter_var($subject, FILTER_VALIDATE_URL)){
              $ch = curl_init($subject);
              curl_setopt($ch, CURLOPT_NOBODY, true);
              curl_exec($ch);
              $nfo = curl_getinfo($ch);
              curl_close($ch);

              if($nfo['http_code']){
                $this->fmt->startContain('url', true);
                $contentType = explode(';', $nfo['content_type']);
                $this->fmt->text('url', sprintf('%s:%d %s %.2fms (%d)', !empty($nfo['primary_ip']) ? $nfo['primary_ip'] : null, !empty($nfo['primary_port']) ? $nfo['primary_port'] : null, $contentType[0], $nfo['total_time'], $nfo['http_code']));
                $this->fmt->endContain();
              }

            }

            // date
            if(($length < 128) && static::$env['supportsDate'] && !preg_match('/[^A-Za-z0-9.:+\s\-\/]/', $subject)){
              try{
                $date   = new \DateTime($subject);
                $errors = \DateTime::getLastErrors();

                if(($errors['warning_count'] < 1) && ($errors['error_count'] < 1)){
                  $now    = new \Datetime('now');
                  $nowUtc = new \Datetime('now', new \DateTimeZone('UTC'));
                  $diff   = $now->diff($date);

                  $map = array(
                    'y' => 'yr',
                    'm' => 'mo',
                    'd' => 'da',                  
                    'h' => 'hr',
                    'i' => 'min',
                    's' => 'sec',
                  );

                  $timeAgo = 'now';
                  foreach($map as $k => $label){
                    if($diff->{$k} > 0){
                      $timeAgo = $diff->format("%R%{$k}{$label}");
                      break;
                    }
                  }

                  $tz   = $date->getTimezone();
                  $offs = round($tz->getOffset($nowUtc) / 3600);

                  if($offs > 0)
                    $offs = "+{$offs}";

                  $timeAgo .= ((int)$offs !== 0) ? ' ' . sprintf('%s (UTC%s)', $tz->getName(), $offs) : ' UTC';
                  $this->fmt->startContain('date', true);
                  $this->fmt->text('date', $timeAgo);
                  $this->fmt->endContain();
                  
                }  
              }catch(\Exception $e){
                // not a date
              }

            }

            // attempt to detect if this is a serialized string     
            static $unserializing = 0;
            $isSerialized = ($unserializing < 3)
              && (($subject[$length - 1] === ';') || ($subject[$length - 1] === '}'))              
              && in_array($subject[0], array('s', 'a', 'O'), true)
              && ((($subject[0] === 's') && ($subject[$length - 2] !== '"')) || preg_match("/^{$subject[0]}:[0-9]+:/s", $subject))
              && (($unserialized = @unserialize($subject)) !== false);

            if($isSerialized){
              $unserializing++;
              $this->fmt->startContain('serialized', true);
              $this->evaluate($unserialized);
              $this->fmt->endContain();
              $unserializing--;
            }

            // try to find out if it's a json-encoded string;
            // only do this for json-encoded arrays or objects, because other types have too generic formats                
            static $decodingJson = 0;
            $isJson = !$isSerialized && ($decodingJson < 3) && in_array($subject[0], array('{', '['), true);

            if($isJson){
              $decodingJson++;
              $data = json_decode($subject);

              // ensure created objects live enough for PHP to provide a unique hash
              if(is_object($data))
                $this->intObjects->attach($data);

              if($isJson = (json_last_error() === JSON_ERROR_NONE)){
                $this->fmt->startContain('json', true);
                $this->evaluate($data);
                $this->fmt->endContain();
              }  

              $decodingJson--;            
            }

            // attempt to match a regex            
            if(!$isSerialized && !$isJson && $length < 768){
              try{
                $components = $this->splitRegex($subject);
                if($components){
                  $regex = '';

                  $this->fmt->startContain('regex', true);
                  foreach($components as $component)
                    $this->fmt->text('regex-' . key($component), reset($component));
                  $this->fmt->endContain();
                }  

              }catch(\Exception $e){
                // not a regex
              }

            }
          }  
        }

        return;
    }  
 
    // if we reached this point, $subject must be an object

    // track objects to detect recursion
    static $hashes = array();      

    // hash ID of this object
    $hash = spl_object_hash($subject);
    $recursion = isset($hashes[$hash]);
 
    // sometimes incomplete objects may be created from string unserialization,
    // if the class to which the object belongs wasn't included until the unserialization stage...
    if($subject instanceof \__PHP_Incomplete_Class){
      $this->fmt->text('object');
      $this->fmt->emptyGroup('incomplete');
      return;
    }

    // check cache at this point
    if(!$recursion && $this->fmt->didCache($hash)){
      static::$debug['cacheHits']++;
      return;        
    }
  
    $reflector = new \ReflectionObject($subject);
    $this->fmt->startContain('class');
    $this->fromReflector($reflector);
    $this->fmt->text('object', ' object');
    $this->fmt->endContain();

    // already been here?
    if($recursion)
      return $this->fmt->emptyGroup('recursion');

    $hashes[$hash] = 1;

    $flags = \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED;

    if(static::$config['showPrivateMembers'])
      $flags |= \ReflectionProperty::IS_PRIVATE;

    $props = $magicProps = $methods = array();    

    if($reflector->hasMethod('__debugInfo')){
      $magicProps = $subject->__debugInfo();
    }else{
      $props = $reflector->getProperties($flags);
    }

    if(static::$config['showMethods']){
      $flags = \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED;

      if(static::$config['showPrivateMembers'])
        $flags |= \ReflectionMethod::IS_PRIVATE;          

      $methods = $reflector->getMethods($flags);
    }

    $constants  = $reflector->getConstants();
    $interfaces = $reflector->getInterfaces();
    $traits     = static::$env['is54'] ? $reflector->getTraits() : array();
    $parents    = static::getParentClasses($reflector);        

    // work-around for https://bugs.php.net/bug.php?id=49154
    // @see http://stackoverflow.com/questions/15672287/strange-behavior-of-reflectiongetproperties-with-numeric-keys
    if(!static::$env['is54']){      
      $props = array_values(array_filter($props, function($prop) use($subject){
        return !$prop->isPublic() || property_exists($subject, $prop->name);
      }));
    }
    
    // no data to display?        
    if(!$props && !$methods && !$constants && !$interfaces && !$traits){
      unset($hashes[$hash]);
      return $this->fmt->emptyGroup();
    }

    if(!$this->fmt->startGroup())
      return;

    // show contents for iterators
    if(static::$config['showIteratorContents'] && $reflector->isIterateable()){

      $itContents = iterator_to_array($subject);
      $this->fmt->sectionTitle(sprintf('Contents (%d)', count($itContents)));          

      foreach($itContents as $key => $value){
        $keyInfo = gettype($key);
        if($keyInfo === 'string'){
          $encoding = static::$env['mbStr'] ? mb_detect_encoding($key) : '';
          $length   = $encoding && ($encoding !== 'ASCII') ? static::strLen($key) . '; ' . $encoding : static::strLen($key);
          $keyInfo  = sprintf('%s(%s)', $keyInfo, $length);        
        }            

        $this->fmt->startRow();
        $this->fmt->text(array('key', 'iterator'), $key, sprintf('Iterator key: %s', $keyInfo));
        $this->fmt->colDiv();
        $this->fmt->sep('=>');
        $this->fmt->colDiv();
        $this->evaluate($value);
        //$this->evaluate($value instanceof \Traversable ? ((count($value) > 0) ? $value : (string)$value) : $value);
        $this->fmt->endRow();
      }
    }        

    // display the interfaces this objects' class implements
    if($interfaces){
      $items = array();            
      $this->fmt->sectionTitle('Implements');
      $this->fmt->startRow();
      $this->fmt->startContain('interfaces');          

      $i     = 0;
      $count = count($interfaces);

      foreach($interfaces as $name => $interface){
        $this->fromReflector($interface);

        if(++$i < $count)
          $this->fmt->sep(', ');
      }  

      $this->fmt->endContain();          
      $this->fmt->endRow();
    } 

    // traits this objects' class uses
    if($traits){       
      $items = array();      
      $this->fmt->sectionTitle('Uses');
      $this->fmt->startRow();
      $this->fmt->startContain('traits');            

      $i     = 0;
      $count = count($traits);          

      foreach($traits as $name => $trait){
        $this->fromReflector($trait);
        
        if(++$i < $count)  
          $this->fmt->sep(', ');                      
      }  

      $this->fmt->endContain();
      $this->fmt->endRow();             
    }

    // class constants
    if($constants){
      $this->fmt->sectionTitle('Constants');
      $max = max(array_map('static::strLen', array_keys($constants)));
      foreach($constants as $name => $value){
        $meta = null;              
        $type = array('const');
        foreach($parents as $parent){
          if($parent->hasConstant($name)){
            if($parent !== $reflector){
              $type[] = 'inherited';
              $meta = array('sub' => array(array('Prototype defined by', $parent->name)));
            }  
            break;
          }
        }

        $this->fmt->startRow();
        $this->fmt->sep('::');  
        $this->fmt->colDiv();
        $this->fmt->startContain($type);
        $this->fmt->text('name', $name, $meta, $this->linkify($parent, $name));
        $this->fmt->endContain();
        $this->fmt->colDiv($max - static::strLen($name));        
        $this->fmt->sep('=');
        $this->fmt->colDiv();
        $this->evaluate($value);          
        $this->fmt->endRow();
      }    
    }

    // object/class properties
    if($props){
      $this->fmt->sectionTitle('Properties');        

      $max = 0;
      foreach($props as $idx => $prop)
        if(($propNameLen = static::strLen($prop->name)) > $max)
          $max = $propNameLen;

      foreach($props as $idx => $prop){

        if($this->hasInstanceTimedOut())
          break;

        $bubbles     = array();
        $sourceClass = $prop->getDeclaringClass();
        $inherited   = $reflector->getShortName() !== $sourceClass->getShortName();
        $meta        = $sourceClass->isInternal() ? null : static::parseComment($prop->getDocComment());

        if($meta){
          if($inherited)
            $meta['sub'] = array(array('Declared in', $sourceClass->getShortName()));

          if(isset($meta['tags']['var'][0]))
            $meta['left'] = $meta['tags']['var'][0][0];

          unset($meta['tags']);        
        }

        if($prop->isProtected() || $prop->isPrivate())        
          $prop->setAccessible(true);

        $value = $prop->getValue($subject);

        $this->fmt->startRow();
        $this->fmt->sep($prop->isStatic() ? '::' : '->');
        $this->fmt->colDiv();

        $bubbles  = array();
        if($prop->isProtected())
          $bubbles[] = array('P', 'Protected');

        if($prop->isPrivate())
          $bubbles[] = array('!', 'Private');

        $this->fmt->bubbles($bubbles);

        $type = array('prop');

        if($inherited)
          $type[] = 'inherited';

        if($prop->isPrivate())
          $type[] = 'private';

        $this->fmt->colDiv(2 - count($bubbles));
        $this->fmt->startContain($type);
        $this->fmt->text('name', $prop->name, $meta, $this->linkify($prop));
        $this->fmt->endContain();
        $this->fmt->colDiv($max - static::strLen($prop->name));
        $this->fmt->sep('=');
        $this->fmt->colDiv();
        $this->evaluate($value);
        $this->fmt->endRow();
      }
    }    

    // __debugInfo()
    if($magicProps){
      $this->fmt->sectionTitle('Properties (magic)');

      $max = 0;
      foreach($magicProps as $name => $value)
        if(($propNameLen = static::strLen($name)) > $max)
          $max = $propNameLen;

      foreach($magicProps as $name => $value){

        if($this->hasInstanceTimedOut())
          break;

        // attempt to pull out doc comment from the "regular" property definition
        try{
          $prop = $reflector->getProperty($name);
          $meta = static::parseComment($prop->getDocComment());

        }catch(\Exception $e){
          $meta = null;
        }

        $this->fmt->startRow();
        $this->fmt->sep('->');
        $this->fmt->colDiv();

        $type = array('prop');

        $this->fmt->startContain($type);
        $this->fmt->text('name', $name, $meta);
        $this->fmt->endContain();
        $this->fmt->colDiv($max - static::strLen($name));
        $this->fmt->sep('=');
        $this->fmt->colDiv();
        $this->evaluate($value);
        $this->fmt->endRow();        
      }
    }      

    // class methods
    if($methods && !$this->hasInstanceTimedOut()){

      $this->fmt->sectionTitle('Methods');  
      foreach($methods as $idx => $method){

        $this->fmt->startRow();
        $this->fmt->sep($method->isStatic() ? '::' : '->');
        $this->fmt->colDiv();
        
        $bubbles = array();
        if($method->isAbstract())
          $bubbles[] = array('A', 'Abstract');

        if($method->isFinal())
          $bubbles[] = array('F', 'Final');

        if($method->isProtected())
          $bubbles[] = array('P', 'Protected');

        if($method->isPrivate())
          $bubbles[] = array('!', 'Private');

        $this->fmt->bubbles($bubbles);
         
        $this->fmt->colDiv(4 - count($bubbles));

        // is this method inherited?
        $inherited = $reflector->getShortName() !== $method->getDeclaringClass()->getShortName();

        $type = array('method');

        if($inherited)
          $type[] = 'inherited';

        if($method->isPrivate())
          $type[] = 'private';

        $this->fmt->startContain($type);

        $name = $method->name;
        if($method->returnsReference())
          $name = "&{$name}";    

        $this->fromReflector($method, $name, $reflector); 

        $paramCom   = $method->isInternal() ? array() : static::parseComment($method->getDocComment(), 'tags');
        $paramCom   = empty($paramCom['param']) ? array() : $paramCom['param'];
        $paramCount = $method->getNumberOfParameters();

        $this->fmt->sep('(');

        // process arguments
        foreach($method->getParameters() as $idx => $parameter){
          $meta      = null;
          $paramName = "\${$parameter->name}";
          $optional  = $parameter->isOptional();
          $variadic  = static::$env['is56'] && $parameter->isVariadic();

          if($parameter->isPassedByReference())
            $paramName = "&{$paramName}";

          if($variadic)
            $paramName = "...{$paramName}";

          $type = array('param');

          if($optional)
            $type[] = 'optional';

          $this->fmt->startContain($type);            
          
          // attempt to build meta
          foreach($paramCom as $tag){
            list($pcTypes, $pcName, $pcDescription) = $tag;
            if($pcName !== $paramName)
              continue;

            $meta = array('title' => $pcDescription);

            if($pcTypes)
              $meta['left'] = $pcTypes;

            break;
          }
       
          try{
            $paramClass = $parameter->getClass();
          }catch(\Exception $e){
            // @see https://bugs.php.net/bug.php?id=32177&edit=1
          }

          if(!empty($paramClass)){
            $this->fmt->startContain('hint');
            $this->fromReflector($paramClass, $paramClass->name);
            $this->fmt->endContain();
            $this->fmt->sep(' ');
          
          }elseif($parameter->isArray()){
            $this->fmt->text('hint', 'array');
            $this->fmt->sep(' ');            

          }else{
            $hasType = static::$env['is7'] && $parameter->hasType();
            if($hasType){
              $type = $parameter->getType();
              $this->fmt->text('hint', (string)$type);
              $this->fmt->sep(' ');
            }
          }

          $this->fmt->text('name', $paramName, $meta);

          if($optional){
            try{
              $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
              if($paramValue !== null){
                $this->fmt->sep(' = ');

                if(static::$env['is546'] && !$parameter->getDeclaringFunction()->isInternal() && $parameter->isDefaultValueConstant()){
                  $this->fmt->text('constant', $parameter->getDefaultValueConstantName(), 'Constant');

                }else{
                  $this->evaluate($paramValue, true);
                }
              }

            }catch(\Exception $e){
              // unable to retrieve default value?
            }

          }

          $this->fmt->endContain();

          if($idx < $paramCount - 1)
            $this->fmt->sep(', ');
        }
        $this->fmt->sep(')');
        $this->fmt->endContain();

        $hasReturnType = static::$env['is7'] && $method->hasReturnType();
        if($hasReturnType){
          $type = $method->getReturnType();
          $this->fmt->startContain('ret');
          $this->fmt->sep(':');
          $this->fmt->text('hint', (string)$type);
          $this->fmt->endContain();
        }

        $this->fmt->endRow();
      }
    }  

    unset($hashes[$hash]);
    $this->fmt->endGroup();

    $this->fmt->cacheLock($hash);
  }



  /**
   * Scans for known classes and functions inside the provided expression,
   * and linkifies them when possible
   *
   * @param   string $expression   Expression to format
   * @return  string               Formatted output
   */
  protected function evaluateExp($expression = null){

    if($expression === null)
      return;

    if(static::strLen($expression) > 120)
      $expression = substr($expression, 0, 120) . '...';

    $this->fmt->sep('> ');

    if(strpos($expression, '(') === false)
      return $this->fmt->text('expTxt', $expression);

    $keywords = array_map('trim', explode('(', $expression, 2));
    $parts = array();

    // try to find out if this is a function
    try{
      $reflector = new \ReflectionFunction($keywords[0]);
      $parts[] = array($keywords[0], $reflector, '');
    
    }catch(\Exception $e){

      if(stripos($keywords[0], 'new ') === 0){
        $cn = explode(' ' , $keywords[0], 2);

        // linkify 'new keyword' (as constructor)
        try{          
          $reflector = new \ReflectionMethod($cn[1], '__construct');
          $parts[] = array($cn[0], $reflector, '');

        }catch(\Exception $e){
          $reflector = null;
          $parts[] = $cn[0];
        }            

        // class name...
        try{          
          $reflector = new \ReflectionClass($cn[1]);
          $parts[] = array($cn[1], $reflector, ' ');

        }catch(\Exception $e){
          $reflector = null;
          $parts[] = $cn[1];
        }      

      }else{

        // we can only linkify methods called statically
        if(strpos($keywords[0], '::') === false)
          return $this->fmt->text('expTxt', $expression);

        $cn = explode('::', $keywords[0], 2);

        // attempt to linkify class name
        try{
          $reflector = new \ReflectionClass($cn[0]);
          $parts[] = array($cn[0], $reflector, '');

        }catch(\Exception $e){
          $reflector = null;
          $parts[] = $cn[0];
        }        

        // perhaps it's a static class method; try to linkify method
        try{
          $reflector = new \ReflectionMethod($cn[0], $cn[1]);
          $parts[] = array($cn[1], $reflector, '::');

        }catch(\Exception $e){
          $reflector = null;
          $parts[] = $cn[1];
        }              
      }
    }

    $parts[] = "({$keywords[1]}";    

    foreach($parts as $element){
      if(!is_array($element)){
        $this->fmt->text('expTxt', $element);
        continue;
      }

      list($text, $reflector, $prefix) = $element;

      if($prefix !== '')
        $this->fmt->text('expTxt', $prefix);      

      $this->fromReflector($reflector, $text);
    }
  
  }



  /**
   * Calculates real string length
   *
   * @param   string $string
   * @return  int
   */
  protected static function strLen($string){
    $encoding = function_exists('mb_detect_encoding') ? mb_detect_encoding($string) : false;   
    return $encoding ? mb_strlen($string, $encoding) : strlen($string);
  }



  /**
   * Safe str_pad alternative
   *
   * @param   string $string
   * @param   int $padLen
   * @param   string $padStr
   * @param   int $padType
   * @return  string
   */
  protected static function strPad($input, $padLen, $padStr = ' ', $padType = STR_PAD_RIGHT){
    $diff = strlen($input) - static::strLen($input);
    return str_pad($input, $padLen + $diff, $padStr, $padType);
  }

}



/**
 * Formatter abstraction
 */ 
abstract class RFormatter{

  /**
   * Flush output and send contents to the output device
   */ 
  abstract public function flush();

  /**
   * Generate a base entity
   *
   * @param  string|array $type
   * @param  string|null $text
   * @param  string|array|null $meta
   * @param  string|null $uri
   */ 
  abstract public function text($type, $text = null, $meta = null, $uri = null);

  /**
   * Generate container start token
   *
   * @param  string|array $type
   * @param  string|bool $label
   */ 
  public function startContain($type, $label = false){}

  /**
   * Generate container ending token
   */ 
  public function endContain(){}  

  /**
   * Generate empty group token
   *
   * @param  string $prefix
   */ 
  public function emptyGroup($prefix = ''){}

  /**
   * Generate group start token
   *
   * This method must return boolean TRUE on success, false otherwise (eg. max depth reached).
   * The evaluator will skip this group on FALSE
   *
   * @param   string $prefix
   * @return  bool
   */ 
  public function startGroup($prefix = ''){}

  /**
   * Generate group ending token   
   */ 
  public function endGroup(){}

  /**
   * Generate section title
   *
   * @param  string $title
   */ 
  public function sectionTitle($title){}

  /**
   * Generate row start token
   */ 
  public function startRow(){}

  /**
   * Generate row ending token
   */ 
  public function endRow(){}

  /**
   * Column divider (cell delimiter)
   *
   * @param  int $padLen
   */ 
  public function colDiv($padLen = null){}

  /**
   * Generate modifier tokens
   *
   * @param  array $items
   */ 
  public function bubbles(array $items){}

  /**
   * Input expression start
   */ 
  public function startExp(){}

  /**
   * Input expression end
   */ 
  public function endExp(){}

  /**
   * Root starting token
   */ 
  public function startRoot(){}

  /**
   * Root ending token
   */ 
  public function endRoot(){}

  /**
   * Separator token
   *
   * @param  string $label
   */
  public function sep($label = ' '){}  

  /**
   * Resolve cache request
   *
   * If the ID is not present in the cache, then a new cache entry is created
   * for the given ID, and string offsets are captured until cacheLock is called
   *
   * This method must return TRUE if the ID exists in the cache, and append the cached item
   * to the output, FALSE otherwise.
   *
   * @param   string $id
   * @return  bool
   */ 
  public function didCache($id){
    return false;
  }

  /**
   * Ends cache capturing for the given ID
   *
   * @param  string $id
   */ 
  public function cacheLock($id){}

}




/**
 * Generates the output in HTML5 format
 *   
 */
class RHtmlFormatter extends RFormatter{

  protected

    /**
     * Actual output
     *
     * @var  string
     */  
    $out = '',

    /**
     * Tracks current nesting level
     *
     * @var  int
     */  
    $level = 0,
     
    /**
     * Stores tooltip content for all entries
     *
     * To avoid having duplicate tooltip data in the HTML, we generate them once,
     * and use references (the Q index) to pull data when required; 
     * this improves performance significantly 
     *
     * @var  array
     */     
    $tips = array(),

    /**
     * Used to cache output to speed up processing.
     *
     * Contains hashes as keys and string offsets as values.
     * Cached objects will not be processed again in the same query
     *
     * @var  array
     */ 
    $cache = array(),

    /**
     * Map of used HTML tag and attributes
     *
     * @var string
     */
    $def = array();



  protected static

    /**
     * Instance counter
     *
     * @var  int
     */   
    $counter = 0,

    /**
     * Tracks style/jscript inclusion state
     *
     * @var  bool
     */    
    $didAssets = false;


  public function __construct(){

    if(ref::config('validHtml')){

      $this->def = array(
        'base'   => 'span',
        'tip'    => 'div',
        'cell'   => 'data-cell',
        'table'  => 'data-table',
        'row'    => 'data-row',
        'group'  => 'data-group',
        'gLabel' => 'data-gLabel',
        'match'  => 'data-match',
        'tipRef' => 'data-tip',
      );


    }else{

      $this->def = array(
        'base'   => 'r',
        'tip'    => 't',
        'cell'   => 'c',
        'table'  => 't',        
        'row'    => 'r',        
        'group'  => 'g',
        'gLabel' => 'gl',
        'match'  => 'm',
        'tipRef' => 'h',
      );

    }

  }



  public function flush(){
    print $this->out;
    $this->out   = '';
    $this->cache = array();
    $this->tips  = array();
  }


  public function didCache($id){

    if(!isset($this->cache[$id])){
      $this->cache[$id] = array();
      $this->cache[$id][] = strlen($this->out);     
      return false;
    }

    if(!isset($this->cache[$id][1])){
      $this->cache[$id][0] = strlen($this->out);
      return false;
    }

    $this->out .= substr($this->out, $this->cache[$id][0], $this->cache[$id][1]);
    return true;
  }
  
  public function cacheLock($id){
    $this->cache[$id][] = strlen($this->out) - $this->cache[$id][0];
  }


  public function sep($label = ' '){
    $this->out .= $label !== ' ' ? '<i>' . static::escape($label) . '</i>' : $label;
  }  

  public function text($type, $text = null, $meta = null, $uri = null){

    if(!is_array($type))
      $type = (array)$type;

    $tip  = '';
    $text = ($text !== null) ? static::escape($text) : static::escape($type[0]);    

    if(in_array('special', $type)){
      $text = strtr($text, array(
        "\r" => '<i>\r</i>',     // carriage return
        "\t" => '<i>\t</i>',     // horizontal tab
        "\n" => '<i>\n</i>',     // linefeed (new line)
        "\v" => '<i>\v</i>',     // vertical tab
        "\e" => '<i>\e</i>',     // escape
        "\f" => '<i>\f</i>',     // form feed
        "\0" => '<i>\0</i>',
      ));
    }

    // generate tooltip reference (probably the slowest part of the code ;)
    if($meta !== null){       
      $tipIdx = array_search($meta, $this->tips, true);

      if($tipIdx === false)
        $tipIdx = array_push($this->tips, $meta) - 1;

      $tip = " {$this->def['tipRef']}=\"{$tipIdx}\"";      
      //$tip = sprintf('%s="%d"', $this->def['tipRef'], $tipIdx);
    }

    // wrap text in a link?
    if($uri !== null)
      $text = '<a href="' . $uri . '" target="_blank">' . $text . '</a>';

    $typeStr = '';
    foreach($type as $part)
      $typeStr .= " data-{$part}";

    $this->out .= "<{$this->def['base']}{$typeStr}{$tip}>{$text}</{$this->def['base']}>";    
    //$this->out .= sprintf('<%1$s%2$s %3$s>%4$s</%1$s>', $this->def['base'], $typeStr, $tip, $text);
  }

  public function startContain($type, $label = false){

    if(!is_array($type))
      $type = (array)$type;    

    if($label)
      $this->out .= '<br>';

    $typeStr = '';
    foreach($type as $part)
      $typeStr .= " data-{$part}";

    $this->out .= "<{$this->def['base']}{$typeStr}>";

    if($label)
      $this->out .= "<{$this->def['base']} {$this->def['match']}>{$type[0]}</{$this->def['base']}>";
  }    

  public function endContain(){
    $this->out .= "</{$this->def['base']}>";
  } 

  public function emptyGroup($prefix = ''){

    if($prefix !== '')
      $prefix = "<{$this->def['base']} {$this->def['gLabel']}>" . static::escape($prefix) . "</{$this->def['base']}>";
    
    $this->out .= "<i>(</i>{$prefix}<i>)</i>";
  }    


  public function startGroup($prefix = ''){

    $maxDepth = ref::config('maxDepth');

    if(($maxDepth > 0) && (($this->level + 1) > $maxDepth)){
      $this->emptyGroup('...');   
      return false;
    }   

    $this->level++;

    $expLvl = ref::config('expLvl');
    $exp = ($expLvl < 0) || (($expLvl > 0) && ($this->level <= $expLvl)) ? ' data-exp' : '';
    
    if($prefix !== '')
      $prefix = "<{$this->def['base']} {$this->def['gLabel']}>" . static::escape($prefix) . "</{$this->def['base']}>";
   
    $this->out .= "<i>(</i>{$prefix}<{$this->def['base']} data-toggle{$exp}></{$this->def['base']}><{$this->def['base']} {$this->def['group']}><{$this->def['base']} {$this->def['table']}>";

    return true;
  }

  public function endGroup(){
    $this->out .= "</{$this->def['base']}></{$this->def['base']}><i>)</i>";
    $this->level--;
  }
 
  public function sectionTitle($title){
    $this->out .= "</{$this->def['base']}><{$this->def['base']} data-tHead>{$title}</{$this->def['base']}><{$this->def['base']} {$this->def['table']}>";
  }

  public function startRow(){
    $this->out .= "<{$this->def['base']} {$this->def['row']}><{$this->def['base']} {$this->def['cell']}>";
  } 

  public function endRow(){
    $this->out .= "</{$this->def['base']}></{$this->def['base']}>";
  } 
     
  public function colDiv($padLen = null){
    $this->out .= "</{$this->def['base']}><{$this->def['base']} {$this->def['cell']}>";
  }   

  public function bubbles(array $items){

    if(!$items)
      return;

    $this->out .= "<{$this->def['base']} data-mod>";   

    foreach($items as $info)
      $this->out .= $this->text('mod-' . strtolower($info[1]), $info[0], $info[1]);

    $this->out .= "</{$this->def['base']}>";
  }      
      
  public function startExp(){
    $this->out .= "<{$this->def['base']} data-input>";
  }                    

  public function endExp(){
    if(ref::config('showBacktrace') && ($trace = ref::getBacktrace())){
      $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
      $path = strpos($trace['file'], $docRoot) !== 0 ? $trace['file'] : ltrim(str_replace($docRoot, '', $trace['file']), '/');
      $this->out .= "<{$this->def['base']} data-backtrace>{$path}:{$trace['line']}</{$this->def['base']}>";
    }

    $this->out .= "</{$this->def['base']}><{$this->def['base']} data-output>";
  } 

  public function startRoot(){
    $this->out .= '<!-- ref#' . ++static::$counter . ' --><div>' . static::getAssets() . '<div class="ref">';
  }                    

  public function endRoot(){
    $this->out .= "</{$this->def['base']}>";

    // process tooltips
    $tipHtml = '';
    foreach($this->tips as $idx => $meta){

      $tip = '';
      if(!is_array($meta))
        $meta = array('title' => $meta);

      $meta += array(
        'title'       => '',
        'left'        => '',
        'description' => '',
        'tags'        => array(),
        'sub'         => array(),            
      );

      $meta = static::escape($meta);
      $cols = array();

      if($meta['left'])
        $cols[] = "<{$this->def['base']} {$this->def['cell']} data-varType>{$meta['left']}</{$this->def['base']}>";

      $title = $meta['title'] ?       "<{$this->def['base']} data-title>{$meta['title']}</{$this->def['base']}>"       : '';
      $desc  = $meta['description'] ? "<{$this->def['base']} data-desc>{$meta['description']}</{$this->def['base']}>"  : '';
      $tags  = '';

      foreach($meta['tags'] as $tag => $values){
        foreach($values as $value){
          if($tag === 'param'){
            $value[0] = "{$value[0]} {$value[1]}";
            unset($value[1]);
          }

          $value  = is_array($value) ? implode("</{$this->def['base']}><{$this->def['base']} {$this->def['cell']}>", $value) : $value;
          $tags  .= "<{$this->def['base']} {$this->def['row']}><{$this->def['base']} {$this->def['cell']}>@{$tag}</{$this->def['base']}><{$this->def['base']} {$this->def['cell']}>{$value}</{$this->def['base']}></{$this->def['base']}>";
        }
      }

      if($tags)
        $tags = "<{$this->def['base']} {$this->def['table']}>{$tags}</{$this->def['base']}>";

      if($title || $desc || $tags)
        $cols[] = "<{$this->def['base']} {$this->def['cell']}>{$title}{$desc}{$tags}</{$this->def['base']}>";

      if($cols)
        $tip = "<{$this->def['base']} {$this->def['row']}>" . implode('', $cols) . "</{$this->def['base']}>";

      $sub = '';
      foreach($meta['sub'] as $line)
        $sub .= "<{$this->def['base']} {$this->def['row']}><{$this->def['base']} {$this->def['cell']}>" . implode("</{$this->def['base']}><{$this->def['base']} {$this->def['cell']}>", $line) . "</{$this->def['base']}></{$this->def['base']}>";
    
      if($sub)
        $tip .= "<{$this->def['base']} {$this->def['row']}><{$this->def['base']} {$this->def['cell']} data-sub><{$this->def['base']} {$this->def['table']}>{$sub}</{$this->def['base']}></{$this->def['base']}></{$this->def['base']}>";

      if($tip)
        $this->out .= "<{$this->def['tip']}>{$tip}</{$this->def['tip']}>";
    }

    if(($timeout = ref::getTimeoutPoint()) > 0)
      $this->out .= sprintf("<{$this->def['base']} data-error>Listing incomplete. Timed-out after %4.2fs</{$this->def['base']}>", $timeout);

    $this->out .= '</div></div><!-- /ref#' . static::$counter . ' -->';        
  }



  /**
   * Get styles and javascript (only generated for the 1st call)
   *
   * @return  string
   */
  public static function getAssets(){
    
    // first call? include styles and javascript
    if(static::$didAssets)
      return '';   

    ob_start();

    if(ref::config('stylePath') !== false){
      ?>
      <style>
        <?php readfile(str_replace('{:dir}', __DIR__, ref::config('stylePath'))); ?>
      </style>
      <?php
    }

    if(ref::config('scriptPath') !== false){
      ?>
      <script>
        <?php readfile(str_replace('{:dir}', __DIR__, ref::config('scriptPath'))); ?>
      </script>
      <?php
    }  

    // normalize space and remove comments
    $output = preg_replace('/\s+/', ' ', trim(ob_get_clean()));
    $output = preg_replace('!/\*.*?\*/!s', '', $output);
    $output = preg_replace('/\n\s*\n/', "\n", $output);

    static::$didAssets = true;
    return $output;
  }


  /**
   * Escapes variable for HTML output
   *
   * @param   string|array $var
   * @return  string|array
   */
  protected static function escape($var){
    return is_array($var) ? array_map('static::escape', $var) : htmlspecialchars($var, ENT_QUOTES);
  }

}



/**
 * Generates the output in plain text format
 *   
 */
class RTextFormatter extends RFormatter{

  protected

    /**
     * Actual output
     *
     * @var  string
     */  
    $out        = '',

    /**
     * Tracks current nesting level
     *
     * @var  int
     */  
    $level      = 0,    

    /**
     * Current indenting level
     *
     * @var  int
     */ 
    $indent     = 0,

    $lastIdx    = 0,
    $lastLineSt = 0,
    $levelPad   = array(0);



  public function flush(){
    print $this->out;    
    $this->out   = '';
    $this->cache = array();
  }

  public function sep($label = ' '){
    $this->out .= $label;
  }  

  public function text($type, $text = null, $meta = null, $uri = null){

    if(!is_array($type))
      $type = (array)$type;

    if($text === null)
      $text = $type[0];

    if(in_array('special', $type, true)){
      $text = strtr($text, array(
        "\r" => '\r',     // carriage return
        "\t" => '\t',     // horizontal tab
        "\n" => '\n',     // linefeed (new line)
        "\v" => '\v',     // vertical tab
        "\e" => '\e',     // escape
        "\f" => '\f',     // form feed
        "\0" => '\0',
      ));

      $this->out .= $text;
      return;
    }        

    $formatMap = array(
      'string'   => '%3$s "%2$s"',
      'integer'  => 'int(%2$s)',
      'double'   => 'double(%2$s)',
      'true'     => 'bool(%2$s)',
      'false'    => 'bool(%2$s)',
      'key'      => '[%2$s]',
    );

    if(!is_string($meta))
      $meta = '';

    $this->out .= isset($formatMap[$type[0]]) ? sprintf($formatMap[$type[0]], $type[0], $text, $meta) : $text;
  }

  public function startContain($type, $label = false){

    if(!is_array($type))
      $type = (array)$type;   

    if($label)
      $this->out .= "\n" . str_repeat(' ', $this->indent + $this->levelPad[$this->level]) . "┗ {$type[0]} ~ ";
  }    

  public function emptyGroup($prefix = ''){
    $this->out .= "({$prefix})";
  }    

  public function startGroup($prefix = ''){

    $maxDepth = ref::config('maxDepth');

    if(($maxDepth > 0) && (($this->level + 1) > $maxDepth)){
      $this->emptyGroup('...');              
      return false;
    }

    $this->level++;  
    $this->out .= '(';  

    $this->indent += $this->levelPad[$this->level - 1];
    return true;
  }

  public function endGroup(){
    $this->out .= "\n" . str_repeat(' ', $this->indent) . ')';
    $this->indent -= $this->levelPad[$this->level - 1];
    $this->level--;
  }

  public function sectionTitle($title){
    $pad = str_repeat(' ', $this->indent + 2);
    $this->out .= sprintf("\n\n%s%s\n%s%s", $pad, $title, $pad, str_repeat('-', strlen($title)));
  }

  public function startRow(){
    $this->out .= "\n  " . str_repeat(' ', $this->indent);
    $this->lastLineSt = strlen($this->out);
  } 

  public function endRow(){
  } 
     
  public function colDiv($padLen = null){
    $padLen = ($padLen !== null) ? $padLen + 1 : 1;
    $this->out .= str_repeat(' ', $padLen);

    $this->lastIdx = strlen($this->out);
    $this->levelPad[$this->level] = $this->lastIdx - $this->lastLineSt + 2;     
  }   

  public function bubbles(array $items){    

    if(!$items){
      $this->out .= '  ';
      return;
    }

    $this->out .= '<';

    foreach($items as $item)
      $this->out .= $item[0];

    $this->out .= '>';
  }      

  public function endExp(){

    if(ref::config('showBacktrace') && ($trace = ref::getBacktrace()))
      $this->out .= ' - ' . $trace['file'] . ':' . $trace['line'];

    $this->out .= "\n" . str_repeat('=', strlen($this->out)) . "\n";
  }                    
 
  public function startRoot(){
    $this->out .= "\n\n";

  }                    

  public function endRoot(){
    $this->out .= "\n"; 
    if(($timeout = ref::getTimeoutPoint()) > 0)
      $this->out .= sprintf("\n-- Listing incomplete. Timed-out after %4.2fs -- \n", $timeout);    
  }

}



/**
 * Text formatter with color support for CLI -- unfinished
 *   
 */
class RCliTextFormatter extends RTextFormatter{

  public function sectionTitle($title){
    $pad = str_repeat(' ', $this->indent + 2);
    $this->out .= sprintf("\n\n%s\x1b[4;97m%s\x1b[0m", $pad, $title);    
  }

  public function startExp(){
    $this->out .= "\x1b[1;44;96m ";
  }                    

  public function endExp(){
    if(ref::config('showBacktrace') && ($trace = ref::getBacktrace()))
      $this->out .= "\x1b[0m\x1b[44;36m " . $trace['file'] . ':' . $trace['line'];

    $this->out .=  " \x1b[0m\n";
  }                                 

  public function endRoot(){
    $this->out .= "\n"; 
    if(($timeout = ref::getTimeoutPoint()) > 0)
      $this->out .= sprintf("\n\x1b[3;91m-- Listing incomplete. Timed-out after %4.2fs --\x1b[0m\n", $timeout);    
  }

}
