<?php

require_once '../vendor/autoload.php';

define('APP_ENV', getenv('ENV') ? getenv('ENV') : 'dev');
define('APP_PATH', APP_ENV == 'dev' ? '/fakerui' : '');

ini_set('display_errors', APP_ENV == 'dev' ? 'on' : 'off');

///////////////////////////////////////////////////////////////////////////////////

function getLocale($app) {
	$accept = Locale :: acceptFromHttp($app->request()->headers('Accept-Language'));
	$cookie = $app->getCookie('fakerui.locale');
	$locale = isset($cookie) ? $cookie : $accept;
	return Locale :: getPrimaryLanguage($locale) . '_' . Locale :: getRegion($locale); 
}

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

///////////////////////////////////////////////////////////////////////////////////

$app = new \Slim\Slim(array(
    'view' => new \Slim\Extras\Views\Twig()
));

$locale = getLocale($app);

$faker = Faker\Factory :: create($locale);

$documentor = new Faker\Documentor($faker);
$fieldTypes = array();

foreach ($documentor->getFormatters() as $class => $methods) {
	$providerName = substr($class, strrpos($class, '\\') + 1);
	foreach ($methods as $method => $example) {
		if (false !== strpos($method, '(')) {
			$method = substr($method, 0, strpos($method, '('));
		}
		$fieldTypes[$providerName][] = $method;
	}
}

///////////////////////////////////////////////////////////////////////////////////

$app->get('/', function() use($app, $faker, $fieldTypes) {
	
	$available = array('en_US', 'fr_FR', 'de_DE', 'it_IT', 'ru_RU', 'es_AR');
	$locales = array();
	foreach ($available as $locale) {
		$locales[$locale] = Locale::getDisplayLanguage($locale, 'en');
	}
	
    $vars = array(
		'faker' 			=> $faker, 
    	'fieldTypes'		=> $fieldTypes, 
    	'app_path'			=> APP_PATH, 
    	'displayLanguage'	=> Locale::getDisplayLanguage(getLocale($app)), 
    	'locales'			=> $locales
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