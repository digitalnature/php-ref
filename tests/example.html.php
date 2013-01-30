<?php


r(true, false, "Hey look a 강남스타일 string", '2010-09-17 14:00:00', null, 24, 4.20);
r(array(), $array, serialize(array('A', 'serialized', 'string')));
r(fopen('php://stdin', 'r'), function($x, $d){}); 
r(new \DateTimeZone('Europe/London'));
r($obj);
