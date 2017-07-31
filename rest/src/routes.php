<?php

// System contants
//define('GOOGLEMAP_APIKEY', "AIzaSyCB_Tzs_EZ1exoXELhuq_sOlkqhrifjezw");
define('GOOGLE_APIKEY', 'AIzaSyB2LvXICy-Je6QQFgeIi32FnbA8r-dnqU4');
define('APPVER', 'v0.5.4.1434749641');
define('SRCPATH', __DIR__);

// Middlewares
$authMiddleWare = function ($request, $response, $next) {
	require_once SRCPATH . '/models/UserModel.php';
	$userModel = new UserModel($this->db);
	
	$userData = null;
	$authData = "";
	if ($request->hasHeader('Authorization')) {
		//-- Validate
		$authData = $request->getHeaderLine('Authorization');
		$authComponent = explode(' ', $authData);
		
		if ($authComponent[0] == 'Basic') {
			$authKey = base64_decode($authComponent[1]);
			$userData = $userModel->get_user_by_token(explode(':', $authKey)[1]);
		}
	}
	
	$outputResponse = null;
	if (!$userData) {
		$errorMsg = generate_message("invalid-session", "Invalid session or authentication failed. Please relogin.");
		$outputResponse = $response->withJson($errorMsg, HTTPSTATUS_UNAUTHORIZED);
	} else {
		$request = $request->withAttribute('userdata', $userData);
		$outputResponse = $next($request, $response);
	}
	return $outputResponse;
};

// Routes

$app->get('/', function ($request, $response, $args) {
	// Render index view
	$args['pageTitle'] = "Angkot Semarang";
	return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/editor', function ($request, $response, $args) {
	//-- Validate with token...
	$userToken = $request->getQueryParam('token', null);
	
	require_once SRCPATH . '/models/UserModel.php';
	$userModel = new UserModel($this->db);
	
	require_once SRCPATH . '/models/NodeModel.php';
	$nodeModel = new NodeModel($this->db);
	
	$args['pageTitle'] = "Graph and Route Editor";
	$args['nodeTypeList'] = $nodeModel->get_node_types();
	$args['nodeFormContent'] = $this->renderer->fetch('forms/form_node.php', $args);
	
	if ($userToken != null) {
		if ($currentUserData = $userModel->get_user_by_token($userToken)) {
			//-- Show editor page, init logics here...
			$args['currentSessionData'] = [
				'activeToken' => $userToken,
				'userId' => $currentUserData['id_user'],
				'userEmail' => $currentUserData['email'],
				'userNickName' => $currentUserData['nickname']
			];
			
		}
	}
	
	if (!$currentUserData) {
		$outputResponse = $response->withStatus(HTTPSTATUS_UNAUTHORIZED);
	} else {
		$outputResponse = $response->withStatus(HTTPSTATUS_OK);
	}
	
	// Render index view
	return $this->renderer->render($outputResponse, 'editor.phtml', $args);
});

$app->group('/auth', function() {
	require SRCPATH . '/controllers/AuthControl.php';

	$this->get('/login', AuthControl::class.':login_form');
	$this->post('/login', AuthControl::class.':login_form');
	
	$this->get('/logout', AuthControl::class.':logout');
	
	$this->post('/token', AuthControl::class.':login_form');
});
	
$app->group('/node', function() {
	require SRCPATH . '/controllers/NodeControl.php';
	
	$this->get('/all', NodeControl::class.':get_nodes');
	$this->post('/add', NodeControl::class.':add_node');
	
	$this->get('/{id}', NodeControl::class.':get_node_by_id');
	$this->delete('/{id}', NodeControl::class.':delete_node_by_id');
	$this->post('/{id}', NodeControl::class.':edit_node');
})->add($authMiddleWare);

$app->group('/edge', function() {
	require SRCPATH . '/controllers/EdgeControl.php';

	$this->post('/add', EdgeControl::class.':add_edge');
	$this->post('/break', EdgeControl::class.':save_edge_and_break');
	$this->post('/interpolate', EdgeControl::class.':interpolate_edge');
	$this->post('/dir', EdgeControl::class.':get_direction');
	
	$this->get('/{id}', EdgeControl::class.':get_edge_by_id');
	$this->delete('/{id}', EdgeControl::class.':delete_edge_by_id');
	$this->post('/{id}', EdgeControl::class.':save_edge');
})->add($authMiddleWare);

$app->group('/route', function() {
	require SRCPATH . '/controllers/RouteControl.php';

	$this->get('/{id}', RouteControl::class.':get_route_by_id');
	$this->post('/{id}', RouteControl::class.':save_route');
	$this->delete('/{id}', RouteControl::class.':delete_route_by_id');
})->add($authMiddleWare);
	
$app->group('/algorithm', function() {
	require SRCPATH . '/controllers/AlgorithmControl.php';
	
	$this->get('/debug', AlgorithmControl::class.':search_route');
	$this->get('/{from}/{dest}', AlgorithmControl::class.':astar');
});
$app->group('/modal', function() {
	require SRCPATH . '/controllers/DialogControl.php';
	$this->post('/get_modal', DialogControl::class.':get_modal');
})->add($authMiddleWare);

$app->group('/app', function() {
	require SRCPATH . '/controllers/ApplicationControl.php';
	$this->get('/init', ApplicationControl::class.':get_init_data');
	$this->get('/cron', ApplicationControl::class.':refresh_distances');
	
})->add($authMiddleWare);

require_once SRCPATH . '/controllers/ApplicationControl.php';
$app->get('/hello', ApplicationControl::class.':hello');

$app->get('/test', function ($request, $response, $args) {
	
    // Sample log message
    //$this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    //return $this->renderer->render($response, 'index.phtml', $args);
});
