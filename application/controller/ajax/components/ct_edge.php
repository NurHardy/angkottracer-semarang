<?php

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
					'edgedata' => array(
							'edge_name' => null,
							'id_node_from' => $newEdgeData['id_node_from'],
							'id_node_dest' => $newEdgeData['id_node_dest'],
							'reversible' => $newEdgeData['reversible']
					)
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
					'edgedata' => array(
						'edge_name' => $edgeItem['edge_name'],
						'id_node_from' => $edgeItem['id_node_from'],
						'id_node_dest' => $edgeItem['id_node_dest'],
						'reversible' => ($edgeItem['reversible'] == 1),
					)
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
					'node_data' => array(
							'node_name' => $nodeFromData['node_name'],
							'node_type' => $nodeFromData['node_type']
					)
			);
			$edgeInfo['dest'] = array(
					'id_node' => $nodeDestData['id_node'],
					'position' => array(
							'lat' => floatval($nodeDestData['location_lat']),
							'lng' => floatval($nodeDestData['location_lng'])
					),
					'node_data' => array(
							'node_name' => $nodeDestData['node_name'],
							'node_type' => $nodeDestData['node_type']
					)
			);
			require_once APP_PATH.'/helper/geo-tools.php';
			require_once APP_PATH.'/helper/gmap-tools.php';

			$polyLineData = mysql_to_latlng_coords($edgeItem['polyline_data']);
			$decPolyLine = encode_polyline($polyLineData);
			
			$edgeInfo['polyline_data'] = $decPolyLine;
			
			return array(
					'status' => 'ok',
					'data' => $edgeInfo
			);
		} else {
			return generate_error("Edge data not found.");
		}
			
	} else if ($actionVerb == 'saveprop') {
		$idEdge = $_POST['id'];
		$edgeName = $_POST['edge_name'];
		$reversibleVal = $_POST['reversible'];
			
		$updateData = array(

		);

		$queryResult = save_edge($updateData, $idEdge);

		if ($queryResult) {
			$mysqli->commit();
			return array(
					'status' => 'ok',
					'edgedata' => 1
			);
		} else {
			$mysqli->rollback();
		}

		return generate_error("Query failed.");
			
	} else if ($actionVerb == 'save') {
		$encPolyLine = $_POST['new_path'];
		$idEdge = $_POST['id'];
		$idNodeFrom = $_POST['id_node_from'];
		$idNodeDest = $_POST['id_node_dest'];
		$edgeName = (isset($_POST['edge_name']) ? $_POST['edge_name'] : null);
		$isReversible = ($_POST['reversible'] == 1 ? true : false);
		
		//-- Validation...
		//if (empty($edgeName)) {
		//	return generate_error("Please enter edge name.");
		//}
		
		require_once APP_PATH.'/helper/gmap-tools.php';
		require_once APP_PATH.'/helper/geo-tools.php';
		require_once APP_PATH.'/model/node.php';
			
		$polyLineData = decode_polyline($encPolyLine);
			
		$lastIdx = count($polyLineData)-1;
			
		//-- Start transaction
		$mysqli->autocommit(false);
			
		//-- Update posisi node ujung...
		$nodeDataQuery = array();
			
		$nodeDataQuery['location'] = "GeomFromText('".latlng_coord_to_mysql($polyLineData[$lastIdx])."')";
		if (!save_node($nodeDataQuery, $idNodeDest)) {
			$mysqli->rollback();
			return generate_error("Query failed.");
		}
			
		$nodeDataQuery['location'] = "GeomFromText('".latlng_coord_to_mysql($polyLineData[0])."')";
		if (!save_node($nodeDataQuery, $idNodeFrom)) {
			$mysqli->rollback();
			return generate_error("Query failed.");
		}
			
		//-- Napus vertex pertama dan terakhir karena merupakan node
		unset($polyLineData[$lastIdx]);
		unset($polyLineData[0]);
			
		$polyLineSql = latlng_coords_to_mysql($polyLineData);
		$updateData = array(
				'edge_name' => _db_to_query($edgeName),
				'id_node_from' => intval($idNodeFrom),
				'id_node_dest' => intval($idNodeDest),
				'polyline' => db_geom_from_text($polyLineSql),
				'reversible' => ($isReversible ? 1 : 0)
		);
			
		$queryResult = save_edge($updateData, $idEdge);
			
		if ($queryResult) {
			$mysqli->commit();
			return array(
					'status' => 'ok',
					'edgedata' => 1
			);
		} else {
			$mysqli->rollback();
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
		//unset($polyLineData[$lastIdx]);
		//unset($polyLineData[0]);
			
		$errorMsg = null;
		$newPolyline = array();
		$newId = save_and_break_edge($polyLineData, $idxVertex, $idEdge, $errorMsg, $newPolyline, true);
			
		if ($newId) {
			//-- Append node ujung...
			array_unshift($newPolyline[0]['polyline'], $polyLineData[0]);
			array_push($newPolyline[0]['polyline'], $polyLineData[$idxVertex]);
				
			array_unshift($newPolyline[1]['polyline'], $polyLineData[$idxVertex]);
			array_push($newPolyline[1]['polyline'], $polyLineData[$lastIdx]);
				
			$newPolyline[0]['polyline'] = encode_polyline($newPolyline[0]['polyline']);
			$newPolyline[1]['polyline'] = encode_polyline($newPolyline[1]['polyline']);

			return (array(
					'status' => 'ok',
					'new_node_id' => strval($newId),
					'new_node_pos' => $polyLineData[$idxVertex],
					'new_polyline' => $newPolyline,
					'new_node_data' => array(
							'node_name' => 'Untitled',
							'node_type' => 0
					)
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