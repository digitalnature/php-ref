<?php



/**
 * Shortcut to ref::describe()
 *
 * @version  1.0
 */
function ref(){
  print call_user_func_array('ref::describe', func_get_args());
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
    WRAPPER_FUNC      = 'ref',

    // regex used to parse tags in docblocks
    COMMENT_TAG_REGEX = '@([^ ]+)(?:\s+(.*?))?(?=(\n[ \t]*@|\s*$))';



  protected

    // temporary element (marker) for arrays, used to track recursions
    $arrayMarker  = null,

    // tracks objects to detect recursion
    $objectHashes = array(),

    // expand/collapse state
    $expanded    = true;



  /**
   * Returns human-readable info about the given variable(s)
   *   
   * @since   1.0
   * @param   mixed $args    Variable(s) to query, multiple arguments can be passed
   * @return  string         Information about each variable (currently only HTML output)
   */
  public static function describe($args){

    static

      // status of the script/style inclusion
      $didAssets  = false;

    $args = func_get_args();
    $output = array();
  
    // iterate trough the arguments and print info for each one
    foreach($args as $index => $subject){

      $startTime = microtime(true);  

      $instance = new static();

      //continue;
      $result = $instance->toHtml($subject);

      if(!$didAssets){

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
        $output[] = preg_replace('/\s+/', ' ', trim(ob_get_clean()));
        $didAssets = true;
      }

      $output[] = sprintf('<!-- dump --><div class="ref">%s</div><!-- /dump (took %ss) -->', $result, round(microtime(true) - $startTime, 4));
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
                if(preg_match('/^([^\s]*)\s*(?:\$([^\s]+))?\s*(.*)$/s', $match[2], $m))
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



  /**
   * Helper method, used to generate a SPAN tag with the given info
   *
   * @since   1.0
   * @param   string $class           Entity class ('r' will be prepended to it)
   * @param   string $text            Entity text content
   * @param   string|Reflector $tip   Tooltip content, or Reflector object from which to generate this content
   * @return  string                  SPAN tag with the provided information
   */
  protected function htmlEntity($class, $text = null, $tip = null){

    if($text === null)
      $text = $class;

    if($tip instanceof \Reflector){

      // function/class/method is part of the core
      if($tip->isInternal()){
        $tip = sprintf('Internal - part of %s (%s)', $tip->getExtensionName(), $tip->getExtension()->getVersion());

      // user-defined; attempt to get doc comments
      }else{
       
        $tip = preg_split('/$\R?^/m', $tip->getDocComment());

        foreach($tip as &$line)
          $line = ltrim($line, '/* ');

        $tip = trim(implode("\n", $tip));
      }

    }

    $tip = ($tip !== null) ? sprintf('<code>%s</code>', $tip) : '';
    
    $class = ucfirst($class);

    if($tip !== '')
      $class .= ' rHasTip';

    return sprintf('<span class="r%s">%s%s</span>', $class, $text, $tip);
  }



  /**
   * Generates info report from the given variable
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
        return $this->htmlEntity('null');

      // boolean
      case is_bool($subject):
        $text = $subject ? 'true' : 'false';
        return $this->htmlEntity($text, $text, gettype($subject));        

      // resource
      case is_resource($subject):
        return $this->htmlEntity('resource', sprintf('%s: %s', $subject, get_resource_type($subject)), gettype($subject));        

      // integer or double
      case is_int($subject) || is_float($subject):
        return $this->htmlEntity(gettype($subject), $subject, gettype($subject));

      // string
      case is_string($subject):
        return $this->htmlEntity('string', htmlspecialchars($subject, ENT_QUOTES), sprintf('%s (%d)', gettype($subject), strlen($subject)));        

      // arrays
      case is_array($subject):

        // empty array?
        if(empty($subject))      
          return $this->htmlEntity('array', 'Array()');

        // set a marker to detect recursion
        if(!$this->arrayMarker)
          $this->arrayMarker = uniqid('', true);

        // if our marker element is present in the array it means that we were here before
        if(isset($subject[$this->arrayMarker]))
          return $this->htmlEntity('array', 'Array(<b>Recursion</b>)');

        // determine longest key
        $longest = max(array_map('strlen', array_keys($subject)));  
        $subject[$this->arrayMarker] = true;             

        // note that we must substract the marker element
        $output .= $this->htmlEntity('array', sprintf('Array(<b>%d</b>', count($subject) - 1));
        $output .= sprintf('<a class="rToggle %s"></a><div><dl>', $expState);

        foreach($subject as $key => &$value){

          // ignore our marker
          if($key === $this->arrayMarker)
            continue;

          $keyInfo = is_string($key) ? sprintf('KEY: %s (%d)', gettype($key), strlen($key)) : gettype($key);

          $output .= '<dt>' . $this->htmlEntity('key', str_pad(htmlspecialchars($key, ENT_QUOTES), $longest), $keyInfo) . '</dt>';
          $output .= '<dt>' . $this->htmlEntity('div', '=&gt') . '<dt>';
          $output .= '<dd>' . $this->toHtml($value) . '</dd>';
        }

        // remove our temporary marker;
        // not really required, because the wrapper function doesn't take references, but we want to be nice :P
        unset($subject[$this->arrayMarker]);      

        return $output . '</dl></div>' . $this->htmlEntity('array', ')');    
    }

    // if we reached this point, $subject must be an object
    $classes = $sections = array();
    $haveParent = new \ReflectionObject($subject);

    // get parent/ancestor classes
    while($haveParent !== false){
      $classes[] = $haveParent;
      $haveParent = $haveParent->getParentClass();
    }
    
    foreach($classes as &$class)
      $class = $this->htmlEntity('class', $class->getName(), $class);

    $objectName = implode('::', array_reverse($classes));
    $objectHash = spl_object_hash($subject);

    // already been here?
    if(in_array($objectHash, $this->objectHashes))
      return $this->htmlEntity('object', $objectName . ' Object(<b>Recursion</b>)');

    // track hash
    $this->objectHashes[] = $objectHash;

    // again, because reflectionObjects can't be cloned apparently :)
    $reflector = new \ReflectionObject($subject);

    $props      = $reflector->getProperties(\ReflectionMethod::IS_PUBLIC);    
    $methods    = $reflector->getMethods(\ReflectionMethod::IS_PUBLIC);
    $constants  = $reflector->getConstants();
    $interfaces = $reflector->getInterfaces();

    // no data to display?
    if(!$props && !$methods && !$constants && !$interfaces)
      return $this->htmlEntity('object', $objectName . ' Object()');

    $output .= $this->htmlEntity('object', $objectName . ' Object(');
    $output .= sprintf('<a class="rToggle %s"></a><div>', $expState);

    // display the interfaces this objects' class implements
    if($interfaces){

      $output .= '<h4>Implements:</h4>';

      $intfNames = array();

      foreach($interfaces as $name => $interface)
        $intfNames[] = $this->htmlEntity('interface', $interface->getName(), $interface);

      $output .= implode(', ', $intfNames);
    }

    // class constants
    if($constants){

      $output .= '<h4>Constants:</h4><dl>';

      $longest = max(array_map('strlen', array_keys($constants)));  

      foreach($constants as $name => $value){
        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('div', '::'));
        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('constant', str_pad(htmlspecialchars($name, ENT_QUOTES), $longest)));
        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('div', '='));
        $output .= sprintf('<dd>%s</dd>', $this->toHtml($value));        
      }  
      
      $output .= '</dl>';
    }

    // object/class properties
    if($props){
      $output .= '<h4>Properties:</h4><dl>';

      $propNames = array();

      foreach($props as $prop)
        $propNames[] = $prop->name;

      $longest = max(array_map('strlen', $propNames));

      foreach($props as $prop){
        $nameTip = '';
        $value = $prop->getValue($subject);        

        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('div', $prop->isStatic() ? '::' : '-&gt;'));
        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('property', str_pad($prop->name, $longest), $nameTip));
        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('div', '='));
        $output .= sprintf('<dd>%s</dd>', $this->toHtml($value));
      }

      $output .= '</dl>';
    }

    // class methods
    if($methods){

      $output .= '<h4>Methods:</h4><dl>';

      foreach($methods as $method){

        // for now, ignore PHP-reserved method names like __construct, __toString etc... (@todo)
        if(strpos($method->name, '__') === 0)
          continue;

        $paramStrings = array();
        $tags = static::parseComment($method->getDocComment(), 'tags');
        $tags = isset($tags['param']) ? $tags['param'] : array();

        // process arguments
        foreach($method->getParameters() as $parameter){

          $paramName = sprintf('$%s', $parameter->getName());

          if($parameter->isPassedByReference())
            $paramName = sprintf('&%s', $paramName);

          $tip = null;
          
          foreach($tags as $tag){
            list($types, $varName, $varDesc) = $tag;
            if($varName === $parameter->getName()){
              $tip = $varDesc;
              break;
            }  
          }  
        
          if($parameter->isOptional()){
            $paramName  = $this->htmlEntity('paramOpt', $paramName, $tip);
            $paramValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            $paramName  = $this->htmlEntity('paramValue', $paramName) . $this->htmlEntity('div', ' = ') . $this->toHtml($paramValue);

          }else{
            $paramName = $this->htmlEntity('param', $paramName, $tip);
          }

          $paramStrings[] = $paramName;
        }

        // is this method inherited?
        $inherited = $reflector->getShortName() !== $method->getDeclaringClass()->getShortName();

        $modTip = $inherited ? sprintf('Inherited from ::%s', $method->getDeclaringClass()->getShortName()) : null;

        $output .= sprintf('<dt>%s</dt>', $this->htmlEntity('div', $method->isStatic() ? '::' : '-&gt;'), $modTip);
        $output .= sprintf('<dd>%s(%s)</dd>', $this->htmlEntity('method', $method->name, $method), implode(', ', $paramStrings));
      }  
      
      $output .= '</dl>';
    }

    return $output . '</div>' . $this->htmlEntity('object', ')');  
  }

  /**
   * Text version of the method above -- todo
   *
   * @since   1.0   
   * @param   mixed $subject    Variable to query   
   * @return  string
   */
  protected function toText(&$subject){

  }  

}
