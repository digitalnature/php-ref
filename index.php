<?php


require __DIR__ . '/ref.php';
require __DIR__ . '/class.test.php';

$obj = new Tests\ClassTest(array('foo', 'bar'));

$array = array(
  'foo'   => 'bar',
  'abc'   => 5,
  'child' => array(4, 'five', 6),
);

$array['self'] = &$array;

header('Content-type: text/html');

?>
<!DOCTYPE HTML>
<html>
  <body>

    <h3>Simple tests</h3>

    <?php r(true, false,  null, fopen('php://stdin', 'r'), 24, 4.20, "Hey look a string", array(), $array); ?>

    <?php r(\DateTime::createFromFormat('U', time(), new \DateTimeZone('Europe/London'))); ?>

    <h3>Complex structure</h3>    
  
    <?php r($obj); ?>    

  </body>
<html>

