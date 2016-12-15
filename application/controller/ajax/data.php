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
				require_once APP_PATH.'/helper/geo-tools.php';
				
				$adjEdgesList = get_neighbor_edges($idNode, true);
				$adjEdges = array();
				foreach ($adjEdgesList as $edgeItem) {
					$polyLineData = mysql_to_latlng_coords($edgeItem['polyline_data']);
					$adjEdges[] = array(
							'id_edge' => $edgeItem['id_edge'],
							'id' => $edgeItem['id_node_adj'],
							'position' => array(
									'lat' => floatval($edgeItem['node_location_lat']),
									'lng' => floatval($edgeItem['node_location_lng'])
							),
							'name'		=> $edgeItem['node_name'],
							'distance'	=> $edgeItem['distance'],
							'polyline_data'	=> $polyLineData,
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
			require_once APP_PATH.'/model/node.php';
			require_once APP_PATH.'/helper/geo-tools.php';
			
			$nodeData = json_decode($_POST['data'], true);
			if ($nodeData === null) {
				return generate_error("Error read parameter data.");
			}
			
			if (!isset($nodeData['id_node_1']) || !isset($nodeData['id_node_2']) || !isset($_POST['edge_direction'])) {
				return generate_error("Incomplete parameter specified.");
			}
			
			$dataNode1 = get_node_by_id($nodeData['id_node_1']);
			$dataNode2 = get_node_by_id($nodeData['id_node_2']);
			
			//-- Validation --
			if (empty($dataNode1) || empty($dataNode2)) {
				return generate_error("Invalid node data specified.");
			}
			$edgeDirection = intval($_POST['edge_direction']);
			
			$nodePos1 = array(
					'lat' => floatval($dataNode1['location_lat']),
					'lng' => floatval($dataNode1['location_lng'])
				);
			$nodePos2 = array(
					'lat' => floatval($dataNode2['location_lat']),
					'lng' => floatval($dataNode2['location_lng'])
			);
			
			$newEdgeData = array();
			$newEdgeData['distance'] = distance($nodePos1['lat'], $nodePos1['lng'], $nodePos2['lat'], $nodePos2['lng'], 'K');

			if ($edgeDirection < 0) {
				$newEdgeData['id_node_from'] = $dataNode2['id_node'];
				$newEdgeData['id_node_dest'] = $dataNode1['id_node'];
			} else {
				$newEdgeData['id_node_from'] = $dataNode1['id_node'];
				$newEdgeData['id_node_dest'] = $dataNode2['id_node'];
			}
			$newEdgeData['traffic_index'] = 1.0;
			$newEdgeData['id_creator'] = 0;
			$newEdgeData['creator'] = "'system'";
			$newEdgeData['reversible'] = ($edgeDirection == 0 ? 1 : 0);
			
			if ($newId = save_edge($newEdgeData, -1)) {
				$savedEdgeData = array(
						'id' => $newId,
						'pos1' => $nodePos1,
						'pos2' => $nodePos2,
						'reversible' => ($newEdgeData['reversible'])
				);
				return array(
						'status' => 'ok',
						'data' => $savedEdgeData
				);
			} else {
				return generate_error("Query error while saving the edge record.");
			}
		
		//----------- Hapus edge -----------
		} else if ($actionVerb == 'delete') {
			$idEdge = $_POST['id'];
			$processResult = delete_edge($idEdge, false);
			
			if ($processResult) {
				return array(
						'status' => 'ok'
				);
			}
			return generate_error("Query error while delete the edge record.");
			
		//---------- Get by Id ------------
		} else if ($actionVerb == 'getbyid') {
			$idEdge = intval($_POST['id']);
			$edgeItem = get_edge_by_id($idEdge);
				
			if ($edgeItem) {
				$edgeInfo = array(
						'id' => $edgeItem['id_edge']
				);
				
				//-- Fetch node info
				require_once APP_PATH.'/model/node.php';
				$nodeFromData = get_node_by_id($edgeItem['id_node_from']);
				$nodeDestData = get_node_by_id($edgeItem['id_node_dest']);
				
				if (!$nodeFromData || !$nodeDestData) {
					return generate_error("Node data not found.");
				}
				$edgeInfo['from'] = array(
						'id_node' => $nodeFromData['id_node'],
						'position' => array(
								'lat' => floatval($nodeFromData['location_lat']),
								'lng' => floatval($nodeFromData['location_lng'])
						),
				);
				$edgeInfo['dest'] = array(
						'id_node' => $nodeDestData['id_node'],
						'position' => array(
								'lat' => floatval($nodeDestData['location_lat']),
								'lng' => floatval($nodeDestData['location_lng'])
						),
				);
				require_once APP_PATH.'/helper/geo-tools.php';
		
				$polyLineData = mysql_to_latlng_coords($edgeItem['polyline_data']);
		
				$edgeInfo['polyline_data'] = $polyLineData;
				return array(
						'status' => 'ok',
						'edgedata' => $edgeInfo
				);
			} else {
				return generate_error("Edge data not found.");
			}
			
		} else if ($actionVerb == 'refine') {
			require_once APP_PATH.'/model/node.php';
			require_once APP_PATH.'/model/edge.php';
			require_once APP_PATH.'/helper/road-tools.php';
			require_once APP_PATH.'/helper/geo-tools.php';
			
			$idEdge = $_POST['id'];
			
			$dataEdge = get_edge_by_id($idEdge);
			
			if (!$dataEdge) {
				return (array(
						'status' => 'error',
						'message' => 'Edge data not found.'
				));
			}
			
			$nodeStartData = get_node_by_id($dataEdge['id_node_from']);
			$nodeDestData = get_node_by_id($dataEdge['id_node_dest']);
			
			$responseFeedback = snap_road_api(array(
				array('lat' => $nodeStartData['location_lat'], 'lng' => $nodeStartData['location_lng']),
				array('lat' => $nodeDestData['location_lat'], 'lng' => $nodeDestData['location_lng'])
			));
			
			$jsonData = json_decode($responseFeedback);
			
			if ($jsonData->status == 'OK') {
				require_once APP_PATH.'/helper/gmap-tools.php';
				foreach ($jsonData->routes as $itemRoute) {
					$strPolyline = $itemRoute->overview_polyline;
					$poliLines = decode_polyline($strPolyline->points);
					
					$responseFeedback = $poliLines;
					break;
				}
				
				$geomText = latlng_coords_to_mysql($responseFeedback);
				$edgeData = array(
					'polyline' => "GeomFromText('".$geomText."')"
				);
				
				save_edge($edgeData, $idEdge);
				return (array(
						'status' => 'ok',
						'data' => $responseFeedback
				));
			}
			
			return (array(
					'status' => $jsonData->status,
					'data' => $responseFeedback
			));
			
			//$jsonurl = "https://maps.google.com/maps/api/geocode/json?sensor=false&address=1600+Pennsylvania+Avenue+Northwest+Washington+DC+20500";
			//echo $json = file_get_contents($jsonurl);
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
	
