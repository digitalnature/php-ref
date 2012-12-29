<?php



/**
 * Shortcut to ref::describe()
 *
 * @version  1.0
 */
function r(){
  $output = ref::describe(func_get_args());

  if(headers_sent())
    return print $output;

  return printf('<!DOCTYPE HTML><html><body>%s', $output);
}



/**
 * REF is a nicer alternative to PHP's print_r() / var_dump().
 *
 * Current only HTML output is supported.
 * Plain text support is on the @todo list ;)
 *
 * @version  1.0
 * @author   digitalnature, http://digitalnature.eu
 */
class ref{

  const

    // shortcut function used to access the ::describe method below;
    // if its namespaced, the namespace must be present as well
    SHORTCUT_FUNC     = 'r',

    // regex used to parse tags in docblocks
    COMMENT_TAG_REGEX = '@([^ ]+)(?:\s+(.*?))?(?=(\n[ \t]*@|\s*$))';



  protected static

    // tracks style/jscript inclusion state
    $didAssets = false,    

    // used to determine the position of the current call,
    // if more ::describe() calls were made on the same line
    $lineInst  = array();



  protected

    // temporary element (marker) for arrays, used to track recursions
    $arrayMarker  = null,

    // tracks objects to detect recursion
    $objectHashes = array(),

    // expand/collapse state
    $expanded     = true;



  /**
   * Generates an URI to the PHP's documentation page for the requested context
   *
   * URI will point to the local PHP manual if installed and configured,
   * otherwise to php.net/manual (the english one)
   *
   * @since   1.0   
   * @param   string $scheme     Scheme to use; valid schemes are 'class', 'function', 'method', 'constant' (class only) and 'property'
   * @param   string $arg1       Class or function name
   * @param   string|null $arg2  Method name (required only for the 'method' scheme)
   * @return  string             URI string
   */
  protected static function getPhpManUri($scheme, $arg1, $arg2 = null){

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

    $args   = func_get_args();
    $scheme = array_shift($args);

    foreach($args as &$arg)
      $arg = str_replace('_', '-', ltrim(strtolower($arg), '\\_'));

    $schemes = array(
      'class'     => $docRefRoot . '/class.%s'    . $docRefExt,
      'function'  => $docRefRoot . '/function.%s' . $docRefExt,
      'method'    => $docRefRoot . '/%1$s.%2$s'   . $docRefExt,
      'constant'  => $docRefRoot . '/class.%1$s'  . $docRefExt . '#%1$s.constants.%2$s',
      'property'  => $docRefRoot . '/class.%1$s'  . $docRefExt . '#%1$s.props.%2$s',
    );

    return vsprintf($schemes[$scheme], $args);
  }



  /**
   * Builds a report with information about $subject
   *
   * @since   1.0   
   * @param   mixed $subject    Variable to query   
   * @return  string
   */
  protected function toHtml(&$subject){

     // expand first level
    $expState = $this->expanded ? 'exp' : 'col';

    $this->expanded = false;       

    $output = '';   

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
        return $this->entity('resource', sprintf('%s: %s', $subject, get_resource_type($subject)), gettype($subject));        

      // integer or double
      case is_int($subject) || is_float($subject):
        return $this->entity(gettype($subject), $subject, gettype($subject));

      // string
      case is_string($subject):
        return $this->entity('string', htmlspecialchars($subject, ENT_QUOTES), sprintf('%s (%d)', gettype($subject), strlen($subject)));        

      // arrays
      case is_array($subject):

        // empty array?
        if(empty($subject))      
          return $this->entity('array', 'Array()');

        // set a marker to detect recursion
        if(!$this->arrayMarker)
          $this->arrayMarker = uniqid('', true);

        // if our marker element is present in the array it means that we were here before
        if(isset($subject[$this->arrayMarker]))
          return $this->entity('array', 'Array(<b>Recursion</b>)');

        $subject[$this->arrayMarker] = true;             

        // note that we must substract the marker element
        $output .= $this->entity('array', sprintf('Array(<b>%d</b>', count($subject) - 1));
        $output .= sprintf('<a class="rToggle %s"></a><div>', $expState);

        foreach($subject as $key => &$value){

          // ignore our marker
          if($key === $this->arrayMarker)
            continue;

          $keyInfo = is_string($key) ? sprintf('String key (%d)', strlen($key)) : sprintf('Integer key', gettype($key));

          $output .= '<dl>';
          $output .= '<dt>' . $this->entity('key', htmlspecialchars($key, ENT_QUOTES), $keyInfo) . '</dt>';
          $output .= '<dt>' . $this->entity('div', '=&gt') . '<dt>';
          $output .= '<dd>' . $this->toHtml($value) . '</dd>';
          $output .= '</dl>';
        }

        // remove our temporary marker;
        // not really required, because the wrapper function doesn't take references, but we want to be nice :P
        unset($subject[$this->arrayMarker]);      

        return $output . '</div>' . $this->entity('array', ')');    
    }

