<?php

function _generate_init_data() {
	global $mysqli;

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
				'id_edge' => $edgeItem['id_edge'],
				'edge_data' => array(
						'edge_name' => $edgeItem['edge_name'],
						'id_node_from' => $edgeItem['id_node_from'],
						'id_node_dest' => $edgeItem['id_node_dest'],
						'reversible' => ($edgeItem['reversible'] == 1)
				),
				'polyline' => $encPolyline
		);
	}
	return array(
			'status' => 'ok',
			'data' => $nodes,
			'edge' => $edges
	);
}

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
		return _generate_init_data();
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
					
				//-- Append node position in edges
				if ($edgeItem['polyline_dir'] > 0) {
					array_unshift($polyLineData, array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng'])
					));
					array_push($polyLineData, array(
							'lat' => floatval($edgeItem['node_location_lat']),
							'lng' => floatval($edgeItem['node_location_lng'])
					));
				} else {
					array_unshift($polyLineData, array(
							'lat' => floatval($edgeItem['node_location_lat']),
							'lng' => floatval($edgeItem['node_location_lng'])
					));
					array_push($polyLineData, array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng'])
					));
				}
					
					
				$encPolyline = encode_polyline($polyLineData);
				$adjEdges[] = array(
						'id_edge' => $edgeItem['id_edge'],
						'edge_data' => array(
								'edge_name' => $edgeItem['edge_name'],
								'id_node_from' => $edgeItem['id_node_from'],
								'id_node_dest' => $edgeItem['id_node_dest'],
								'reversible' => ($edgeItem['reversible'] == 1)
						),
						'polyline' => $encPolyline,
							
						//-- Additional data
						'id_node_adj' => $edgeItem['id_node_adj'],
						'adj_node_position' => array(
								'lat' => floatval($edgeItem['node_location_lat']),
								'lng' => floatval($edgeItem['node_location_lng'])
						),
						'distance'	=> $edgeItem['distance'],
						'polyline_dir'	=> $edgeItem['polyline_dir']
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
					'position' => array(
							'lat' => $nodePosLat,
							'lng' => $nodePosLng
					)
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
		require_once APP_PATH.'/helper/geo-tools.php';
			
		// Memindahkan node
		$idNode = $_POST['id'];
		$latLngData = $_POST['position'];
		//$newLabel = $_POST['name'];
		//$trafficIdx = $_POST['traffic'];
			
		$latLngObj = array(
				'lat' => floatval($latLngData['lat']),
				'lng' => floatval($latLngData['lng'])
		);
		$locationQuery = db_geom_from_text(latlng_coord_to_mysql($latLngObj));
		$updateData = array(
				'location' => $locationQuery
		);
		if (save_node($updateData, $idNode)) {
			return array(
					'status' => 'ok',
					'data' => $latLngData
			);
		} else {
			return generate_error("Query failed.");
		}
			
	} else {
		return generate_error("Unrecognized verb: ".$actionVerb);
	}
}