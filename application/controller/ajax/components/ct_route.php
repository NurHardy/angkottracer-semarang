<?php

function _data_ajax_route($verbMethod) {
	global $mysqli;
	
	// Load model node.php
	require_once APP_PATH.'/model/route.php';
	if ($verbMethod == 'get') {
		$idRoute = intval($_POST['id_route']);
		
		$routeData = get_route_by_id($idRoute);
		if ($routeData) {
			return (array(
				'status' => 'ok',
				'data' => array(
					'id_route' => $routeData['id_route'],
					'route_name' => $routeData['route_name'],
					'route_code' => $routeData['route_code'],
					'node_seq' => [1,2,3,4,5],
					'edge_seq' => [1,2,3,4,5]
				)
			));
		} else {
			return generate_error("Route data not found!");
		}
		
	} else if ($verbMethod == 'save') {
		return generate_message('ok', print_r($_POST, true));
		
	} else {
		return generate_error("Unrecognized verb: ".$verbMethod);
	}
}