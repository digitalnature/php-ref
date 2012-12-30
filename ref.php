<?php



/**
 * Shortcut to ref::build()
 *
 * @version  1.0
 * @param    mixed $args
 * @return   string
 */
function r(){

  $modifiers   = array();
  $args        = func_get_args();
  $output      = array();  
  $expressions = ref::getSourceExpressions($modifiers);
  $mode        = in_array('\\', $modifiers, true) ? 'text' : 'html';

  // something went wrong while trying to parse the source expressions;
  // silently ignore this part
  if(func_num_args() !== count($expressions))
    $expressions = array();

  foreach($args as $index => $arg)
    $output[] = ref::build($arg, isset($expressions[$index]) ? $expressions[$index] : null, $mode);

  $output = implode("\n\n", $output);

  // '@' modifier forces return only
  if(in_array('@', $modifiers, true))
    return $output;     

  if(headers_sent()){    
    print $output;
      
  }else{

    if($mode !== 'html')
      header('Content-Type: text/plain');

    // print html tags because IE freaks out if it doesn't get them
    print ($mode !== 'html') ? $output : '<!DOCTYPE HTML><html><body>' . $output;    
  }  

  // '~' modifier stops execution of the script
  if(in_array('~', $modifiers, true))
    exit(0);
}



/**
 * REF is a nicer alternative to PHP's print_r() / var_dump().
 *
 * @version  1.0
 * @author   digitalnature, http://digitalnature.eu
 */
class ref{

  const

    // shortcut function used to access the ::build() method below;
    // if its namespaced, the namespace must be present as well
    SHORTCUT_FUNC     = 'r',

    // regex used to parse tags in docblocks
    COMMENT_TAG_REGEX = '@([^ ]+)(?:\s+(.*?))?(?=(\n[ \t]*@|\s*$))';



  protected static

    // used to determine the position of the current call,
    // if more ::build() calls were made on the same line
    $lineInst  = array(),

    // tracks style/jscript inclusion state (html only)
    $didAssets = false,

    // instance index (gets displayed as comment in html-mode)
    $counter   = 1;          



  protected

    // temporary element (marker) for arrays, used to track recursions
    $arrayMarker  = null,

    // tracks objects to detect recursion
    $objectHashes = array(),

    // expand/collapse state
    $expanded     = true,

    // output format
    $format       = 'html';



  /**
   * Currently the constructor will only set up the output format.
   *
   * Other options might be added in the future
   *
   * @since   1.0
   * @param   string $format
   */
  public function __construct($format = 'html'){
    $this->format = $format;
  }



