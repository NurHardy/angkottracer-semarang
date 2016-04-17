<?php
/*
 * controller/ajax/data.php
 * -----------------------------------------------------
 * Controller AJAX untuk modul data.
 * By Nur Hardyanto
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
				require_once APP_PATH.'/model/vertex.php';
				$adjVertexesList = get_adjacent_vertex($idNode);
				$adjVertexes = array();
				foreach ($adjVertexesList as $vertexItem) {
					$destNode = ($vertexItem['id_node_from']==$idNode ?
							$vertexItem['id_node_dest'] :
							$vertexItem['id_node_from']);
					$adjVertexes[] = array(
							'dest' => $destNode,
							'distance' => $vertexItem['distance'],
							'reversible' => ($vertexItem['reversible']==1)
					);
				}
				
				
				return array(
						'status' => 'ok',
						'nodedata' => $nodeInfo,
						'vertexes' => $adjVertexes
				);
			} else {
				return generate_error("Node data not found.");
			}
			
		} else if ($actionVerb == 'add') {
			foreach ($nodes as $nodeItem) {
				$nodeData = array();
				$nodeData['node_name'] = _db_to_query($nodeItem['name']);
				$nodeData['location'] = sprintf("GeomFromText( 'POINT(%f %f)', 0 )", $nodeItem['lng'], $nodeItem['lat']);
				$nodeData['id_area'] = 0;
				$nodeData['id_creator'] = 0;
				$nodeData['creator'] = "'system'";
				if (!save_node($nodeData, -1)) {
					echo mysqli_error($mysqli);
				}
			}
			
			
		} else {
			return generate_error("Unrecognized verb: ".$actionVerb);
		}
	}

	$actionVerb = $_POST['verb'];
	
	$verbSegments = explode('.', $actionVerb, 2);
	$verbObject = $verbSegments[0];
	$verbMethod = (isset($verbSegments[1])?$verbSegments[1]:null);
	
	if ($verbObject == "node") {
		$jsonResponse = _data_ajax_node($verbMethod);
	} else {
		$jsonResponse = generate_error('Unrecognized verb.');
	}
	
