<?php


require __DIR__ . '/ref.php';

$obj = new Ref();

$array = array(
  'foo'   => 'bar',
  'abc'   => 5,
  'child' => array(4, 'five', 6),
);

$array['self'] = &$array;

$obj->foo = 'bar';
$obj->date = \DateTime::createFromFormat('U', time(), new \DateTimeZone('Europe/London'));
$obj->array = $array;

header('Content-type: text/html');

?>
<!DOCTYPE HTML>
<html>
  <body>

    <h3>Simple tests</h3>

    <?php ref(true, false, null, fopen('php://stdin', 'r'), 24, 4.20, "Hey look a string", array(), $array); ?>

    <h3>Complex structure</h3>    
  
    <?php ref($obj); ?>    

  </body>
<html>

