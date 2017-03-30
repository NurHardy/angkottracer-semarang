<?php
	require '../vendor/autoload.php';
	
	// Prepare app
	$app = new \Slim\Slim(array(
	    'templates.path' => realpath('../templates'),
	));
	
	// Create monolog logger and store logger in container as singleton 
	// (Singleton resources retrieve the same log resource definition each time)
	$app->container->singleton('log', function () {
	    $log = new \Monolog\Logger('slim-skeleton');
	    $log->pushHandler(new \Monolog\Handler\StreamHandler('../logs/app.log', \Monolog\Logger::DEBUG));
	    return $log;
	});
	
	// Prepare view
	$app->view(new \Slim\Views\Twig());
	//$app->view(new \Slim\Views\PhpRenderer("./templates"));
	$app->view->parserOptions = array(
	    'charset' => 'utf-8',
	    'cache' => realpath('../templates/cache'),
	    'auto_reload' => true,
	    'strict_variables' => false,
	    'autoescape' => true
	);
	$app->view->parserExtensions = array(new \Slim\Views\TwigExtension());
	
	// Define routes
	$app->get('/', function () use ($app) {
	    // Sample log message
	    //$app->log->info("Slim-Skeleton '/' route");
	    // Render index view
	    $app->render('index.html');
	});
	
	$app->get('/editor', function () use ($app) {
		//$app->render('editor.php', ['name' => 'Nur']);
		$app->render('editor.php', ['name' => 'Nur']);
	});
		
	// Define routes
	$app->get('/path', function () use ($app) {
		echo json_encode(array(
			'status' => 'ok',
			'data' => '1234567'
		));
	});
	
	// Run app
	$app->run();
