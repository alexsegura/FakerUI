<?php

require_once '../vendor/autoload.php';

ini_set('display_errors', 'on');

$faker = Faker\Factory::create('fr_FR');

$fieldTypes = array();
$blacklist = array('toUpper', 'toLower');
foreach ($faker->getProviders() as $provider) {
	$reflectionClass = new ReflectionClass($provider);
	$publicMethods = $reflectionClass->getMethods(ReflectionMethod :: IS_PUBLIC);
	$providerName = substr(get_class($provider), strrpos(get_class($provider),  '\\') + 1);
	foreach ($publicMethods as $publicMethod) {
		if (!$publicMethod->isConstructor() && !in_array($publicMethod->getName(), $blacklist)) {
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

$app->run();

?>