  /**
   * Builds a report with information about $subject
   *
   * @since   1.0
   * @param   mixed $subject    Variable to query   
   * @return  string
   */
  protected function transformSubject(&$subject){

     // expand first level
    $expState = $this->expanded ? 'exp' : 'col';

    $this->expanded = false;       

    // identify variable type
    switch(true){

      // null value
      case is_null($subject):        
        return $this->entity('null');

      // boolean
      case is_bool($subject):
        $text = $subject ? 'true' : 'false';
        return $this->entity($text, $text, gettype($subject));        

      // resource
      case is_resource($subject):

        $type = get_resource_type($subject);
        $name = $this->entity('resource', $subject);        

        // @see: http://php.net/manual/en/resource.php
        // need to add more...
        switch($type){

          // curl extension resource
          case 'curl':
            $meta = curl_getinfo($subject);
          break;

          // gd image extension resource
          case 'gd':

            $meta = array(
               'size'       => sprintf('%d x %d', imagesx($subject), imagesy($subject)),
               'true_color' => imageistruecolor($subject),
            );

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
            while($row = @mysql_fetch_object($subject))
              $meta[] = (array)$row;

          break;

          // stream resource (fopen, fsockopen, popen, opendir etc)
          case 'stream':
            $meta = stream_get_meta_data($subject);
          break;

          default:
            $meta = array();

        }

        $items = array();

        foreach($meta as $key => $value){
          $key = ucwords(str_replace('_', ' ', $key));
          $items[] = array(
            $this->entity('resourceInfo', htmlspecialchars($key, ENT_QUOTES)),
            $this->entity('sep', ':'),
            $this->transformSubject($value),
          );
        }  

        return $name . $this->group($type, $items ? $expState : null, $this->section($items));

      // integer or double
      case is_int($subject) || is_float($subject):
        return $this->entity(gettype($subject), $subject, gettype($subject));

      // string
      case is_string($subject):
        return $this->entity('string', htmlspecialchars($subject, ENT_QUOTES), sprintf('%s/%d', gettype($subject), strlen($subject)));        

      // arrays
      case is_array($subject):

        // empty array?
        if(empty($subject))      
          return $this->entity('array', 'Array') . $this->group();

        // set a marker to detect recursion
        if(!$this->arrayMarker)
          $this->arrayMarker = uniqid('', true);

        // if our marker element is present in the array it means that we were here before
        if(isset($subject[$this->arrayMarker]))
          return $this->entity('array', 'Array') . $this->group('Recursion');

        $subject[$this->arrayMarker] = true;

        // note that we must substract the marker element
        $itemCount = count($subject) - 1;
        $index = 0;

        // the array might contain a huge amount of entries; splFixedArray saves us some memory usage.
        // A more efficient way is to build the items as a string (concatenate each item),
        // but then we loose the flexibility that the entity/group/section methods provide us (exporting data in different formats becomes harder)
        $items = new \SplFixedArray($itemCount);

        foreach($subject as $key => &$value){

          // ignore our marker
          if($key === $this->arrayMarker)
            continue;

          $keyInfo = is_string($key) ? sprintf('String key (%d)', strlen($key)) : sprintf('Integer key', gettype($key));

          $items[$index++] = array(
            $this->entity('key', htmlspecialchars($key, ENT_QUOTES), $keyInfo),
            $this->entity('sep', '=>'),
            $this->transformSubject($value),
          );
        }

        // remove our temporary marker;
        // not really required, because the shortcut function doesn't take references, but we want to be nice :P
        unset($subject[$this->arrayMarker]);

        return $this->entity('array', 'Array') . $this->group($itemCount, $expState, $this->section($items));
    }

    // if we reached this point, $subject must be an object
    $classes = $internalParents = array();
    $haveParent = new \ReflectionObject($subject);

    // get parent/ancestor classes
    while($haveParent !== false){
      $classes[] = $haveParent;

      if($haveParent->isInternal())
        $internalParents[] = $haveParent;

      $haveParent = $haveParent->getParentClass();
    }
    
    foreach($classes as &$class){
     
      $modifiers = array();

      if($class->isAbstract())
        $modifiers[] = array('abstract', 'A', 'This class is abstract');

      if($class->isFinal())
        $modifiers[] = array('final', 'F', 'This class is final and cannot be extended');

      // php 5.4+ only
      if((PHP_MINOR_VERSION > 3) && $class->isCloneable())
        $modifiers[] = array('cloneable', 'C', 'Instances of this class can be cloned');

      if($class->isIterateable())
        $modifiers[] = array('iterateable', 'X', 'Instances of this class are iterateable');            
     
      $className = $class->getName();

      if($class->isInternal())
        $className = $this->anchor($className, 'class');

      $class = $this->modifiers($modifiers) . $this->entity('class', $className, $class);
    }  

    $objectName = implode($this->entity('sep', ' :: '), array_reverse($classes));
    $objectHash = spl_object_hash($subject);

    // already been here?
    if(in_array($objectHash, $this->objectHashes))
      return $this->entity('object', "{$objectName} Object") . $this->group('Recursion');

    // track hash
    $this->objectHashes[] = $objectHash;

    // again, because reflectionObjects can't be cloned apparently :)
    $reflector = new \ReflectionObject($subject);    

    $props      = $reflector->getProperties(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);    
    $methods    = $reflector->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);
    $constants  = $reflector->getConstants();
    $interfaces = $reflector->getInterfaces();
    $traits     = (PHP_MINOR_VERSION > 3) ? $reflector->getTraits() : array();

    // no data to display?
    if(!$props && !$methods && !$constants && !$interfaces && !$traits)
      return $this->entity('object', "{$objectName} Object") . $this->group();

    $output = '';

