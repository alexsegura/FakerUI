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

$lines = 5;

$twigView = new \Slim\Extras\Views\Twig();

$app = new \Slim\Slim(array(
    'view' => $twigView
));

$app->get('/', function() use($app, $faker, $fieldTypes) {
	
    $vars = array(
		'faker' 		=> $faker, 
    	'fieldTypes'	=> $fieldTypes
	);
	
	$app->render('layout.html', $vars);
	
});

$app->get('/preview', function() use($app, $faker) {
	
    $request = $app->request();
    
    $fields = $request->get('fields');
    
    $response = array();
    
    for ($i = 0; $i < 20; $i++) {
    	$row = array();
    	foreach ($fields as $field) {
    		$row[] = $faker->{$field};
    	}
    	$response[] = $row;
    }
    
    echo json_encode($response);
	
});

$app->post('/download', function() use($app, $faker) {
	
    $request = $app->request();
    
    $fields = $request->params('fields');
    
    $response = $app->response();
    
    $response['Content-Description'] 		= 'File Transfer';
    $response['Content-Type'] 				= 'application/octet-stream';
    $response['Content-Disposition'] 		= 'attachment; filename=foo.csv';
    $response['Content-Transfer-Encoding'] 	= 'binary';
    $response['Expires'] 					= '0';
    $response['Cache-Control'] 				= 'must-revalidate, post-check=0, pre-check=0';
    $response['Pragma'] 					= 'public';
    // $response['Content-Length'] = filesize($file);
    
    // We write the file in a stream
    $stream = fopen('php://temp/maxmemory:'. (12*1024*1024), 'r+');
	for ($i = 0; $i < 20; $i++) {
    	$row = array();
    	foreach ($fields as $field) {
    		$row[] = $faker->{$field};
    	}
        fputcsv($stream, $row, ';', '"');
    }
    rewind($stream);
    $output = stream_get_contents($stream);
   	fclose($stream);
   	
   	$response->write($output);
	
});

$app->run();

?>