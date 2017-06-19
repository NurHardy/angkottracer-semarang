<?php

// System contants
//define('GOOGLEMAP_APIKEY', "AIzaSyCB_Tzs_EZ1exoXELhuq_sOlkqhrifjezw");
define('GOOGLE_APIKEY', 'AIzaSyB2LvXICy-Je6QQFgeIi32FnbA8r-dnqU4');
define('APPVER', 'v0.5.4.1434749586');
define('SRCPATH', __DIR__);

// Routes

$app->get('/', function ($request, $response, $args) {
	// Render index view
	return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/editor', function ($request, $response, $args) {
	require_once SRCPATH . '/models/NodeModel.php';
	$nodeModel = new NodeModel($this->db);
	
	$args['nodeTypeList'] = $nodeModel->get_node_types();
	$args['nodeFormContent'] = $this->renderer->fetch('forms/form_node.php', $args);
	
	// Render index view
	return $this->renderer->render($response, 'editor.phtml', $args);
});

$app->group('/node', function() {
	require SRCPATH . '/controllers/NodeControl.php';
	
	$this->get('/all', NodeControl::class.':get_nodes');
	$this->post('/add', NodeControl::class.':add_node');
	
	$this->get('/{id}', NodeControl::class.':get_node_by_id');
	$this->delete('/{id}', NodeControl::class.':delete_node_by_id');
	$this->post('/{id}', NodeControl::class.':edit_node');
});

$app->group('/edge', function() {
	require SRCPATH . '/controllers/EdgeControl.php';

	$this->post('/add', EdgeControl::class.':add_edge');
	$this->post('/break', EdgeControl::class.':save_edge_and_break');
	$this->post('/interpolate', EdgeControl::class.':interpolate_edge');
	$this->post('/dir', EdgeControl::class.':get_direction');
	
	$this->get('/{id}', EdgeControl::class.':get_edge_by_id');
	$this->delete('/{id}', EdgeControl::class.':delete_edge_by_id');
	$this->post('/{id}', EdgeControl::class.':save_edge');
});

$app->group('/route', function() {
	require SRCPATH . '/controllers/RouteControl.php';

	$this->get('/{id}', RouteControl::class.':get_route_by_id');
	$this->post('/{id}', RouteControl::class.':save_route');
	$this->delete('/{id}', RouteControl::class.':delete_route_by_id');
});
	
$app->group('/algorithm', function() {
	require SRCPATH . '/controllers/AlgorithmControl.php';
	
	$this->get('/debug', AlgorithmControl::class.':search_route');
	$this->get('/{from}/{dest}', AlgorithmControl::class.':astar');
});
$app->group('/modal', function() {
	require SRCPATH . '/controllers/DialogControl.php';
	$this->post('/get_modal', DialogControl::class.':get_modal');
});

$app->group('/app', function() {
	require SRCPATH . '/controllers/ApplicationControl.php';
	$this->get('/init', ApplicationControl::class.':get_init_data');
	$this->get('/cron', ApplicationControl::class.':refresh_distances');
});
	
$app->get('/test/[{name}]', function ($request, $response, $args) {
    // Sample log message
    //$this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
