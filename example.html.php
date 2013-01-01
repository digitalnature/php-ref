<?php

require __DIR__ . '/ref.php';
require __DIR__ . '/example.class.php';

$obj = new \Tests\ClassTest(array('foo', 'bar', 'abc def'));

$array = array(
  'foo'         => 'bar',
  'abc def xyz' => 5,
  'child'       => array(4, 'five', 6),
);

$array['self'] = &$array;

r(true, false, "Hey look a 강남스타일 string", '2010-09-17 14:00:00', null, 24, 4.20);
r(fopen('php://stdin', 'r'), function($x, $d){}); 
r(array(), $array, serialize(array('A', 'serialized', 'string')));
r(new \DateTimeZone('Europe/London'));
r($obj);

