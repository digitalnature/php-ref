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
abstract class AbstractTest implements \Iterator{

	private

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
final class ClassTest extends AbstractTest implements Testable{
  
	public

		/**
		 * A public variable that everyone can access		 
		 */
	  $pubA = 420,

		/**
		 * Another one, to test recursivity
		 */
	  $pubB = null,

		/**
		 * DateTime object
		 */
	  $date = null;	  



	protected

    /**
		 * A protected variable that only this class and child classes can have access to
		 */
	  $list = null;




	/**
	 * Class constructor
	 *
	 * @since   1.0
	 * @param   mixed $list   Value to set for "B"
	 */
	public function __construct(array $list){
		$this->list = $list;
		$this->pubB = $this;
		$this->date = \DateTime::createFromFormat('U', time(), new \DateTimeZone('Europe/London'));
	}



	/**
	 * A public getter method
	 *
	 * @since   1.0
	 * @return  array    Indexed array containing list items
	 */
	public function getList(){
		return $this->list;
	}



	/**
	 * A protected setter method
	 *
	 * Only child classes can call it
	 *
	 * @since   1.0
	 * @param   array $list   List as indexed array
	 */
	final protected function setList(array $list){
		$this->list = $list;
	}



	/**
	 * A static method that creates a new instance
	 *
	 * @since   1.0
	 * @param   array $list  Indexed array containing list items
	 * @return  static       A new instance of this class
	 */
  final public static function factory(array $list){
  	return new static($list);
  }

}

