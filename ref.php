<?php



/**
 * Shortcut to ref, HTML mode
 *
 * @version  1.0
 * @param    mixed $args
 */
function r(){

  // arguments passed to this function
  $args = func_get_args();

  // options (operators) gathered by the expression parser;
  // this variable gets passed as reference to getInputExpressions(), which will store the operators in it
  $options = array();

  // doh
  $output = '';

  $ref = new ref('html');

  // names of the arguments that were passed to this function
  $expressions = ref::getInputExpressions($options);

  // something went wrong while trying to parse the source expressions?
  // if so, silently ignore this part and leave out the expression info
  if(func_num_args() !== count($expressions))
    $expressions = array_fill(0, func_num_args(), null);

  foreach($args as $index => $arg)
    $output .= $ref->query($arg, $expressions[$index]);

  // return the results if this function was called with the error suppression operator 
  if(in_array('@', $options, true))
    return $output;

  // IE goes funky if there's no doctype
  if(!headers_sent() && !ob_get_length())
    print '<!DOCTYPE HTML><html><head><title>REF</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>';

  print $output;

  // stop the script if this function was called with the bitwise not operator
  if(in_array('~', $options, true)){
    print '</body></html>';
    exit(0);
  }  
}



/**
 * Shortcut to ref, plain text mode
 *
 * @version  1.0
 * @param    mixed $args
 */