    // display the interfaces this objects' class implements
    if($interfaces){

      // no splFixedArray here because we don't expect one zillion interfaces to be implemented by this object
      $intfNames = array();

      foreach($interfaces as $name => $interface){

        $name = $interface->getName();

        if($interface->isInternal())
          $name = $this->anchor($name, 'class');

        $intfNames[] = $this->entity('interface', $name, $interface);      
      }  

      $output .= $this->section(array((array)implode($this->entity('sep', ', '), $intfNames)), 'Implements');
    }

    // class constants
    if($constants){
      $itemCount = count($constants);
      $index = 0;
      $items = new \SplFixedArray($itemCount);

      foreach($constants as $name => $value){

        foreach($internalParents as $parent)
          if($parent->hasConstant($name))
            $name = $this->anchor($name, 'constant', $parent->getName(), $name);

        $items[$index++] = array(
          $this->entity('sep', '::'),
          $this->entity('constant', $name),
          $this->entity('sep', '='),
          $this->transformSubject($value),
        );
        
      }

      $output .= $this->section($items, 'Constants');    
    }

    // traits this objects' class uses
    if($traits){  
      $traitNames = array();
      foreach($traits as $name => $trait)
        $traitNames[] = $this->entity('trait', $trait->getName(), $trait);

      $output .= $this->section((array)implode(', ', $traitNames), 'Uses');
    }

    // object/class properties
    if($props){
      $itemCount = count($props);
      $index = 0;
      $items = new \SplFixedArray($itemCount);

      foreach($props as $prop){
        $modifiers = array();

        if($prop->isProtected())        
          $prop->setAccessible(true);

        $value = $prop->getValue($subject);

        if($prop->isProtected())        
          $prop->setAccessible(false);        

        if($prop->isProtected())
          $modifiers[] = array('protected', 'P', 'This property is protected');

        $name = htmlspecialchars($prop->name, ENT_QUOTES);

        foreach($internalParents as $parent)
          if($parent->hasProperty($name))
            $name = $this->anchor($name, 'property', $parent->getName(), $name);

        $items[$index++] = array(
          $this->entity('sep', $prop->isStatic() ? '::' : '->'),
          $this->modifiers($modifiers),
          $this->entity('property', $name, $prop),
          $this->entity('sep', '='),
          $this->transformSubject($value),
        );

      }

      $output .= $this->section($items, 'Properties');
    }

    // class methods
    if($methods){
      $itemCount = count($methods);
      $index = 0;
      $items = new \SplFixedArray($itemCount);

      foreach($methods as $method){

        $paramStrings = $modifiers = array();

        $tags = static::parseComment($method->getDocComment(), 'tags');
        $tags = isset($tags['param']) ? $tags['param'] : array();

        // process arguments
        foreach($method->getParameters() as $parameter){

          $paramName = sprintf('$%s', $parameter->getName());

          if($parameter->isPassedByReference())
            $paramName = sprintf('&amp;%s', $paramName);

          try{
            $paramClass = $parameter->getClass();

          // @see https://bugs.php.net/bug.php?id=32177&edit=1
          }catch(\Exception $e){

          }

          $paramHint = '';

          if($paramClass)
            $paramHint = $this->entity('hint', $this->anchor($paramClass->getName(), 'class'), $paramClass);
          
          if($parameter->isArray())
            $paramHint = $this->entity('arrayHint', 'Array');

          $tip = null;
          
          foreach($tags as $tag){
            list($types, $varName, $varDesc) = $tag;
            if(ltrim($varName, '&') === $parameter->getName()){
              $tip = $varDesc;
              break;
            }  
          }
       
          if($parameter->isOptional()){
            $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;            
            $paramName  = $this->entity('param', $paramName, $tip);
            $paramName .= $this->entity('sep', ' = ');
            $paramName .= $this->entity('paramValue', $this->transformSubject($paramValue));

            if($paramHint)
              $paramName = $paramHint . ' ' . $paramName;

            $paramName  = $this->entity('optional', $paramName);

          }else{            
            $paramName = $this->entity('param', $paramName, $tip);

            if($paramHint)
              $paramName = $paramHint . ' ' . $paramName;            
          }

          $paramStrings[] = $paramName;
        }

        // is this method inherited?
        $inherited = $reflector->getShortName() !== $method->getDeclaringClass()->getShortName();
        $modTip    = $inherited ? sprintf('Inherited from ::%s', $method->getDeclaringClass()->getShortName()) : null;

        if($method->isAbstract())
          $modifiers[] = array('abstract', 'A', 'This method is abstract');

        if($method->isFinal())
          $modifiers[] = array('final', 'F', 'This method is final and cannot be overridden');

        if($method->isProtected())
          $modifiers[] = array('protected', 'P', 'This method is protected');        

        $name = $method->name;

        if($method->returnsReference())
          $name = '&' . $name;

        if($method->isInternal())
          $name = $this->anchor($name, 'method', $method->getDeclaringClass()->getName(), $name);          

        $name = $this->entity('method', $name, $method);

        if($inherited)
          $name = $this->entity('inherited', $name);

        $items[$index++] = array(
          $this->entity('sep', $method->isStatic() ? '::' : '->', $modTip),
          $this->modifiers($modifiers),
          $name . $this->entity('sep', ' (') . implode($this->entity('sep', ', '), $paramStrings) . $this->entity('sep', ')'),
        );       
      }

      $output .= $this->section($items, 'Methods');
    }

