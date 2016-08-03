<?php

  if(isset($_GET['mode'])){

    $htmlMode = ($_GET['mode'] !== 'text');

    require dirname(__DIR__) . '/ref.php';
    require __DIR__ . '/example.class.php';

    ref::config('showPrivateMembers', true);
    ref::config('showIteratorContents', true);
    ref::config('showUrls', true);
	  ref::config('showBacktrace', false);



    /**
     * Test class
     */ 
    final class Today extends \Tests\ClassTest{

    }

    /**
     * Test function
     *
     * @param   $test  Test argument
     * @return  void   Nothing
     */ 
    function today($test){

    }

    $closedCurlRes = curl_init();
    curl_close($closedCurlRes);

    $array = array(
      'hèllo world'                       => '(͡°͜ʖ͡°)',      
      'empty string'                      => '',
      'multiline string'                  => "first line and some padding   \nsecond line",
      'infinity'                          => INF,      
      'regular expression (pcre)'         => '/^([0-9a-zA-Z]([-\.\w]*[0-9a-zA-Z])*@([0-9a-zA-Z][-\w]*[0-9a-zA-Z]\.)+[a-zA-Z]{2,9})$/',
      'multi'                             => array(1, 2, 3, array(4, 5, 6), 'FUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUU'),  
      'matching class'                    => 'DateTime',
      'matching file'                     => 'file.txt',
      'incomplete object'                 => unserialize('O:3:"Foo":1:{s:3:"bar";i:5;}'),
      'empty object'                      => new \StdClass(),
      'closed CURL resource'              => $closedCurlRes,
      'matching date/file/function/class' => 'today',      
      'url'                               => 'http://google.com',
    );

    $array['reference to self'] = &$array;

    $obj = new \Tests\ClassTest(array('foo', 'bar'), $array);    

    if($htmlMode){
      r(true, false, 'I can haz a 강남스타일 string', '1492-10-14 04:20:00 America/Nassau', null, 4.20);      
      r(array(), $array, serialize(array('A', 'serialized', 'string')));
      r(fopen('php://stdin', 'r'), function($x, $d){}); 
      r(new \DateTimeZone('Pacific/Honolulu'));
      r($obj, new ref()); 

    }else{

      rt(true, false, 'I can haz a 강남스타일 string', '1492-10-14 04:20:00 America/Nassau', null, 17, 4.20);
      rt(array(), $array, serialize(array('A', 'serialized', 'string')));     
      rt(fopen('php://stdin', 'r'), function($x, $d){}); 
      rt(new \DateTimeZone('Pacific/Honolulu'));
      rt($obj, new ref());

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