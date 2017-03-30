<?php

function _data_ajax_route($verbMethod) {
	global $mysqli;
	
	// Load model node.php
	require_once APP_PATH.'/model/route.php';
	if ($verbMethod == 'get') {
		$idRoute = intval($_POST['id_route']);
		
		$routeData = get_route_by_id($idRoute);
		if ($routeData) {
			$warningMessage = null;
			$edgeList = get_route_edges($routeData['id_route'], true);
			
			if ($edgeList == null) {
				return generate_error("Internal query error: ".$mysqli->error);
			}
			
			$edgeSeq = []; $nodeSeq = [];
			
			//-- Check route
			$tmpNode1 = 0; $tmpNode2 = 0;
			
			$lastNode = null;
			foreach ($edgeList as $edgeItem) {
				if (empty($edgeItem['id_node_from']) || empty($edgeItem['id_node_dest'])) {
					$warningMessage = "Broken route. Please redraw.";
					break;
				}
				
				if ($edgeItem['direction'] > 0) {
					$tmpNode1 = $edgeItem['id_node_from'];
					$tmpNode2 = $edgeItem['id_node_dest'];
				} else {
					$tmpNode1 = $edgeItem['id_node_dest'];
					$tmpNode2 = $edgeItem['id_node_from'];
				}
				if ($lastNode == null) {
					$nodeSeq[] = $tmpNode1;
				} else {
					if ($lastNode != $tmpNode1) {
						$warningMessage = "Broken route. Please redraw.";
					}
				}
				
				$edgeSeq[] = $edgeItem['id_edge'];
				$nodeSeq[] = $tmpNode2;
				
				$lastNode = $tmpNode2;
			}
			return (array(
				'status' => 'ok',
				'data' => array(
					'id_route' => $routeData['id_route'],
					'route_name' => $routeData['route_name'],
					'route_code' => $routeData['route_code'],
					'node_seq' => $nodeSeq,
					'edge_seq' => $edgeSeq,
					'profile' => ''
				),
				'warning' => $warningMessage
			));
		} else {
			return generate_error("Route data not found!");
		}
		
	} else if ($verbMethod == 'save') {
		$processError = null;
		
		$idRoute = $_POST['id_route'];
		$routeName = $_POST['txt_route_name'];
		$routeCode = $_POST['txt_route_code'];
		
		
		$seqEdge = $_POST['seq_edge'];
		$seqNode = $_POST['seq_node'];
		
		if (!is_array($seqEdge) || !is_array($seqNode)) {
			return generate_error("Invalid parameter specified.");
		}
		
		if (!empty($idRoute)) {
			clear_route_edges($idRoute);
		} else {
			$idRoute = save_route(array(
				'route_name' => _db_to_query($routeName),
				'route_code' => _db_to_query($routeCode),
				'vehicle_type' => 1,
				'route_length' => 0.0, // TODO: Masukkan panjang trayek
				'cost_type' => 1,
				'status' => 1,
				'date_created' => _db_to_query(date('Y-m-d H:i:s'))
			));
			
			if (!$idRoute) {
				return generate_error("Cannot save route. Internal database error.");
			}
		}
		
		require_once APP_PATH.'/model/edge.php';
		
		$mysqli->autocommit(false);
		
		//-- Buat batch setiap 100 record...
		$recCounter = 0;
		
		$assignData = array();
		foreach ($seqEdge as $idx => $itemEdge) {
			$recCounter++;
			
			$edgeData = get_edge_by_id($itemEdge);
			if ($edgeData) {
				$direction = ($edgeData['id_node_dest'] == $seqNode[$idx] ? "'-1'" : "'1'");
				$assignData[] = [$idRoute, $itemEdge, $direction, ($idx+1)];
			} else {
				$processError = "Invalid input. Please refresh your browser and try again.";
				break;
			}
			
			if (($recCounter % 100) == 0) {
				if (!assign_route_edge($assignData)) {
					$processError = "Internal query error: ".$mysqli->error;
					break;
				}
				
				$assignData = array(); $recCounter = 0;
			}
			
		} // End foreach
		
		if (!empty($assignData) && empty($processError)) {
			if (!assign_route_edge($assignData)) {
				$processError = "Internal query error: ".$mysqli->error;
			}
		}
		
		
		if (empty($processError)) {
			$mysqli->commit();
			
			return array(
				'status' => 'ok',
				'data' => array(
					'new_id_route' => $idRoute
				)
			);
		} else {
			$mysqli->rollback();
			return generate_error($processError);
		}
		
	} else {
		return generate_error("Unrecognized verb: ".$verbMethod);
	}
}