    return $this->entity('object', "{$objectName} Object") . $this->group('', $expState, $output);
  }



  /**
   * Scans for internal classes and functions inside the provided expression,
   * and formats them when possible
   *
   * @since   1.0
   * @param   string $expression      Expression to format
   * @return  string                  Formatted output
   */
  public function transformExpression($expression){

    $prefix = $this->entity('sep', '> ');

    if(strpos($expression, '(') === false)
      return $this->entity('exp', $prefix . $expression);

    $fn = explode('(', $expression, 2);

    // try to find out if this is a function
    try{
      $reflector = new \ReflectionFunction($fn[0]);        

      if($reflector->isInternal()){
        $fn[0] = $this->anchor($fn[0], 'function');
        $fn[0] = $this->entity('srcFunction', $fn[0], $reflector);
      }
    
    }catch(\Exception $e){

      if(stripos($fn[0], 'new ') === 0){

        $cn = explode(' ' , $fn[0], 2);

        // linkify 'new keyword' (as constructor)
        try{          
          $reflector = new \ReflectionMethod($cn[1], '__construct');
          if($reflector->isInternal()){
            $cn[0] = $this->anchor($cn[0], 'method', $cn[1], '__construct');
            $cn[0] = $this->entity('srcClass', $cn[0], $reflector);
          }              
        }catch(\Exception $e){
          $reflector = null;
        }            

        // class name...
        try{          
          $reflector = new \ReflectionClass($cn[1]);
          if($reflector->isInternal()){
            $cn[1] = $this->anchor($cn[1], 'class');
            $cn[1] = $this->entity('srcClass', $cn[1], $reflector);
          }              
        }catch(\Exception $e){
          $reflector = null;
        }      

        $fn[0] = implode(' ', $cn);

      }else{

        if(strpos($expression, '::') === false)
          return $this->entity('exp', $prefix . $expression);

        $cn = explode('::', $fn[0], 2);

        // perhaps it's a static class method; try to linkify method first
        try{
          $reflector = new \ReflectionMethod($cn[0], $cn[1]);

          if($reflector->isInternal()){
            $cn[1] = $this->anchor($cn[1], 'method', $cn[0], $cn[1]);
            $cn[1] = $this->entity('srcMethod', $cn[1], $reflector);
          }  

        }catch(\Exception $e){
          $reflector = null;
        }        

        // attempt to linkify the class name as well
        try{
          $reflector = new \ReflectionClass($cn[0]);

          if($reflector->isInternal()){
            $cn[0] = $this->anchor($cn[0], 'class');
            $cn[0] = $this->entity('srcClass', $cn[0], $reflector);
          }  

        }catch(\Exception $e){
          $reflector = null;
        }

        // apply changes
        $fn[0] = implode('::', $cn);
      }  
    }

    return $this->entity('exp', $prefix . implode('(', $fn));
  }



  /**
   * Creates the root structure that contains all the groups and entities
   *   
   * @since   1.0
   * @param   mixed $subject           Variable to query
   * @param   string|null $expression  Source expression string
   * @param   string $format           Output format
   * @return  string                   Formatted information
   */
  public static function build($subject, $expression = null, $format = 'html'){

    $startTime = microtime(true);  
    $startMem = memory_get_usage();

    $instance = new static($format);

    $varOutput = $instance->transformSubject($subject);    
    $expOutput = ($expression !== null) ? $instance->transformExpression($expression) : '';

    $memUsage = abs(round((memory_get_usage() - $startMem) / 1024, 2));
    $cpuUsage = round(microtime(true) - $startTime, 4);

    $instance = null;
    unset($instance);    

    switch($format){

      // HTML output
      case 'html':

        // first call? include styles and javascript
        if(!static::$didAssets){

          ob_start();
          ?>

          <style scoped>
            /*<![CDATA[*/
            <?php readfile(__DIR__ . '/ref.css'); ?>
            /*]]>*/
          </style>

          <script>
            /*<![CDATA[*/
            <?php readfile(__DIR__ . '/ref.js'); ?>
            /*]]>*/
          </script>       
          
          <?php    
          $varOutput = preg_replace('/\s+/', ' ', trim(ob_get_clean())) . $varOutput;
          static::$didAssets = true;
        }

        $output = sprintf('%s<div class="ref">%s</div>', $expOutput, $varOutput);

        return sprintf('<!-- ref #%d -->%s<!-- /ref (took %ss, %sK) -->', static::$counter++, $output, $cpuUsage, $memUsage);        

      // text output
      default:
        return sprintf("%s\n%s\n%s\n", $expOutput, str_repeat('^', strlen($expOutput)), $varOutput);

    }

  }



  /**
   * Creates a group
   *
   * @since   1.0
   * @param   string $prefix
   * @param   string|null $toggleState
   * @param   string $toggledText
   * @return  string
   */
  protected function group($prefix = '', $toggleState = null, $toggledText = ''){

    $content = ($toggleState !== null) ? $toggledText : '';

    switch($this->format){

      // HTML output
      case 'html':
        $content = ($toggleState !== null) ? '<a class="rToggle ' . $toggleState . '"></a><div>' . $content . '</div>' : '';

        if($prefix !== '')
          $prefix = '<b>' . $prefix . '</b>';

        return '<span class="rGroup">(' . $prefix . '</span>' . $content . '<span class="rGroup">)</span>';

      // text-only output
      default:

        if($content !== '')
          $content =  $content . "\n";

        return '(' . $prefix . $content . ')';
      
    }    
  }



  /**
   * Creates a group section
   *
   * @since   1.0
   * @param   array|splFixedArray $items    Array or SplFixedArray instance containing rows and columns (columns as arrays)
   * @param   string $title                 Section title, optional
   * @return  string
   */
  protected function section($items, $title = ''){

    switch($this->format){
      
      // HTML output
      case 'html':

        if($title !== '')
          $title = '<h4>' . $title .'</h4>';

        $content = '';

        foreach($items as $item){
          $last = array_pop($item);
          $defs = $item ? '<dt>' . implode('</dt><dt>', $item) . '</dt>' : '';
          $content .= '<dl><dt>' . $defs . '</dt><dd>' . $last . '</dd></dl>';
        }

        return $title . '<section>' . $content . '</section>';

      // text-only output
      default:

        $output = '';

        if($title !== '')
          $output .= sprintf("\n\n %s\n %s", $title, str_repeat('-', strlen($title)));

        $lengths = array();

        // determine maximum column width
        foreach($items as $item)
          foreach($item as $colIdx => $c)
            if(!isset($lengths[$colIdx]) || $lengths[$colIdx] < strlen($c))
              $lengths[$colIdx] = strlen($c);

        foreach($items as $item){

          $lastColIdx = count($item) - 1;
          $padLen     = 0;
          $output    .= "\n  ";

          foreach($item as $colIdx => $c){

            // skip empty columns
            if($lengths[$colIdx] < 1)
              continue;

            if($colIdx < $lastColIdx){
              $output .= str_pad($c, $lengths[$colIdx]). ' ';
              $padLen += $lengths[$colIdx] + 1;
              continue;
            }
        
            $lines   = explode("\n", $c);
            $output .= array_shift($lines);

            // we must indent the entire block
            foreach($lines as &$line)
              $line = str_repeat(' ', $padLen)  . $line;

            $output .= $lines ? "\n  " . implode("\n  ", $lines) : '';
          }         
        }

        return $output;
    }
  }



  /**
   * Generates an anchor that links to the documentation page relevant for the requested context
   *
   * The URI will point to the local PHP manual if installed and configured,
   * otherwise to php.net/manual (the english one)
   *
   * @since   1.0   
   * @todo    maybe, detect and linkify functions from popular frameworks
   * @param   string $scheme     Scheme to use; valid schemes are 'class', 'function', 'method', 'constant' (class only) and 'property'
   * @param   string $id1        Class or function name
   * @param   string|null $id2   Method name (required only for the 'method' scheme)
   * @return  string             URI string
   */
  protected function anchor($linkText, $scheme, $id1 = null, $id2 = null){

    // no links in text-mode :)
    if($this->format !== 'html')
      return $linkText;

    static $docRefRoot = null, $docRefExt  = null;

     // most people don't have this set
    if(!$docRefRoot)
      $docRefRoot = rtrim(ini_get('docref_root'), '/');

    if(!$docRefRoot)
      $docRefRoot = 'http://php.net/manual/en';

    if(!$docRefExt)
      $docRefExt = ini_get('docref_ext');

    if(!$docRefExt)
      $docRefExt = '.php';

    if($id1 === null)
      $id1 = $linkText;

    $args = array_filter(array($id1, $id2));

    foreach($args as &$arg)
      $arg = str_replace('_', '-', ltrim(strtolower($arg), '\\_'));

    $schemes = array(
      'class'     => $docRefRoot . '/class.%s'    . $docRefExt,
      'function'  => $docRefRoot . '/function.%s' . $docRefExt,
      'method'    => $docRefRoot . '/%1$s.%2$s'   . $docRefExt,
      'constant'  => $docRefRoot . '/class.%1$s'  . $docRefExt . '#%1$s.constants.%2$s',
      'property'  => $docRefRoot . '/class.%1$s'  . $docRefExt . '#%1$s.props.%2$s',
    );

    $uri = vsprintf($schemes[$scheme], $args);

    return sprintf('<a href="%s" target="_blank">%s</a>', $uri, $linkText);     
  }



  /**
   * Creates a single entity with the provided class, text and tooltip content
   *
   * @since   1.0
   * @param   string $class           Entity class ('r' will be prepended to it, then the entire thing gets camelized)
   * @param   string $text            Entity text content
   * @param   string|Reflector $tip   Tooltip content, or Reflector object from which to generate this content
   * @return  string                  SPAN tag with the provided information
   */
  protected function entity($class, $text = null, $tip = null){

    if($text === null)
      $text = $class;

    // we can't show all tip content in text-mode
    if($this->format !== 'html'){

      if(in_array($class, array('string', 'integer', 'double', 'true', 'false')))
        $text = $tip . ': ' . $text;

      return $text;
    }

    if($class === 'sep')
      $class = htmlspecialchars($class, ENT_QUOTES);

    if($tip instanceof \Reflector){

      // function/class/method is part of the core
      if(method_exists($tip, 'isInternal') && $tip->isInternal()){
        $tip = sprintf('Internal - part of %s (%s)', $tip->getExtensionName(), $tip->getExtension()->getVersion());

      // user-defined; attempt to get doc comments
      }else{

        $comments = static::parseComment($tip->getDocComment());

        $tip = '';

        if(!empty($comments['title']))
          $tip .= $comments['title'];

        if(!empty($comments['desc']))
          $tip .= "\n\n" . $comments['desc'];        
      }

    }

    $tip = empty($tip) ? '' : sprintf('<code>%s</code>', $tip);
    
    $class = ucfirst($class);

    if($tip !== '')
      $class .= ' rHasTip';

    return sprintf('<span class="r%s">%s%s</span>', $class, $text, $tip);
  }



  /**
   * Creates modifier bubbles
   *
   * @since   1.0
   * @param   string $class           Entity class ('r' will be prepended to it, then the entire thing gets camelized)
   * @param   string $text            Entity text content   
   * @return  string                  SPAN tag with the provided information
   */
  protected function modifiers(array $modifiers){

    switch($this->format){
      case 'html':

        foreach($modifiers as &$modifier)
          $modifier = $this->entity($modifier[0], $modifier[1], $modifier[2]);
        
        return '<span class="rModifiers">' . implode('', $modifiers) . '</span>';

      default:
        foreach($modifiers as &$modifier)
          $modifier = '[' . $modifier[1] . '] ';

        return implode('', $modifiers);

    } 
  }



  /**
   * Determines the input expression(s) passed to the shortcut function
   *
   * @since   1.0
   * @todo    improve this!
   * @param   array &$modifiers   If this variable is present, modifiers will be stored here
   * @return  array               Array of string expressions
   */
  public static function getSourceExpressions(&$modifiers = null){

    // pull only basic info with php 5.3.6+ to save some memory
    $trace = debug_backtrace(defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : null);
    
    while($callee = array_pop($trace)){

      // skip, if the called function doesn't match the shortcut function name
      if(strcasecmp($callee['function'], static::SHORTCUT_FUNC) > 0)
        continue;

      $codeContext = empty($callee['class']) ? $callee['function'] : $callee['function'];     
    
      $code = file($callee['file']);
      $code = array_slice($code, $callee['line'] - 1);
      $code = implode('', $code);

      $instIdx = 0;

      static::$lineInst[$callee['line']] = isset(static::$lineInst[$callee['line']]) ? static::$lineInst[$callee['line']] + 1 : 1;

      // if there are multiple calls to this function on the same line, make sure this is the one we're after;
      // note that calls that span across multiple lines will produce incorrect expression info :(
      while($instIdx < static::$lineInst[$callee['line']]){

        $fnPos = 0;
        
        while(isset($code[$fnPos])){
          $fnPos = strpos($code, $codeContext);
          if(isset($code[$fnPos - 1]) && ctype_alpha($code[$fnPos - 1])){
            $code = substr($code, $fnPos + strlen(static::SHORTCUT_FUNC));
            continue;
          }
          break;  
        }        

        // gather modifiers
        if($modifiers !== null){
          $modifiers = array();
          $i = $fnPos;

          while(isset($code[--$i]) && in_array($code[$i], array('@', '+', '-', '!', '~', '\\')))
            $modifiers[] = $code[$i];
        }

        $code = trim(substr($code, $fnPos + strlen($codeContext)));
        $code = substr($code, 1);

        $inSQuotes   = $inDQuotes = false;
        $expressions = array(0 => '');
        $index       = $sBracketLvl = $cBracketLvl = 0;

        for($i = 0, $len = strlen($code); $i < $len; $i++){

          switch($code[$i]){

            case '\'':
              if(!$inDQuotes)
                $inSQuotes = !$inSQuotes;

              $expressions[$index] .= $code[$i];              
              break;

            case '"':
              if(!$inSQuotes)
                $inDQuotes = !$inDQuotes;

              $expressions[$index] .= $code[$i];                            
              break;              

            case '{':
              if(!$inSQuotes && !$inDQuotes)
                $cBracketLvl++;

              $expressions[$index] .= $code[$i];
              break;            

            case '}':
              $expressions[$index] .= $code[$i];

              if(!$inSQuotes && !$inDQuotes)
                $cBracketLvl--;

              break;  

            case '(':
              if(!$inSQuotes && !$inDQuotes)
                $sBracketLvl++;

              $expressions[$index] .= $code[$i];
              break;
                
            case ')':
              if($sBracketLvl > 0)
                $expressions[$index] .= $code[$i];

              if(!$inSQuotes && !$inDQuotes){
                $sBracketLvl--;

                if($sBracketLvl < 0){
                  $code = substr($code, $i + 1);
                  break 2;
                }  

              }

              break;                

            case ',':
              if(!$inSQuotes && !$inDQuotes && ($sBracketLvl === 0) && ($cBracketLvl === 0)){
                $index++;
                $expressions[$index] = '';
                break;
              }

            default:
              $expressions[$index] .= $code[$i];
          }

        }

        $instIdx++;
      }

      // further entries are irrelevant
      break;    
    }

    return array_map('trim', $expressions);
  }



  /**
   * Parses a DocBlock comment into a data structure.
   *
   * A comment is expected to contain a title, description and tags
   * denoting parameter descriptions or return values.
   *
   * Code based on Sami - https://github.com/fabpot/Sami
   *
   * @since   1.0
   * @todo    rewrite and optimize for this class
   * @link    https://github.com/fabpot/Sami
   * @param   string $comment   Comment string
   * @param   string $key       Field to return (optional)
   * @return  array|string      Array containing all fields, or array/string with the contents of the requested field
   */
  public static function parseComment($comment, $key = false){
   
    $docBlockLine   = 1;
    $docBlockCursor = 0;

    // remove comment characters and normalize
    $comment = preg_replace(array('#^/\*\*\s*#', '#\s*\*/$#', '#^\s*\*#m'), '', trim($comment));
    $comment = "\n" . preg_replace('/(\r\n|\r)/', "\n", $comment);

    $position = 'desc';
    $doc = array();

    while($docBlockCursor < strlen($comment)){

      switch($position){

        case 'desc':
          if(preg_match('/(.*?)(\n[ \t]*' . static::COMMENT_TAG_REGEX . '|$)/As', $comment, $match, null, $docBlockCursor)){

            $docBlockLine += substr_count($match[1], "\n");
            $docBlockCursor += strlen($match[1]);

            $short = trim($match[1]);
            $long = '';

            // short desc ends at the first dot or when \n\n occurs
            if(preg_match('/(.*?)(\.\s|\n\n|$)/s', $short, $match)){
              $long = trim(substr($short, strlen($match[0])));
              $short = trim($match[0]);
            }
          }

          $position = 'tag';

          $doc['title'] = str_replace("\n", '', $short);
          $doc['desc'] = $long;
        break;

        case 'tag':

          if(preg_match('/\n\s*' . static::COMMENT_TAG_REGEX . '/As', $comment, $match, null, $docBlockCursor)){
         
            $docBlockLine += substr_count($match[0], "\n");
            $docBlockCursor += strlen($match[0]);            

            switch($type = $match[1]){
              case 'param':
                if(preg_match('/^([^\s]*)\s*(?:(?:\$|\&\$)([^\s]+))?\s*(.*)$/s', $match[2], $m))
                  $tag = array($type, array(static::parseCommentHint(trim($m[1])), trim($m[2]), static::normalizeString($m[3])));
              break;    

              case 'return':
              case 'var':
                if(preg_match('/^([^\s]+)\s*(.*)$/s', $match[2], $m))
                  $tag = array($type, array(static::parseCommentHint(trim($m[1])), static::normalizeString($m[2])));
              break;    

              case 'throws':
                if(preg_match('/^([^\s]+)\s*(.*)$/s', $match[2], $m))
                  $tag = array($type, array(trim($m[1]), static::normalizeString($m[2])));
              break;    

              default:
                $tag = array($type, static::normalizeString($match[2]));
            }

          // skip
          }else{
            $docBlockCursor = strlen($comment);
          }

          list($type, $values) = $tag;
          $doc['tags'][$type][] = $values;

        break;
      }

      if(preg_match('/\s*$/As', $comment, $match, null, $docBlockCursor))
        $docBlockCursor = strlen($comment);
      
    }
   
    if($key !== false)
      return isset($doc[$key]) ? $doc[$key] : '';

    return $doc;
  }



  /**
   * Extracts hints from an param tag expression
   *
   * @since   1.0
   * @param   string $hint  
   * @return  array
   */
  protected static function parseCommentHint($hint){
    $hints = array();
    foreach(explode('|', $hint) as $hint)
      $hints[] = (substr($hint, -2) === '[]') ? array(substr($hint, 0, -2), true) : array($hint, false);
    
    return $hints;
  }



  /**
   * Removes extra whitespaces from a string
   *  
   * @since   1.0
   * @param   string $str
   * @return  string
   */
  protected static function normalizeString($str){
    return preg_replace('/\s*\n\s*/', ' ', trim($str));
  }

}