function rt(){
  $args        = func_get_args();
  $options     = array();  
  $output      = '';
  $expressions = ref::getInputExpressions($options);
  $ref         = new ref('text');

  if(func_num_args() !== count($expressions))
    $expressions = array_fill(0, func_num_args(), null);

  foreach($args as $index => $arg)
    $output .= $ref->query($arg, $expressions[$index]);

  if(in_array('@', $options, true))
    return $output;  

  if(!headers_sent())    
    header('Content-Type: text/plain; charset=utf-8');

  print $output;

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
    $time   = 0,

    /**
     * Configuration (+ default values)
     *
     * @var  array
     */     
    $config = array(

                // initial expand depth (for HTML mode only)
                'expandDepth'  => 1,

                // shortcut functions used to access the query method below;
                // if they are namespaced, the namespace must be present as well (methods are not supported)
                'shortcutFunc' => array('r', 'rt'),

                // callbacks for custom/external formatters (as associative array: format => callback)
                'formatter'    => array(),

                // when this option is set to TRUE, additional information is
                // returned (note that this seriously affects performance):
                // - string matches (date, file, functions, classes, json, serialized data, regex etc.)
                // - extra information for some resource types                
                // - contents of iterator objects
                'extendedInfo' => true,

                // stylesheet path (for HTML only);
                // 'false' means no styles
                'stylePath'    => '{:dir}/ref.css',

                // javascript path (for HTML only);
                // 'false' means no js                      
                'scriptPath'   => '{:dir}/ref.js',

                // when this option is set to TRUE, private properties are shown
                'showPrivateProperties' => false,

              );


  protected

    /**
     * Tracks current nesting level
     *
     * @var  int
     */  
    $level                 = 0,

    /**
     * Max. expand depth of this instance
     *
     * @var  int
     */     
    $expDepth              = 1,

    /**
     * Output format of this instance
     *
     * @var  string
     */     
    $format                = null,

    /**
     * Some environment variables
     * used to determine feature support
     *
     * @var  string
     */ 
    $env                   = array(),

    /**
     * Used to cache output to speed up processing.
     * Cached objects will not be processed again in the same query
     *
     * @var  array
     */ 
    $cache                 = array(),

    /**
     * When set to TRUE, private properties are shown.
     *
     * @var boolean
     */
    $showPrivateProperties = false;



  /**
   * Constructor
   *
   * @param   string $format        Output format, defaults to 'html'
   * @param   int|null $expDepth    Maximum expand depth (relevant to the HTML format)
   */
  public function __construct($format = 'html', $expDepth = null){

    $this->format   = $format;
    $this->expDepth = ($expDepth !== null) ? $expDepth : static::$config['expandDepth'];

    $this->env = array(

      // php 5.4+ ?
      'is54'         => version_compare(PHP_VERSION, '5.4') >= 0,

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
    throw new \Exception('No such property');
  }



  /**
   * Enforce proper use of this class
   *
   * @param   string $name
   * @param   mixed $value
   */
  public function __set($name, $value){
    throw new \Exception('Not allowed');
  }  



  /**
   * Used to dispatch output to a custom formatter
   *   
   * @param   string $name
   * @param   array $args
   * @return  string
   */
  public function __call($name, array $args = array()){

    if(isset(static::$config['formatters'][$name]))
      return call_user_func_array(static::$config['formatters'][$name], $args);

    throw new \Exception('Method not defined');
  }



  /**
   * Generate structured information about a variable/value/expression (subject)
   *   
   * @param   mixed $subject
   * @param   string $expression
   * @return  string
   */
  public function query($subject, $expression = null){

    $startTime     = microtime(true);
    $output        = $this->{"to{$this->format}"}('root', $this->evaluate($subject), $this->evaluateExp($expression));
    $this->cache   = array();
    static::$time += microtime(true) - $startTime; 

    return $output;
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
   * Timery utility
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
   * Get styles and javascript (only generated for the 1st call)
   *
   * @return  string
   */
  public static function getAssets(){

    // tracks style/jscript inclusion state (html only)
    static $didAssets = false;

    // first call? include styles and javascript
    if($didAssets)
      return '';   

    ob_start();

    if(static::$config['stylePath'] !== false){
      ?>
      <style scoped>
        <?php readfile(str_replace('{:dir}', __DIR__, static::$config['stylePath'])); ?>
      </style>
      <?php
    }

    if(static::$config['scriptPath'] !== false){
      ?>
      <script>
        <?php readfile(str_replace('{:dir}', __DIR__, static::$config['scriptPath'])); ?>
      </script>
      <?php
    }  

    // normalize space and remove comments
    $output = preg_replace('/\s+/', ' ', trim(ob_get_clean()));
    $output = preg_replace('!/\*.*?\*/!s', '', $output);
    $output = preg_replace('/\n\s*\n/', "\n", $output);

    $didAssets = true;

    return $output;
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
   * Determines the input expression(s) passed to the shortcut function
   *
   * @param   array &$options   Optional, options to gather (from operators)
   * @return  array             Array of string expressions
   */
  public static function getInputExpressions(array &$options = null){    

    // used to determine the position of the current call,
    // if more queries calls were made on the same line
    static $lineInst = array();

    // pull only basic info with php 5.3.6+ to save some memory
    $trace = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) : debug_backtrace();
    
    while($callee = array_pop($trace)){

      // extract only the information we neeed
      $callee = array_intersect_key($callee, array_fill_keys(array('file', 'function', 'line'), false));
      extract($callee);

      // skip, if the called function doesn't match the shortcut function name
      if(!$function || !preg_grep("/{$function}/i" , static::$config['shortcutFunc']))
        continue;

      if(!$line || !$file)
        return array();
    
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
    }
  }



  /**
   * Generates the output in plain text format
   *   
   * @param   string $element
   * @param   string|null $arg1
   * @param   string|null $arg2
   * @param   string|array|null $meta
   * @return  string
   */
  protected function toText($element, $arg1 = null, $arg2 = null, $meta = null){

    switch($element){
      case 'sep':
        return $arg1;

      case 'text':
        if($arg2 === null)
          $arg2 = $arg1;

        if($arg1 === 'specialString'){
          $arg2 = strtr($arg2, array(
            "\r" => '\r',     // carriage return
            "\t" => '\t',     // horizontal tab
            "\n" => '\n',     // linefeed (new line)
            "\v" => '\v',     // vertical tab
            "\e" => '\e',     // escape
            "\f" => '\f',     // form feed
            "\0" => '\0',
          ));
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

        return isset($formatMap[$arg1]) ? sprintf($formatMap[$arg1], $arg1, $arg2, $meta) : $arg2;

      case 'match':
        return ($arg1 !== 'regex') ? "\n~~ {$arg1}: {$arg2}" : '';

      case 'contain':
        return ($arg1 !== 'regex') ? $arg2 : '';

      case 'link':
        return $arg1;

      case 'group':
        $prefix = ($arg2 !== null) ? $arg2 : '';
        if($arg1){
          $arg1 =  $arg1 . "\n";
          $prefix .= " \xe2\x96\xbc";
        }

        return "({$prefix}{$arg1})";

      case 'section':        
        $output = '';

        if($arg2 !== null)
          $output .= "\n\n " . $arg2 . "\n " . str_repeat('-', static::strLen($arg2));

        $lengths = array();

        // determine maximum column width
        foreach($arg1 as $item)
          foreach($item as $colIdx => $c)
            if(!isset($lengths[$colIdx]) || $lengths[$colIdx] < static::strLen($c))
              $lengths[$colIdx] = static::strLen($c);

        foreach($arg1 as $item){
          $lastColIdx = count($item) - 1;
          $padLen     = 0;
          $output    .= "\n  ";

          foreach($item as $colIdx => $c){

            // skip empty columns
            if($lengths[$colIdx] < 1)
              continue;

            if($colIdx < $lastColIdx){
              $output .= static::strPad($c, $lengths[$colIdx]) . ' ';
              $padLen += $lengths[$colIdx] + 1;
              continue;
            }
       
            // indent the entire block
            $output .= str_replace("\n", "\n" . str_repeat(' ', $padLen + 2), $c);
          }         
        }

        return $output;

      case 'bubbles':
        return $arg1 ? '[' . implode('', $arg1) . '] ' : '';

      case 'root':
        return sprintf("\n%s\n%s\n%s\n", $arg2, str_repeat('=', static::strLen($arg2)), $arg1);        
        
      default:
        return '';

    }
  }



  /**
   * Generates the output in HTML5 format
   *   
   * @param   string $element
   * @param   string|null $arg1
   * @param   string|null $arg2
   * @param   string|array|null $meta
   * @return  string
   */
  protected function toHtml($element, $arg1 = null, $arg2 = null, $meta = null){

    // stores tooltip content for all entries;
    // to avoid having duplicate tooltip data in the HTML, we generate them once,
    // and use references (the Q index) to pull data when required; 
    // this improves performance significantly    
    static $tips = array();

    switch($element){
      case 'sep':
        return '<i>' . static::escape($arg1) . '</i>';

      case 'text':
        $tip  = '';
        $arg2 = ($arg2 !== null) ? static::escape($arg2) : static::escape($arg1);

        if($arg1 === 'specialString'){
          $arg2 = strtr($arg2, array(
            "\r" => '<ins>\r</ins>',     // carriage return
            "\t" => '<ins>\t</ins>',     // horizontal tab
            "\n" => '<ins>\n</ins>',     // linefeed (new line)
            "\v" => '<ins>\v</ins>',     // vertical tab
            "\e" => '<ins>\e</ins>',     // escape
            "\f" => '<ins>\f</ins>',     // form feed
            "\0" => '<ins>\0</ins>',
          ));
        }

        // generate tooltip reference (probably the slowest part of the code ;)
        if($meta !== null){       
          $tipIdx = array_search($meta, $tips, true);

          if($tipIdx === false)
            $tipIdx = array_push($tips, $meta) - 1;

          $tip = ' data-tip="' . $tipIdx . '"';
        }  

        return ($arg1 !== 'name') ? "<b data-{$arg1}{$tip}>{$arg2}</b>" : "<b{$tip}>{$arg2}</b>";

      case 'match':
        return "<br><s>{$arg1}</s>{$arg2}";

      case 'contain':
        return "<b data-{$arg1}>{$arg2}</b>";

      case 'link':
        return "<a href=\"{$arg2}\" target=\"_blank\">{$arg1}</a>";

      case 'group':

        if($arg1){
          $exp = ($this->expDepth < 0) || (($this->expDepth > 0) && ($this->level <= $this->expDepth)) ? ' data-exp' : '';
          $arg1 = "<kbd{$exp}></kbd><div>{$arg1}</div>";          
        }

        $prefix = ($arg2 !== null) ? '<ins>' . static::escape($arg2) . '</ins>' : '';
        return "<i>(</i>{$prefix}{$arg1}<i>)</i>";

      case 'section':
        $title  = ($arg2 !== null) ? "<h4>{$arg2}</h4>" : '';
        $output = '';

        foreach($arg1 as $row)
          $output .= '<dl><dd>' . implode('</dd><dd>', $row) . '</dd></dl>';

        return "{$title}<div>{$output}</div>";

      case 'bubbles':
        return '<b data-mod>' . implode('', $arg1) . '</b>';

      case 'root':
        static $counter = 0;

        if($arg2 !== null)
          $arg2 = "<kbd>{$arg2}</kbd>";

        $assets = static::getAssets();
        $counter++;

        // process tooltips
        $tipHtml = '';
        foreach($tips as $idx => $meta){

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

          if($meta['title'])
            $tip = "<i>{$meta['title']}</i>";

          if($meta['description'])
            $tip .= "<i>{$meta['description']}</i>";

          $tip = "<i>{$tip}</i>";

          if($meta['left'])
            $tip = "<b>{$meta['left']}</b>{$tip}";

          $tags = '';
          foreach($meta['tags'] as $tag => $values){
            foreach($values as $value){
              if($tag === 'param'){
                $value[0] = "{$value[0]} {$value[1]}";
                unset($value[1]);
              }

              $value  = is_array($value) ? implode('</b><b>', $value) : $value;
              $tags  .= "<i><b>@{$tag}</b><b>{$value}</b></i>";
            }
          }

          if($tags)
            $tip .= "<u>{$tags}</u>";

          $sub = '';
          foreach($meta['sub'] as $line)
            $sub .= '<i><b>' . implode('</b> <b>', $line) . '</b></i>';

          if($sub !== '')
            $tip .= "<u>{$sub}</u>";

          $tipHtml .= "<q>{$tip}</q>";
        }

        // empty tip array
        $tips = array();

        return "<!-- ref#{$counter} --><div>{$assets}<div class=\"ref\">{$arg2}<div>{$arg1}</div>{$tipHtml}</div></div><!-- /ref#{$counter} -->";

      default:
        return '';

    }
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
    
    // check if we already have this in the cache
    if(isset($this->cache[__FUNCTION__][$hash]))
      return $this->cache[__FUNCTION__][$hash];

    $f     = "to{$this->format}";
    $items = array($reflector);

    if(($single === '') && ($reflector instanceof \ReflectionClass))
      $items = static::getParentClasses($reflector);

    foreach($items as &$item){

      $name     = ($single !== '') ? $single : $item->getName();
      $comments = $item->isInternal() ? array() : static::parseComment($item->getDocComment());
      $meta     = array('sub' => array());

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

        if($item instanceof \ReflectionMethod){
          try{
            $proto = $item->getPrototype();
            $meta['sub'][] = array('Prototype defined by', $proto->class);
          }catch(\Exception $e){}
        }  

        $item = $this->linkify($this->{$f}('text', 'name', $name, $meta), $item);
        continue;
      }

      $bubbles = array();

      // @todo: maybe - list interface methods
      if(!($item->isInterface() || ($this->env['is54'] && $item->isTrait()))){

        if($item->isAbstract())
          $bubbles[] = $this->{$f}('text', 'mod-abstract', 'A', 'Abstract');

        if($item->isFinal())
          $bubbles[] = $this->{$f}('text', 'mod-final', 'F', 'Final');

        // php 5.4+ only
        if($this->env['is54'] && $item->isCloneable())
          $bubbles[] = $this->{$f}('text', 'mod-cloneable', 'C', 'Cloneable');

        if($item->isIterateable())
          $bubbles[] = $this->{$f}('text', 'mod-iterateable', 'X', 'Iterateable');            
      
      }

      if($item->isInterface() && $single !== '')
       $bubbles[] = $this->{$f}('text', 'mod-interface', 'I', 'Interface');

      $bubbles = $bubbles ? $this->{$f}('bubbles', $bubbles) : '';
      $name = $this->{$f}('text', 'name', $name, $meta);

      if($item->isInterface() && $single === '')
        $name .= sprintf(' (%d)', count($item->getMethods()));

      $item = $bubbles . $this->linkify($name, $item);
    }

    // store in cache and return
    return $this->cache[__FUNCTION__][$hash] = count($items) > 1 ? implode($this->{$f}('sep', ' :: '), $items) : $items[0];
  }



  /**
   * Generates an URL that points to the documentation page relevant for the requested context
   *
   * For internal functions and classes, the URI will point to the local PHP manual
   * if installed and configured, otherwise to php.net/manual (the english one)
   *
   * @param   string $node            Text to linkify
   * @param   Reflector $reflector    Reflector object (used to determine the URL scheme for internal stuff)
   * @param   string|null $constant   Constant name, if this is a request to linkify a constant
   * @return  string                  Updated text
   */
  protected function linkify($node, \Reflector $reflector, $constant = null){

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

    $url = '';    
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
        case ($type === 'function') && class_exists('WP') && defined('ABSPATH') && defined('WPINC'):
          if(strpos($reflector->getFileName(), realpath(ABSPATH . WPINC)) === 0){
            $url = sprintf('http://queryposts.com/function/%s', urlencode(strtolower($reflector->getName())));
            break;
          }

        // @todo: handle more apps
      }      

    }

    if($url !== '')
      return $this->{"to{$this->format}"}('link', $node, $url);

    return $node;
  }



  /**
   * Evaluates the given variable
   *
   * @param   mixed $subject    Variable to query
   * @param   bool $specialStr  Should this be interpreted as a special string?
   * @return  mixed             Result (both HTML and text modes generate strings)
   */
  protected function evaluate(&$subject, $specialStr = false){

    // internal shortcut for to(Format) methods
    $f = "to{$this->format}";

    switch($type = gettype($subject)){
    
      // null value
      case 'NULL':
        return $this->{$f}('text', 'null');

      // integer/double/float
      case 'integer':
      case 'double':
        return $this->{$f}('text', $type, $subject, $type);

      // boolean
      case 'boolean':
        $text = $subject ? 'true' : 'false';
        return $this->{$f}('text', $text, $text, $type);

      // arrays
      case 'array':

        // empty array?
        if(empty($subject))
          return $this->{$f}('text', 'array') . $this->{$f}('group');

        if(isset($subject[static::MARKER_KEY])){
          unset($subject[static::MARKER_KEY]);
          return $this->{$f}('text', 'array') . $this->{$f}('group', null, 'recursion');          
        }  

        $count = count($subject);
        $subject[static::MARKER_KEY] = true;

        // use splFixedArray() on PHP 5.4+ to save up some memory, because the subject array
        // might contain a huge amount of entries.
        // (note: lower versions of PHP 5.3 throw a heap corruption error after 10K entries)
        // A more efficient way is to build the items as we go as strings,
        // by concatenating the info foreach entry, but then we loose the flexibility that the
        // entity/group/section methods provide us (exporting data in different formats
        // and indenting in text mode would become harder)
        $section = $this->env['is54'] ? new \SplFixedArray($count) : array();
        $idx     = 0;

        $this->level++;
        foreach($subject as $key => &$value){
          
          // ignore our temporary marker
          if($key === static::MARKER_KEY)
            continue;      

          // first recursion level detection;
          // this is optional (used to print consistent recursion info)
          if(is_array($value)){

            // save current value in a temporary variable
            $buffer = $value;

            // assign new value
            $value = ($value !== 1) ? 1 : 2;
            
            // if they're still equal, then we have a reference            
            if($value === $subject){
              $value = $buffer;                      
              $value[static::MARKER_KEY] = true;
              $output = $this->evaluate($value);
              $this->level--;
              return $output;
            }

            // restoring original value
            $value = $buffer;          
          }

          $keyInfo = gettype($key);

          if($keyInfo === 'string'){
            $encoding = $this->env['mbStr'] ? mb_detect_encoding($key) : '';
            $keyLen = $encoding && ($encoding !== 'ASCII') ? static::strLen($key) . '; ' . $encoding : static::strLen($key);
            $keyInfo = "{$keyInfo}({$keyLen})";
          }

          $section[$idx++] = array(
            $this->{$f}('text', 'key', $key, "Key: {$keyInfo}"),
            $this->{$f}('sep', '=>'),
            $this->evaluate($value, $specialStr),
          );
        }

        unset($subject[static::MARKER_KEY]);

        $output = $this->{$f}('text', 'array') . $this->{$f}('group', $this->{$f}('section', $section), $count);
        $this->level--;

        return $output;

      // resource
      case 'resource':
        $meta    = array();
        $resType = get_resource_type($subject);

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
            if(!static::$config['extendedInfo'])
              break;

            $meta = array(
               'size'       => sprintf('%d x %d', imagesx($subject), imagesy($subject)),
               'true_color' => imageistruecolor($subject),
            );

          break;

          case 'ldap link':
            if(!static::$config['extendedInfo'])
              break;

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

            if(!static::$config['extendedInfo'])
              break;

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
            if(!static::$config['extendedInfo'])
              break;

            while($row = @mysql_fetch_object($subject))
              $meta[] = (array)$row;

          break;

          // stream resource (fopen, fsockopen, popen, opendir etc)
          case 'stream':
            $meta = stream_get_meta_data($subject);
          break;

        }

        $section = array();
        $this->level++;
        foreach($meta as $key => $value){
          $section[] = array(
            $this->{$f}('text', 'resourceInfo', ucwords(str_replace('_', ' ', $key))),
            $this->{$f}('sep', ':'),
            $this->evaluate($value),
          );
        }

        $output = $this->{$f}('text', 'resource', strval($subject)) . $this->{$f}('group', $this->{$f}('section', $section), $resType);
        $this->level--;
        return $output;

      // string      
      case 'string':
     
        $length   = static::strLen($subject);       
        $encoding = $this->env['mbStr'] ? mb_detect_encoding($subject) : false;      
        $info     = $encoding && ($encoding !== 'ASCII') ? $length . '; ' . $encoding : $length;
        $add      = '';

        if($specialStr)
          return $this->{$f}('sep', '"') . $this->{$f}('text', 'specialString', $subject, "string({$info})") . $this->{$f}('sep', '"');

        // advanced checks only if there are 3 characteres or more
        if(static::$config['extendedInfo'] && $length > 2){

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
              
              $add .= $this->{$f}('match', 'file', $this->{$f}('text', 'file', implode('', $flags) . $size));

            }catch(\Exception $e){
              $isFile = false;
            }
          }

          // class/interface/function
          if(!preg_match('/[^\w+\\\\]/', $subject) && ($length < 96)){
            $isClass = class_exists($subject, false);
            if($isClass)
              $add .= $this->{$f}('match', 'class', $this->fromReflector(new \ReflectionClass($subject)));

            if(!$isClass && interface_exists($subject, false))
              $add .= $this->{$f}('match', 'interface', $this->fromReflector(new \ReflectionClass($subject)));

            if(function_exists($subject))
              $add .= $this->{$f}('match', 'function', $this->fromReflector(new \ReflectionFunction($subject)));
          }


          // skip serialization/json/date checks if the string appears to be numeric,
          // or if it's shorter than 5 characters
          if(!$isNumeric && ($length > 4)){
            if(($length < 128) && $this->env['supportsDate'] && !preg_match('/[^A-Za-z0-9.:+\s\-\/]/', $subject)){
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
                  $add     .= $this->{$f}('match', 'date', $this->{$f}('text', 'date', $timeAgo));
                  
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
              $add .= $this->{$f}('match', 'serialized', $this->evaluate($unserialized));
              $unserializing--;
            }

            // try to find out if it's a json-encoded string;
            // only do this for json-encoded arrays or objects, because other types have too generic formats                
            static $decodingJson = 0;
            $isJson = !$isSerialized && ($decodingJson < 3) && in_array($subject[0], array('{', '['), true);

            if($isJson){
              $decodingJson++;
              $json = json_decode($subject);

              if($isJson = (json_last_error() === JSON_ERROR_NONE))
                $add .= $this->{$f}('match', 'json', $this->evaluate($json));

              $decodingJson--;            
            }

            // attempt to match a regex            
            if($length < 768){
              try{
                $components = $this->splitRegex($subject);

                if($components){
                  $regex = '';
                  foreach($components as $component)
                    $regex .= $this->{$f}('text', 'regex-' . key($component), reset($component));

                  $add .= $this->{$f}('match', 'regex', $this->{$f}('contain', 'regex', $regex));
                }  

              }catch(\Exception $e){
                // not a regex
              }

            }
          }  

        }

        return $this->{$f}('text', 'string', $subject, "string({$info})") . $add;
 
      // if we reached this point, $subject must be an object
      default:

        // hash ID of this object
        $hash = spl_object_hash($subject);

        // do we have it in the cache?
        if(isset($this->cache[__FUNCTION__][$hash]))
          return $this->cache[__FUNCTION__][$hash];    

        // sometimes incomplete objects may be created from string unserialization,
        // if the class to which the object belongs wasn't included until the unserialization stage...
        if($subject instanceof \__PHP_Incomplete_Class)
          return $this->{$f}('text', 'object') . $this->{$f}('group', null, 'incomplete');
      
        $reflector  = new \ReflectionObject($subject);
        $objectName = $this->{$f}('contain', 'class', $this->fromReflector($reflector)) . ' ' . $this->{$f}('text', 'object');

        // track objects to detect recursion
        static $hashes = array();

        // already been here?
        if(isset($hashes[$hash]))
          return $objectName . $this->{$f}('group', null, 'recursion');

        $hashes[$hash] = 1;
        $group = '';
        $this->level++;

        // show contents for iterators
        if(static::$config['extendedInfo'] && $reflector->isIterateable()){

          $itContents = iterator_to_array($subject);
          $count      = count($itContents);
          $idx        = 0;
          $section    = $this->env['is54'] ? new \SplFixedArray($count) : array();

          foreach($itContents as $key => $value){
            $keyInfo = gettype($key);
            if($keyInfo === 'string'){
              $encoding = $this->env['mbStr'] ? mb_detect_encoding($key) : '';
              $length   = $encoding && ($encoding !== 'ASCII') ? static::strLen($key) . '; ' . $encoding : static::strLen($key);
              $keyInfo  = sprintf('%s(%s)', $keyInfo, $length);        
            }            

            $section[$idx++] = array(
              $this->{$f}('text', 'key', $key, sprintf('Iterator key: %s', $keyInfo)),
              $this->{$f}('sep', '=>'),
              $this->evaluate($value),
            );
          }

          $group .= $this->{$f}('section', $section, sprintf('Contents (%d)', $count));
        }

        $propertyTypes = \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED;
        if(static::$config['showPrivateProperties']){
          $propertyTypes |= \ReflectionProperty::IS_PRIVATE;
        }

        $props      = $reflector->getProperties($propertyTypes);
        $methods    = static::$config['extendedInfo'] ? $reflector->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) : array();
        $constants  = $reflector->getConstants();
        $interfaces = $reflector->getInterfaces();
        $traits     = $this->env['is54'] ? $reflector->getTraits() : array();
        $parents    = static::getParentClasses($reflector);        

        // work-around for https://bugs.php.net/bug.php?id=49154
        // @see http://stackoverflow.com/questions/15672287/strange-behavior-of-reflectiongetproperties-with-numeric-keys
        if(!$this->env['is54']){      
          $props = array_values(array_filter($props, function($prop) use($subject){
            return !$prop->isPublic() || property_exists($subject, $prop->name);
          }));
        } 
        
        // no data to display?        
        if(!$props && !$methods && !$constants && !$interfaces && !$traits){
          unset($hashes[$hash]);
          $this->level--;
          return $objectName . $this->{$f}('group');
        }  

        // display the interfaces this objects' class implements
        if($interfaces){
          $items = array();            
          foreach($interfaces as $name => $interface)
            $items[] = $this->fromReflector($interface);

          $row    = array($this->{$f}('contain', 'interfaces', implode($this->{$f}('sep', ', '), $items)));
          $group .= $this->{$f}('section', array($row), 'Implements');      
        } 

        // traits this objects' class uses
        if($traits){       
          $items = array();      
          foreach($traits as $name => $trait)
            $items[] = $this->fromReflector($trait);

          $row    = array($this->{$f}('contain', 'traits', implode($this->{$f}('sep', ', '), $items)));
          $group .= $this->{$f}('section', array($row), 'Uses');            
        }          

        // class constants
        $section = array();
        foreach($constants as $name => $value){
          $inherited = '';
          $meta = null;
          foreach($parents as $parent){
            if($parent->hasConstant($name)){
              if($parent !== $reflector){
                $inherited = '-inherited';
                $meta = array('sub' => array(array('Prototype defined by', $parent->name)));
              }  
              break;
            }
          }

          $section[] = array(
            $this->{$f}('sep', '::'),          
            $this->{$f}('contain', "constant{$inherited}", $this->linkify($this->{$f}('text', 'name', $name, $meta), $parent, $name)),
            $this->{$f}('sep', '='),
            $this->evaluate($value),
          );
        }

        if($section)
          $group .= $this->{$f}('section', $section, 'Constants');                  
              

        // object/class properties
        $section = array();
        foreach($props as $idx => $prop){
          $bubbles     = array();
          $sourceClass = $prop->getDeclaringClass();
          $inherited   = $reflector->getShortName() !== $sourceClass->getShortName() ? '-inherited' : '';

          // weird memory leak in ReflectionProperty::getDocComment() ?
          $meta        = $sourceClass->isInternal() ? null : static::parseComment($prop->getDocComment());

          if($meta){
            if($inherited)
              $meta['sub'] = array(array('Declared in', $sourceClass->getShortName()));

            // note that we need to make the left meta area have the same height as the content
            if(isset($meta['tags']['var'][0]))
              $meta['left'] = $meta['tags']['var'][0][0] . str_repeat("\n", substr_count(implode("\n", array_filter(array($meta['title'], $meta['description']))), "\n") + 1);

            unset($meta['tags']);        
          }

          if($prop->isProtected() || ($prop->isPrivate() && static::$config['showPrivateProperties']))
            $prop->setAccessible(true);

          $value = $prop->getValue($subject);

          if($prop->isProtected()){
            $prop->setAccessible(false);        
            $bubbles[] = $this->{$f}('text', 'mod-protected', 'P', 'Protected');        
          }  

          if($prop->isPrivate() && static::$config['showPrivateProperties']){
            $prop->setAccessible(false);
            $bubbles[] = $this->{$f}('text', 'mod-private', 'P', 'Private');
          }

          $section[] = array(
            $this->{$f}('sep', $prop->isStatic() ? '::' : '->'),
            $this->{$f}('bubbles', $bubbles),
            $this->{$f}('contain', "property{$inherited}", $this->linkify($this->{$f}('text', 'name', $prop->name, $meta), $prop)),
            $this->{$f}('sep', '='),
            $this->evaluate($value),
          );
        }

        if($section)
          $group .= $this->{$f}('section', $section, 'Properties');      

        // class methods
        $section = array();      
        foreach($methods as $idx => $method){
          $bubbles  = array();
          $args     = array();
          $paramCom = $method->isInternal() ? array() : static::parseComment($method->getDocComment(), 'tags');
          $paramCom = empty($paramCom['param']) ? array() : $paramCom['param'];      

          // process arguments
          foreach($method->getParameters() as $idx => $parameter){
            $meta      = null;
            $paramName = "\${$parameter->name}";
            $optional  = $parameter->isOptional() ? '-optional' : '';        

            $parts     = array();

            if($parameter->isPassedByReference())
              $paramName = "&{$paramName}";
            
            // attempt to build meta
            foreach($paramCom as $tag){
              list($pcTypes, $pcName, $pcDescription) = $tag;
              if($pcName !== $paramName)
                continue;

              $meta = array('title' => $pcDescription);

              if($pcTypes)
                $meta['left'] = $pcTypes . str_repeat("\n", substr_count($pcDescription, "\n") + 1);

              break;
            }
         
            try{
              $paramClass = $parameter->getClass();
            }catch(\Exception $e){
              // @see https://bugs.php.net/bug.php?id=32177&edit=1
            }

            if($paramClass)
              $parts[] = $this->fromReflector($paramClass, $paramClass->name);
            
            if($parameter->isArray())
              $parts[] = $this->{$f}('text', 'name', 'array');

            $parts[] = $this->{$f}('text', 'name', $paramName, $meta);

            if($optional){
              $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;            
              $parts[] = $this->{$f}('sep', '=');
              $parts[] = $this->evaluate($paramValue, true);
            }

            $args[] = $this->{$f}('contain', "parameter{$optional}", implode(' ', $parts));
          }
      
          if($method->isAbstract())
            $bubbles[] = $this->{$f}('text', 'mod-abstract', 'A', 'Abstract');

          if($method->isFinal())
            $bubbles[] = $this->{$f}('text', 'mod-final', 'F', 'Final');

          if($method->isProtected())
            $bubbles[] = $this->{$f}('text', 'mod-protected', 'P', 'Protected');

          $name = $method->name;

          if($method->returnsReference())
            $name = "&{$name}";

          // is this method inherited?
          $inherited = $reflector->getShortName() !== $method->getDeclaringClass()->getShortName();
          $type      = $inherited ? 'method-inherited' : 'method';
          $name      = $this->fromReflector($method, $name, $reflector);    
          $name      = $this->{$f}('contain', $type, $name. $this->{$f}('sep', '(') . implode($this->{$f}('sep', ', '), $args) . $this->{$f}('sep', ')'));

          $section[] = array(
            $this->{$f}('sep', $method->isStatic() ? '::' : '->'),
            $this->{$f}('bubbles', $bubbles),
            $name,
          );
        }

        if($section)
          $group .= $this->{$f}('section', $section, 'Methods');        

        unset($hashes[$hash]);
        $objectName .= $this->{$f}('group', $group);
        $this->level--;

        // store in cache, and return output
        return $this->cache[__FUNCTION__][$hash] = $objectName;
    }  
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
      return '';

    if(static::strLen($expression) > 120)
      $expression = substr($expression, 0, 120) . '...';

    $f      = "to{$this->format}";
    $prefix = $this->{$f}('sep', '> ');

    if(strpos($expression, '(') === false)
      return $prefix . $this->{$f}('text', 'expTxt', $expression);

    $fn    = array_map('trim', explode('(', $expression, 2));
    $fn[1] = $this->{$f}('text', 'expTxt', $fn[1]); // @todo, maybe: parse $fn[1] too (content within brackets)

    // try to find out if this is a function
    try{
      $reflector = new \ReflectionFunction($fn[0]);
      $fn[0] = $this->fromReflector($reflector, $fn[0]);
    
    }catch(\Exception $e){

      if(stripos($fn[0], 'new ') === 0){
        $cn = explode(' ' , $fn[0], 2);

        // linkify 'new keyword' (as constructor)
        try{          
          $reflector = new \ReflectionMethod($cn[1], '__construct');

          if($reflector->isInternal())            
            $cn[0] = $this->fromReflector($reflector, $cn[0]);

        }catch(\Exception $e){
          $reflector = null;
          $cn[0] = $this->{$f}('text', 'expTxt', $cn[0]);
        }            

        // class name...
        try{          
          $reflector = new \ReflectionClass($cn[1]);
          $cn[1] = $this->fromReflector($reflector, $cn[1]);

        }catch(\Exception $e){
          $reflector = null;
          $cn[1] = $this->{$f}('text', 'expTxt', $cn[1]);
        }      

        $fn[0] = implode(' ', $cn);

      }else{

        // we can only linkify methods called statically
        if(strpos($fn[0], '::') === false)
          return $prefix . $this->{$f}('text', 'expTxt', $expression);

        $cn = explode('::', $fn[0], 2);

        // perhaps it's a static class method; try to linkify method first
        try{
          $reflector = new \ReflectionMethod($cn[0], $cn[1]);
          $cn[1] = $this->fromReflector($reflector, $cn[1]);    

        }catch(\Exception $e){
          $reflector = null;
          $cn[1] = $this->{$f}('text', 'expTxt', $cn[1]);
        }        

        // attempt to linkify the class name as well
        try{
          $reflector = new \ReflectionClass($cn[0]);
          $cn[0] = $this->fromReflector($reflector, $cn[0]);

        }catch(\Exception $e){
          $reflector = null;
          $cn[0] = $this->{$f}('text', 'expTxt', $cn[0]);
        }

        // apply changes
        $fn[0] = implode('::', $cn);
      }
    }

    return $prefix . implode('(', $fn);
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
