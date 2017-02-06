<?php
/*
 * controller/ajax/modal.php
 * ---------------------------------------------
 * AJAX handler for modal request
 * by Nur Hardyanto
 * 
 */

	$modalName = $_POST['name'];
	
	if ($modalName == 'node.add') {
		$data['nodeData'] = json_encode($_POST['data']);
		
		htmlview('modal/add_node.php', $data);
		
	} else if ($modalName == 'edge.add') {
		$data['edgeData'] = json_encode($_POST['data']);
		
		// TODO: Validation!
		if (!isset($_POST['data']['id_node_1']) || !isset($_POST['data']['id_node_2'])) {
			echo "Incomplete parameter";
			return;
		}
		require_once APP_PATH.'/model/node.php';
		$data['dataNode1'] = get_node_by_id(intval($_POST['data']['id_node_1']));
		$data['dataNode2'] = get_node_by_id(intval($_POST['data']['id_node_2']));
		htmlview('modal/add_edge.php', $data);
		
	} else if ($modalName == 'route.load') {
		require_once APP_PATH.'/model/route.php';
		$data['routeList'] = get_routes();
		htmlview('modal/load_route.php', $data);
	} else {
		echo '<div class="alert alert-danger">Undefined parameter! <a href="#" onclick="return hide_modal();">OK</a></div>';
	}