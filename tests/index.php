<?php

  if(isset($_GET['mode'])){

  	$htmlMode = ($_GET['mode'] !== 'text');

		require dirname(__DIR__) . '/ref.php';
		require __DIR__ . '/example.class.php';

		/**
		 * Test class
		 */ 
		class Today{

		}

		/**
		 * Test function
		 */ 
		function today(){

		}

		$array = array(
		  'foo'                                     => 'bar',
		  '要'                                      => 'UTF-8 key test',
		  'bar'                                     => 5,  
		  'multi'                                   => array(4, 'five', 6),  
		  'matching class'                          => 'DateTime',
		  'matching file'                           => 'file.txt',
		  'matching date, file, function and class' => 'today',
		);

		$array['reference to self'] = &$array;

		$obj = new \Tests\ClassTest($array);

		if($htmlMode){
	  
			r(true, false, "Hey look a 강남스타일 string", '2010-09-17 14:00:00', null, 24, 4.20);			
			r(array(), $array, serialize(array('A', 'serialized', 'string')));
			r(fopen('http://google.com', 'r'), function($x, $d){}); 
			r(new \DateTimeZone('Europe/London'));
			r($obj);	

    }else{

			rt(true, false, "Hey look a 강남스타일 string", '2010-09-17 14:00:00', null, 24, 4.20);
			rt(array(), $array, serialize(array('A', 'serialized', 'string')));			
			rt(fopen('php://stdin', 'r'), function($x, $d){}); 
			rt(new \DateTimeZone('Europe/London'));
			rt($obj);

    }		

    exit(0);
  }   

?>

<!DOCTYPE HTML>
<html>
  <head>
  	<title>REF by digitalnature</title>
	  <style>

	    body{
	      font: 40px "Helvetica Neue", Helvetica, Arial, sans-serif;    	
	    	text-align: center;
	    	color: #ccc;
	    }

	    a{
	    	color: #2183cf;
	    	text-decoration: none;
	    }

	    a:hover{
	    	background: #2183cf;
	    	color: #fff;
	    }

	    h1{
	    	font-size: 400%;
	    }

	    h3{
	    	border-top: 1px solid #ccc;
	    	padding-top: 20px;
	    }

	  </style>
  </head>
  <body>

  	<h1><a href="https://github.com/digitalnature/php-ref">REF</a></h1>
    <h2><a href="index.php?mode=html">HTML output</a> ~ <a href="index.php?mode=text">TEXT output</a></h2>
    <h3> created by <a href="http://digitalnature.eu/">digitalnature</digitalnature></h3>
  </body>
</html>  