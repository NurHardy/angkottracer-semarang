<?php
/*
 * controller/ajax/data.php
 * -----------------------------------------------------
 * Controller AJAX untuk modul data.
 * By Nur Hardyanto
 */

	/**
	 * Fungsi memroses khusus node.
	 * 
	 * @param string $actionVerb Kata kerja
	 * @return array Response JSON hasil pemrosesan
	 */
	function _data_ajax_node($actionVerb) {
		global $mysqli;
		
		// Load model node.php
		require_once APP_PATH.'/model/node.php';
		if ($actionVerb == 'get') {
			$nodeData = get_nodes();
			
			$nodes = array();
			foreach ($nodeData as $nodeItem) {
				$nodes[] = array(
					'id' => $nodeItem['id_node'],
					'name' => $nodeItem['node_name'],
					'position' => array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng']))
				);
			}
			return array(
					'status' => 'ok',
					'data' => $nodes
			);
		} else if ($actionVerb == 'getbyid') {
			$idNode = intval($_POST['id']);
			$nodeItem = get_node_by_id($idNode);
			
			if ($nodeItem) {
				$nodeInfo = array(
					'id' => $nodeItem['id_node'],
					'name' => $nodeItem['node_name'],
					'position' => array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng']))
				);
				require_once APP_PATH.'/model/edge.php';
				$adjEdgesList = get_neighbor_edges($idNode, true);
				$adjEdges = array();
				foreach ($adjEdgesList as $edgeItem) {
					$adjEdges[] = array(
							'id' => $edgeItem['id_node_adj'],
							'position' => array(
									'lat' => floatval($edgeItem['node_location_lat']),
									'lng' => floatval($edgeItem['node_location_lng'])
							),
							'name'		=> $edgeItem['node_name'],
							'distance'	=> $edgeItem['distance'],
							'reversible'	=> ($edgeItem['reversible']==1)
					);
				}
				
				
				return array(
						'status' => 'ok',
						'nodedata' => $nodeInfo,
						'edges' => $adjEdges
				);
			} else {
				return generate_error("Node data not found.");
			}
			
		} else if ($actionVerb == 'add') {
			$nodeData = json_decode($_POST['data'], true);
			if ($nodeData === null) {
				return generate_error("Error read parameter data.");
			}
			
			//-- Validation --
			if (!isset($nodeData['lat']) || !isset($nodeData['lng']) || !isset($_POST['node_name'])) {
				return generate_error("Incomplete parameter.");
			}
			$nodeName = $_POST['node_name'];
			$nodePosLat = floatval($nodeData['lat']);
			$nodePosLng = floatval($nodeData['lng']);
			// TODO: Validasi lat, lng, nama
			
			//foreach ($nodes as $nodeItem) {
				$nodeDataQuery = array();
				$nodeDataQuery['node_name'] = _db_to_query($nodeName);
				$nodeDataQuery['location'] = sprintf("GeomFromText( 'POINT(%f %f)', 0 )", $nodePosLng, $nodePosLat);
				$nodeDataQuery['id_area'] = 0;
				$nodeDataQuery['id_creator'] = 0;
				$nodeDataQuery['creator'] = "'system'";
				if ($newId = save_node($nodeDataQuery, -1)) {
					$savedNodeData = array(
							'id' => $newId,
							'name' => $nodeName,
							'lat' => $nodePosLat,
							'lng' => $nodePosLng
					);
					return array(
							'status' => 'ok',
							'data' => $savedNodeData
					);
				} else {
					echo mysqli_error($mysqli);
				}
			//}
		} else {
			return generate_error("Unrecognized verb: ".$actionVerb);
		}
	}

	/**
	 * Fungsi memroses khusus edge/tepi.
	 *
	 * @param string $actionVerb Kata kerja
	 * @return array Response JSON hasil pemrosesan
	 */
	function _data_ajax_edge($actionVerb) {
		global $mysqli;
	
		// Load model edge.php
		require_once APP_PATH.'/model/edge.php';
		if ($actionVerb == 'add') {
			$nodeData = json_decode($_POST['data'], true);
			if ($nodeData === null) {
				return generate_error("Error read parameter data.");
			}
			
			if (!isset($nodeData['id_node_1']) || !isset($nodeData['id_node_2'])) {
				return generate_error("Incomplete parameter specified.");
			}
			
			require_once APP_PATH.'/model/node.php';
			$dataNode1 = get_node_by_id($nodeData['id_node_1']);
			$dataNode2 = get_node_by_id($nodeData['id_node_2']);
			
			//-- Validation --
			if (!isset($nodeData['lat']) || !isset($nodeData['lng']) || !isset($_POST['edge_direction'])) {
				return generate_error("Incomplete parameter.");
			}
			$edgeDirection = $_POST['edge_direction'];
			
		}
	}
	$actionVerb = $_POST['verb'];
	
	$verbSegments = explode('.', $actionVerb, 2);
	$verbObject = $verbSegments[0];
	$verbMethod = (isset($verbSegments[1]) ? $verbSegments[1] : null);
	
	if ($verbObject == "node") {
		$jsonResponse = _data_ajax_node($verbMethod);
	} else if ($verbObject == "edge") {
		$jsonResponse = _data_ajax_edge($verbMethod);
	} else {
		$jsonResponse = generate_error('Unrecognized verb.');
	}
	
