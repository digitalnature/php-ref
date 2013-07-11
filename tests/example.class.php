<?php

namespace Tests;



/**
 * An example interface
 *
 * Description of this interface goes here
 * bla blah...
 *
 * @since   1.0
 * @author  One Trick Pony
 */
interface Testable{}



/**
 * An example abstract class
 *
 * This class implements PHP's iterator interface
 * and can only be extended because it's abstract
 *
 * @since   1.0
 * @author  One Trick Pony
 */
abstract class AbstractTest extends \ArrayObject{

  const
  
    FOO  = 'this constant will be inherited';


  protected

    /**
     * A property that will be inherited by children
     *
     * @var bool
     */
    $parentProp = true;

  private

    /**
     * A private property
     */
    $data = array();


  /**
   * Example Iterator::current() method
   */
  public function current(){}

  /**
   * Example Iterator::next() method
   */  
  public function next(){}

  /**
   * Example Iterator::key() method
   */  
  public function key(){}

  /**
   * Example Iterator::valid() method
   */  
  public function valid(){}

  /**
   * Example Iterator::rewind() method
   */  
  public function rewind(){}


  /**
   * Example abstract function definition
   */ 
  abstract public function getList();

}



/**
 * An example concrete / child class
 *
 * This class extends the example abstract class and implements
 * the example interface 
 *
 * @since   1.0
 * @author  One Trick Pony
 */
class ClassTest extends AbstractTest implements Testable{

  const
  
    BAR  = 420;
    
  
  public

    /**
     * A public variable that everyone can access    
     *
     * @var int
     */
    $pubVarA     = 420,

    /**
     * Another one, to test recursivity
     *
     * @var self     
     */
    $pubVarB     = null,

    /**
     * DateTime object
     *
     * Testing property description
     *
     * @var DateTime
     */
    $currentDate = null,
    
    /**
     * Image resource created with GD
     *
     * No @var definition here
     */   
    $image       = null,
        
    /**
     * Curl resource
     *
     * @var resource     
     */   
    $curl        = null,

    /**
     * A json-encoded object
     *
     * @var string
     */   
    $jsonString  = null;



  private

    /**
     * A private property
     *
     * @var array
     */   
    $privProp    = 'asdf';




  protected

    /**
     * A protected variable that only this class and child classes can have access to
     *
     * @var array
     */
    $stuff = null;




  /**
   * Class constructor
   *
   * @since   1.0
   * @param   array $list               Value to set for "B"
   * @param   mixed &$refTest           A referenced variable
   * @param   Iterator $classHintTest   An iterateable instance
   *                                    Two line comment test
   */
  public function __construct(array $list, $stuff, &$refTest = null, \Iterator $classHintTest = null){
    parent::__construct($list);
    $this->stuff = $list;
    $this->pubVarB = $this;
    $this->currentDate = \DateTime::createFromFormat('U', time(), new \DateTimeZone('Europe/London'));

    if(extension_loaded('gd'))
      $this->image = imagecreate(1, 1); 

    if(extension_loaded('curl')){
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_URL, 'http://localhost');
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_exec($curl);

      $this->curl = $curl;
    }

    $this->jsonString = json_encode($this->currentDate);
  }



  /**
   * The destructor destroys the created image resource and the curl connection
   *
   * @since   1.0
   */
  public function __destruct(){

    if(isset($this->curl))
      curl_close($this->curl);

    if(isset($this->image))
      imagedestroy($this->image);
  }



  /**
   * A private method
   *
   * @since   1.0
   * @return  array    Normalized list
   */
  private function normalizeList(){}



  /**
   * A public getter method
   *
   * @since   1.0
   * @return  array    Indexed array containing list items
   */
  public function getList(ClassTest $x = null, $regexToIgnore = "#special\tabc\n#", $const = self::BAR){}



  /**
   * A protected setter method that returns a reference
   *
   * Accessible only from classes that extend this class
   * or from parent classes
   *
   * @since   1.0
   * @param   array $list   List as indexed array
   */
  final protected function &setList(array $list){}



  /**
   * A static method that creates a new instance
   *
   * @since   1.0
   * @param   array $list  Indexed array containing list items
   * @return  static       A new instance of this class
   */
  final public static function factory(array $list){}



  /**
   * A method that overrides parent::rewind()
   *
   * @since   1.0  
   */
  public function rewind(){

  }

}

