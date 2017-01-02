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
			
			// Node map memetakan id_node ke index
			$nodeMap = array();
			$nodes = array();
			
			$ctrId = 0;
			foreach ($nodeData as $nodeItem) {
				$nodes[$ctrId] = array(
					'id' => $nodeItem['id_node'],
					'name' => $nodeItem['node_name'],
					'position' => array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng']))
				);
				
				$nodeMap[$nodeItem['id_node']] = $ctrId;
				$ctrId++;
			}
			
			//-- List semua edge...
			require_once APP_PATH.'/model/edge.php';
			require_once APP_PATH.'/helper/geo-tools.php';
			require_once APP_PATH.'/helper/gmap-tools.php';
			
			$edgeList = get_edges(true);
			
			$edges = array();
			foreach ($edgeList as $edgeItem) {
				$points = mysql_to_latlng_coords($edgeItem['polyline_data']);
				
				array_unshift($points, $nodes[$nodeMap[$edgeItem['id_node_from']]]['position']);
				array_push($points, $nodes[$nodeMap[$edgeItem['id_node_dest']]]['position']);
				
				$encPolyline = encode_polyline($points);
				
				$edges[] = array(
					'edge_name' => $edgeItem['id_edge'],
					'id_edge' => $edgeItem['id_edge'],
					'id_node_from' => $edgeItem['id_node_from'],
					'id_node_dest' => $edgeItem['id_node_dest'],
					'polyline' => $encPolyline,
					'reversible' => ($edgeItem['reversible'] == 1)
				);
			}
			return array(
					'status' => 'ok',
					'data' => $nodes,
					'edge' => $edges
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
				require_once APP_PATH.'/helper/gmap-tools.php';
				require_once APP_PATH.'/helper/geo-tools.php';
				
				$adjEdgesList = get_neighbor_edges($idNode, true);
				$adjEdges = array();
				foreach ($adjEdgesList as $edgeItem) {
					$polyLineData = mysql_to_latlng_coords($edgeItem['polyline_data']);
					$encPolyLine = encode_polyline($polyLineData);
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
							'polyline_dir'	=> $edgeItem['polyline_dir'],
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
			
		} else if ($actionVerb == 'add-from-edgevertex') {
			require_once APP_PATH.'/model/edge.php';
			require_once APP_PATH.'/model/node.php';
			require_once APP_PATH.'/helper/node-tools.php';
			
			$idEgde = $_POST['id_edge'];
			$vertexIndex = $_POST['vertex_index'];
			
			$newNodeId = create_node_from_edgevertex($idEgde, $vertexIndex);
			
			if ($newNodeId) {
				return array(
						'status' => 'ok',
						'new_id' => $newNodeId
				);
			}
			return generate_error("Operation failed.");
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
			
			require_once APP_PATH.'/helper/geo-tools.php';
			
			// TODO: Validasi lat, lng, nama
			
			//foreach ($nodes as $nodeItem) {
				$nodeDataQuery = array();
				$nodeDataQuery['node_name'] = _db_to_query($nodeName);
				$nodeDataQuery['location'] = "GeomFromText('".latlng_point_to_mysql($nodePosLng, $nodePosLat)."')";
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
			
		//-- Proses hapus node
		} else if ($actionVerb == 'delete') {
			// Proses hapus node akan menghapus record node, dan semua edge yang adjacent
			
		} else if ($actionVerb == 'edit') {
			// Memindahkan node
			$idEgde = $_POST['id'];
			$latLngData = $_POST['position'];
			$newLabel = $_POST['name'];
			$trafficIdx = $_POST['traffic'];
			
			return array(
					'status' => 'ok',
					'data' => $latLngData
			);
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
						'id' => $edgeItem['id_edge'],
						'reversible' => ($edgeItem['reversible'] == 1)
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
			
		} else if ($actionVerb == 'save') {
			$encPolyLine = $_POST['new_path'];
			$idEdge = $_POST['id'];
			
			require_once APP_PATH.'/helper/gmap-tools.php';
			require_once APP_PATH.'/helper/geo-tools.php';
			
			$polyLineData = decode_polyline($encPolyLine);
			
			$lastIdx = count($polyLineData)-1;
			
			//-- Napus vertex pertama dan terakhir karena merupakan node
			unset($polyLineData[$lastIdx]);
			unset($polyLineData[0]);
			
			$polyLineSql = latlng_coords_to_mysql($polyLineData);
			$updateData = array(
				'polyline' => "GeomFromText('".$polyLineSql."')"
			);
			
			$queryResult = save_edge($updateData, $idEdge);
			
			if ($queryResult) {
				return array(
						'status' => 'ok',
						'edgedata' => 1
				);
			}
			
			return generate_error("Query failed.");
			
		} else if ($actionVerb == 'saveandbreak') {
			require_once APP_PATH.'/model/node.php';
			require_once APP_PATH.'/helper/gmap-tools.php';
			require_once APP_PATH.'/helper/geo-tools.php';
			require_once APP_PATH.'/helper/node-tools.php';
			
			$encPolyLine = $_POST['new_path'];
			$idEdge = $_POST['id'];
			$idxVertex = intval($_POST['vertex_idx']);
				
			$polyLineData = decode_polyline($encPolyLine);
				
			$lastIdx = count($polyLineData)-1;
				
			//-- Napus vertex pertama dan terakhir karena merupakan node
			unset($polyLineData[$lastIdx]);
			unset($polyLineData[0]);
			
			$errorMsg = null;
			$newId = save_and_break_edge($polyLineData, $idxVertex, $idEdge, true, $errorMsg);
			
			if ($newId) {
				return (array(
						'status' => 'ok',
						'new_node_id' => $newId,
						'new_node_pos' => $polyLineData[$idxVertex],
						'new_node_name' => 'Untitled'
				));
			} else {
				return generate_error("Process failed: ".$errorMsg);
			}
			
		} else if ($actionVerb == 'interpolate') {
			require_once APP_PATH.'/model/node.php';
			require_once APP_PATH.'/helper/road-tools.php';
			require_once APP_PATH.'/helper/gmap-tools.php';
			require_once APP_PATH.'/helper/geo-tools.php';
				
			$encPath = $_POST['path'];
				
			$pathData = decode_polyline($encPath);
						
			$responseFeedback = snap_road_api(
					$pathData
			);
				
			$jsonData = json_decode($responseFeedback);
			$snappedPoints = $jsonData->snappedPoints;
			
			$snappedOutput = array();
			foreach ($snappedPoints as $itemPoint) {
				$snappedOutput[] = array('lat' => $itemPoint->location->latitude, 'lng' => $itemPoint->location->longitude);
			}
			
			$warningMessage = (isset($jsonData->warning) ? $jsonData->warning : null);
			
			return (array(
					'status' => 'ok',
					'snapdata' => $snappedOutput,
					'warning' => $warningMessage
			));
				
		} else if ($actionVerb == 'direction') {
			require_once APP_PATH.'/model/node.php';
			require_once APP_PATH.'/helper/road-tools.php';
			require_once APP_PATH.'/helper/geo-tools.php';
			
			$startPos = $_POST['origin'];
			$destPos = $_POST['dest'];
			
			//-- Validation
			if (!isset($startPos['lat']) || !isset($startPos['lng']) ||
					!isset($destPos['lat']) || !isset($destPos['lng'])) {
				return generate_error("Please recheck input.");
			}
			
			$responseFeedback = map_direction_api(
				$startPos, $destPos
			);
			
			$jsonData = json_decode($responseFeedback);
			
			if ($jsonData->status == 'OK') {
				require_once APP_PATH.'/helper/gmap-tools.php';
				
				// Parse every point to a polyline
				foreach ($jsonData->routes as $itemRoute) {
					$strPolyline = $itemRoute->overview_polyline;
					$polyLineData = decode_polyline($strPolyline->points);
					break;
				}
				
				/*
				$geomText = latlng_coords_to_mysql($responseFeedback);
				$edgeData = array(
					'polyline' => "GeomFromText('".$geomText."')"
				);
				save_edge($edgeData, $idEdge);
				*/
				return (array(
						'status' => 'ok',
						'path' => $polyLineData
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
	
