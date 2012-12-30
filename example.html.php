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

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_URL, 'http://localhost');
curl_setopt($curl, CURLOPT_HEADER, 0);
curl_exec($curl);

r(true, false,  null, $curl, 24, 4.20, "Hey look a string", array(), $array);
r(new \DateTimeZone('Europe/London'));
r($obj);

curl_close($curl);