    // if we reached this point, $subject must be an object
    $classes = $sections = $internalParents = array();
    $haveParent = new \ReflectionObject($subject);

    // get parent/ancestor classes
    while($haveParent !== false){
      $classes[] = $haveParent;

      if($haveParent->isInternal())
        $internalParents[] = $haveParent;

      $haveParent = $haveParent->getParentClass();
    }
    
    foreach($classes as &$class){
     
      $modifiers = '';

      if($class->isAbstract())
        $modifiers .= $this->entity('abstract', 'A', 'This class is abstract');

      if($class->isFinal())
        $modifiers .= $this->entity('final', 'F', 'This class is final and cannot be extended');

      // php 5.4+ only
      if((PHP_MINOR_VERSION > 3) && $class->isCloneable())
        $modifiers .= $this->entity('cloneable', 'C', 'Instances of this class can be cloned');

      if($class->isIterateable())
        $modifiers .= $this->entity('iterateable', 'X', 'Instances of this class are iterateable');            
     
      $className = $class->getName();

      if($class->isInternal())
        $className = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('class', $className), $className);

      $class = $modifiers . $this->entity('class', $className, $class);
    }  

    $objectName = implode(' :: ', array_reverse($classes));
    $objectHash = spl_object_hash($subject);

    // already been here?
    if(in_array($objectHash, $this->objectHashes))
      return $this->entity('object', $objectName . ' Object(<b>Recursion</b>)');

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
      return $this->entity('object', $objectName . ' Object()');

    $output .= $this->entity('object', $objectName . ' Object(');
    $output .= sprintf('<a class="rToggle %s"></a><div>', $expState);

    // display the interfaces this objects' class implements
    if($interfaces){

      $output .= '<h4>Interfaces:</h4>';

      $intfNames = array();

      foreach($interfaces as $name => $interface){

        $name = $interface->getName();

        if($interface->isInternal())
          $name = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('class', $name), $name);

        $intfNames[] = $this->entity('interface', $name, $interface);      
      }  

      $output .= sprintf('<dl><dt>%s</dt></dl>', implode(', ', $intfNames));
    }

    // class constants
    if($constants){

      $output .= '<h4>Constants:</h4>';

      foreach($constants as $name => $value){

        $output .= '<dl>';

        foreach($internalParents as $parent)
          if($parent->hasConstant($name))
            $name = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('constant', $parent->getName(), $name), $name);

        $output .= sprintf('<dt>%s</dt>', $this->entity('div', '::'));
        $output .= sprintf('<dt>%s</dt>', $this->entity('constant', $name));
        $output .= sprintf('<dt>%s</dt>', $this->entity('div', '='));
        $output .= sprintf('<dd>%s</dd>', $this->toHtml($value));        
        $output .= '</dl>';
      }  
      
    }

    // traits this objects' class uses
    if($traits){
      $output .= '<h4>Uses:</h4>';

      $traitNames = array();

      foreach($traits as $name => $trait)
        $traitNames[] = $this->entity('trait', $trait->getName(), $trait);

      $output .= sprintf('<dl><dt>%s</dt></dl>', implode(', ', $traitNames));      
    }

    // object/class properties
    if($props){
      $output .= '<h4>Properties:</h4>';

      foreach($props as $prop){
        $modifiers = '';

        if($prop->isProtected())        
          $prop->setAccessible(true);

        $value = $prop->getValue($subject);

        if($prop->isProtected())        
          $prop->setAccessible(false);        

        if($prop->isProtected())
          $modifiers .= $this->entity('protected', 'P', 'This property is protected');

        $name = htmlspecialchars($prop->name, ENT_QUOTES);

        foreach($internalParents as $parent)
          if($parent->hasProperty($name))
            $name = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('property', $parent->getName(), $name), $name);

        $output .= '<dl>';
        $output .= sprintf('<dt>%s</dt>', $this->entity('div', $prop->isStatic() ? '::' : '-&gt;'));
        $output .= sprintf('<dt>%s</dt>', $modifiers);
        $output .= sprintf('<dt>%s</dt>', $this->entity('property', $name, $prop));
        $output .= sprintf('<dt>%s</dt>', $this->entity('div', '='));
        $output .= sprintf('<dd>%s</dd>', $this->toHtml($value));
        $output .= '</dl>';        
      }

    }

    // class methods
    if($methods){

      $output .= '<h4>Methods:</h4>';

      foreach($methods as $method){

        $output .= '<dl>';        

        $paramStrings = array();
        $modifiers = '';

        $tags = static::parseComment($method->getDocComment(), 'tags');
        $tags = isset($tags['param']) ? $tags['param'] : array();

        // process arguments
        foreach($method->getParameters() as $parameter){

          $paramName = sprintf('$%s', $parameter->getName());

          if($parameter->isPassedByReference())
            $paramName = sprintf('&amp;%s', $paramName);

          $paramClass = $parameter->getClass();
          $paramHint = '';

          if($paramClass){
            $paramHint = $this->entity('hint', $paramClass->getName(), $paramClass);
            $paramHint = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('class', $paramClass->getName()), $paramHint);
          }  

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
            $paramName  = $this->entity('param', $paramName, $tip);
            $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            $paramName  = sprintf('%s%s<span class="rParamValue">%s</span>', $paramName, $this->entity('div', ' = '), $this->toHtml($paramValue));

            if($paramHint)
              $paramName = $paramHint . ' ' . $paramName;

            $paramName  = sprintf('<span class="rOptional">%s</span>', $paramName);

          }else{            
            $paramName = $this->entity('param', $paramName, $tip);

            if($paramHint)
              $paramName = $paramHint . ' ' . $paramName;            
          }

          $paramStrings[] = $paramName;
        }

        // is this method inherited?
        $inherited = $reflector->getShortName() !== $method->getDeclaringClass()->getShortName();
        $htmlClass = $inherited ? 'methodInherited' : 'method';     

        $modTip = $inherited ? sprintf('Inherited from ::%s', $method->getDeclaringClass()->getShortName()) : null;

        if($method->isAbstract())
          $modifiers .= $this->entity('abstract', 'A', 'This method is abstract');

        if($method->isFinal())
          $modifiers .= $this->entity('final', 'F', 'This method is final and cannot be overridden');

        if($method->isProtected())
          $modifiers .= $this->entity('protected', 'P', 'This method is protected');

        $output .= sprintf('<dt>%s</dt>', $this->entity('div', $method->isStatic() ? '::' : '-&gt;', $modTip));
        $output .= sprintf('<dt>%s</dt>', $modifiers);

        $name = $method->name;

        if($method->returnsReference())
          $name = '&' . $name;

        if($method->isInternal())
          $name = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('method', $method->getDeclaringClass()->getName(), $name), $name);

        $name = $this->entity($htmlClass, $name, $method);  

        $output .= sprintf('<dd>%s(%s)</dd>', $name, implode(', ', $paramStrings));
        $output .= '</dl>';        
      }  

    }

    return $output . '</div>' . $this->entity('object', ')');  
  }



  /**
   * Helper method, used to generate a SPAN tag with the provided class, text and tooltip content
   *
   * @since   1.0
   * @param   string $class           Entity class ('r' will be prepended to it)
   * @param   string $text            Entity text content
   * @param   string|Reflector $tip   Tooltip content, or Reflector object from which to generate this content
   * @return  string                  SPAN tag with the provided information
   */
  protected function entity($class, $text = null, $tip = null){

    if($text === null)
      $text = $class;

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
   * Scans for default classes and functions inside the provided expression,
   * and linkifies them when possible
   *
   * @since   1.0
   * @param   string $expression      Expression to linkify
   * @return  string                  HTML
   */
  public function transformExpression($expression){

    if(strpos($expression, '(') === false)
      return $expression;

    $fn = explode('(', $expression, 2);

    // try to find out if this is a function
    try{
      $reflector = new \ReflectionFunction($fn[0]);        

      if($reflector->isInternal()){
        $fn[0] = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('function', $fn[0]), $fn[0]);
        $fn[0] = $this->entity('srcFunction', $fn[0], $reflector);
      }
    
    }catch(\Exception $e){

      if(stripos($fn[0], 'new ') === 0){

        $cn = explode(' ' , $fn[0], 2);

        // linkify 'new keyword' (as constructor)
        try{          
          $reflector = new \ReflectionMethod($cn[1], '__construct');
          if($reflector->isInternal()){
            $cn[0] = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('method', $cn[1], '__construct'), $cn[0]);
            $cn[0] = $this->entity('srcClass', $cn[0], $reflector);
          }              
        }catch(\Exception $e){
          $reflector = null;
        }            

        // class name...
        try{          
          $reflector = new \ReflectionClass($cn[1]);
          if($reflector->isInternal()){
            $cn[1] = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('class', $cn[1]), $cn[1]);
            $cn[1] = $this->entity('srcClass', $cn[1], $reflector);
          }              
        }catch(\Exception $e){
          $reflector = null;
        }      

        $fn[0] = implode(' ', $cn);

      }else{

        if(strpos($expression, '::') === false)
          return $expression;

        $cn = explode('::', $fn[0], 2);

        // perhaps it's a static class method; try to linkify method first
        try{
          $reflector = new \ReflectionMethod($cn[0], $cn[1]);

          if($reflector->isInternal()){
            $cn[1] = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('method', $cn[0], $cn[1]), $cn[1]);
            $cn[1] = $this->entity('srcMethod', $cn[1], $reflector);
          }  

        }catch(\Exception $e){
          $reflector = null;
        }        

        // attempt to linkify the class name as well
        try{
          $reflector = new \ReflectionClass($cn[0]);

          if($reflector->isInternal()){
            $cn[0] = sprintf('<a href="%s" target="_blank">%s</a>', static::getPhpManUri('class', $cn[0]), $cn[0]);
            $cn[0] = $this->entity('srcClass', $cn[0], $reflector);
          }  

        }catch(\Exception $e){
          $reflector = null;
        }

        // apply changes
        $fn[0] = implode('::', $cn);
      }  
    }

    return implode('(', $fn);
  }


  protected static function getExpressions(){

    // find caller information;
    // pull only basic info with php 5.3.6+ to save some memory
    $trace = debug_backtrace(defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : null);
    
    while($callee = array_pop($trace)){
      if((!strcasecmp($callee['function'], static::SHORTCUT_FUNC)) || (isset($callee['class']) && !strcasecmp($callee['class'], __CLASS__))){

        $codeContext = empty($callee['class']) ? $callee['function'] : $callee['function'];     
      
        $code = file($callee['file']);
        $code = array_slice($code, $callee['line'] - 1);
        $code = implode('', $code);

        $instIdx = 0;
        static::$lineInst[$callee['line']] = isset(static::$lineInst[$callee['line']]) ? static::$lineInst[$callee['line']] + 1 : 1;

        // if there are multiple calls to this function on the same line, make sure this is the one we're after;
        // note that calls that span across multiple lines will produce incorrect expression info :(
        while($instIdx < static::$lineInst[$callee['line']]){
          $code = trim(substr($code, strpos($code, $codeContext) + strlen($codeContext)));
          $code = substr($code, 1);

          $inSQuotes = $inDQuotes = false;
          $expressions = array(0 => '');
          $index = 0;
          $sBracketLvl = 0;
          $cBracketLvl = 0;

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

        break;
      }  
    }

    return $expressions;
  }



  /**
   * Returns human-readable info about the given variable(s)
   *   
   * @since   1.0
   * @param   array $args    Variable(s) to query
   * @return  string         Information about each variable (currently only HTML output)
   */
  public static function describe(array $args){

    $output = array();

    $expressions = array_map('trim', static::getExpressions());

    foreach($expressions as &$expression){
      $instance = new static();
      $expression = $instance->transformExpression($expression);
    }

    $instance = null;
    unset($instance);

    // iterate trough the arguments and print info for each one
    foreach($args as $index => $subject){

      $startTime = microtime(true);  
      $startMem = memory_get_usage();

      $instance = new static();

      $html = $instance->toHtml($subject);
 
      // first call? include styles & js
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
        $html = preg_replace('/\s+/', ' ', trim(ob_get_clean())) . $html;
        static::$didAssets = true;
      }

      $instance = null;
      unset($instance);

      $endTime = microtime(true);

      $memUsage = abs(round((memory_get_usage() - $startMem) / 1024, 2));
      $cpuUsage = round(microtime(true) - $startTime, 4);

      $source = sprintf('<dfn class="refDfn">&gt; %s</dfn>', $expressions[$index]);

      $output[] = sprintf('<!-- ref #%d -->%s<div class="ref">%s</div><!-- /ref (took %ss, %sK) -->', $index + 1, $source, $html, $cpuUsage, $memUsage);
    }

    return implode("\n\n", $output);
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
