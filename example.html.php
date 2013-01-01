<?php

require __DIR__ . '/ref.php';
require __DIR__ . '/example.class.php';

$obj = new Tests\ClassTest(array('foo', 'bar', 'abc def'));

$array = array(
  'foo'         => 'bar',
  'abc def xyz' => 5,
  'child'       => array(4, 'five', 6),
);

$array['self'] = &$array;

r(true, false, '2010-09-17 14:00:00', null, function($x, $d){});
r(fopen('php://stdin', 'r'), 24, 4.20, "Hey look a string"); 
r(serialize(array('A', 'serialized', 'string')), array(), $array);
r(new \DateTimeZone('Europe/London'));
r($obj);


