<?php

require_once '../vendor/autoload.php';

ini_set('display_errors', 'on');

$faker = Faker\Factory::create('fr_FR');

$fieldTypes = array();
$blacklist = array('toUpper', 'toLower', 
	'randomDigit', 'randomNumber', 'randomLetter', 'randomElement', 
	'randomDigitNotNull', 'numberBetween', 
	'numerify', 'lexify', 'bothify');
foreach ($faker->getProviders() as $provider) {
	$reflectionClass = new ReflectionClass($provider);
	$publicMethods = $reflectionClass->getMethods(ReflectionMethod :: IS_PUBLIC);
	$providerName = substr(get_class($provider), strrpos(get_class($provider),  '\\') + 1);
	foreach ($publicMethods as $publicMethod) {
		if (!$publicMethod->isConstructor() && !in_array($publicMethod->getName(), $blacklist)) {
			// $params = $publicMethod->getParameters();
			$fieldTypes[$providerName][] = $publicMethod->getName();
		}
	}
}

define('APP_ENV', getenv('ENV') ? getenv('ENV') : 'dev');
define('APP_PATH', APP_ENV == 'dev' ? '/fakerui' : '');

$lines = 5;

$twigView = new \Slim\Extras\Views\Twig();

$app = new \Slim\Slim(array(
    'view' => $twigView
));

function writeCSV($fields, $response, $faker, $size = 15, $titles = false) {
	
    $stream = fopen('php://temp/maxmemory:'. (12*1024*1024), 'r+');
    $head = false;
	for ($i = 0; $i < $size; $i++) {
		
		if ($titles && !$head) {
			$row = array();
    		foreach ($fields as $field) {
    			$row[] = $field->title;
    		}
        	fputcsv($stream, $row, ';', '"');
        	$head = true;
		}
		
    	$row = array();
    	foreach ($fields as $field) {
    		$row[] = $faker->{$field->type};
    	}
        fputcsv($stream, $row, ';', '"');
    }
    rewind($stream);
    $output = stream_get_contents($stream);
   	fclose($stream);
   	
   	$response->write($output);
	
}

function writeSQL($fields, $response, $faker, $size = 15) {
	
	// We write the file in a stream
    $stream = fopen('php://temp/maxmemory:'. (12*1024*1024), 'r+');
	for ($i = 0; $i < $size; $i++) {
    	$row = array();
    	foreach ($fields as $field) {
    		$row[] = $faker->{$field->type};
    	}
        fwrite($stream, 'INSERT INTO %table% VALUES("' .implode('", "', $row). '");' . "\n");
    }
    rewind($stream);
    $output = stream_get_contents($stream);
   	fclose($stream);
   	
   	$response->write($output);
	
}

$app->get('/', function() use($app, $faker, $fieldTypes) {
	
    $vars = array(
		'faker' 		=> $faker, 
    	'fieldTypes'	=> $fieldTypes, 
    	'app_path'		=> APP_PATH
	);
	
	$app->render('layout.html', $vars);
	
});

$app->post('/download', function() use($app, $faker) {
	
    $request 	= $app->request();
    $response 	= $app->response();
    
    $fields = $request->post('fields');
    $format = $request->post('format');
    $size 	= $request->post('size') ? $request->post('size') : 20;
	$titles = $request->post('titles') ? $request->post('titles') : false;
    
    $config = array();
    foreach ($fields as $field) {
    	$config[] = new ArrayObject($field, ArrayObject :: ARRAY_AS_PROPS);
    }
    
    $response['Content-Description'] 		= 'File Transfer';
    $response['Content-Type'] 				= 'application/octet-stream';
    $response['Content-Disposition'] 		= 'attachment; filename=data.' . $format;
    $response['Content-Transfer-Encoding'] 	= 'binary';
    $response['Expires'] 					= '0';
    $response['Cache-Control'] 				= 'must-revalidate, post-check=0, pre-check=0';
    $response['Pragma'] 					= 'public';
    
	switch ($format) {
		case 'sql':
			writeSQL($config, $response, $faker, $size, $titles);
			break;
		default:
		case 'csv':
			writeCSV($config, $response, $faker, $size, $titles);
			break;
	}
    
});

$app->post('/data.:format', function($format) use($app, $faker) {
	
	$request 	= $app->request();
	$response	= $app->response();
	
	$size 	= $request->get('size') ? $request->get('size') : 20;
	$titles = $request->get('titles') ? $request->get('titles') : false;
	$config = json_decode($request->getBody());
	
	switch ($format) {
		case 'sql':
			writeSQL($config, $response, $faker, $size, $titles);
			break;
		default:
		case 'csv':
			writeCSV($config, $response, $faker, $size, $titles);
			break;
	}
	
});

$app->run();

?>