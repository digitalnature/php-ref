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

r(true, false,  null, fopen('php://stdin', 'r'), 24, 4.20, "Hey look a string", array(), $array);
r(new \DateTimeZone('Europe/London'));
r($obj);
