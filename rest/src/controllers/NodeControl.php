<?php

/**
 * 
 * Controller node
 * @author Nur Hardyanto
 *
 */
class NodeControl {
	
	private $_data;
	private $_status;
	protected $container;
	protected $renderer;

	// constructor receives container instance
	public function __construct($container) {
		$this->container = $container;
		$this->renderer = $this->container->get('renderer');
		$this->_data = [];
		$this->_status = 200;
	}
	
	public function get_nodes($request, $response, $args)  {
		echo "<pre>";
		var_dump($args);
		echo "</pre>";
	}
	
	public function get_node_by_id ($request, $response, $args) {
		require_once SRCPATH . '\models\NodeModel.php';
	
		$nodeModel = new NodeModel($this->container->get('db'));
		//print_r($nodeModel->get_node_by_id($args['id']));
		
		$idNode = intval($args['id']);
		$nodeItem = $nodeModel->get_node_by_id($idNode);
			
		if ($nodeItem) {
			$nodeInfo = array(
					'id' => $nodeItem['id_node'],
					'position' => array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng'])),
					'node_data' => array(
							'node_name' => $nodeItem['node_name'],
							'node_type' => $nodeItem['node_type']
					)
			);
			require_once SRCPATH.'/models/EdgeModel.php';
			require_once SRCPATH.'/helpers/gmap_tools.php';
			require_once SRCPATH.'/helpers/geo_tools.php';
		
			$edgeModel = new EdgeModel($this->container->get('db'));
			$adjEdgesList = $edgeModel->get_neighbor_edges($idNode, true);
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
		
			$this->_data = array(
					'status' => 'ok',
					'nodedata' => $nodeInfo,
					'edges' => $adjEdges
			);
		} else {
			$this->_status = HTTPSTATUS_NOTFOUND;
			$this->_data = generate_error("Node data not found.");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	public function add_node($request, $response, $args) {
		$postData = $request->getParsedBody();
		
		$nodeData = json_decode($postData['data'], true);
		if ($nodeData === null) {
			$this->_status = HTTPSTATUS_BADREQUEST;
			$this->_data = generate_error("Error read parameter data.");
			return $response->withJson($this->_data, $this->_status);
		}
			
		//-- Validation --
		if (!isset($nodeData['lat']) || !isset($nodeData['lng']) || !isset($postData['node_name'])) {
			$this->_status = HTTPSTATUS_BADREQUEST;
			$this->_data = generate_error("Incomplete parameter.");
			return $response->withJson($this->_data, $this->_status);
		}
		$nodeName = $postData['node_name'];
		$nodePosLat = floatval($nodeData['lat']);
		$nodePosLng = floatval($nodeData['lng']);
		$nodeType = (isset($postData['node_type']) ? $postData['node_type'] : 0);
		$idNodeToConnect = (isset($postData['connect_to']) ? $postData['connect_to'] : null);
			
		require_once SRCPATH.'\helpers\geo_tools.php';
		require_once SRCPATH .'\models\NodeModel.php';
		$mysqli = $this->container->get('db');
		
		$mysqli->autocommit(false);
		
		$nodeModel = new NodeModel($mysqli);
		// TODO: Validasi lat, lng, nama
			
		//foreach ($nodes as $nodeItem) {
		$nodeDataQuery = array();
		$nodeDataQuery['node_name'] = _db_to_query($nodeName, $mysqli);
		$nodeDataQuery['location'] = "GeomFromText('".latlng_point_to_mysql($nodePosLng, $nodePosLat)."')";
		$nodeDataQuery['id_area'] = 0;
		$nodeDataQuery['id_creator'] = 0;
		$nodeDataQuery['creator'] = "'system'";
		$nodeDataQuery['node_type'] = $nodeType;
		
		if ($newId = $nodeModel->save_node($nodeDataQuery, -1)) {
			$savedEdgeData = null;
			
			//-- Process meta
			if (!empty($idNodeToConnect)) {
				$adjNodePos = [];
				$adjNodeData = $nodeModel->get_node_by_id($idNodeToConnect);
				if (!$adjNodeData) {
					$mysqli->rollback();
					$this->_status = HTTPSTATUS_BADREQUEST;
					$this->_data = generate_error("Invalid node id specified.");
					return $response->withJson($this->_data, $this->_status);
				}
				
				$adjNodePos['lat'] = floatval($adjNodeData['location_lat']);
				$adjNodePos['lng'] = floatval($adjNodeData['location_lng']);
				
				$newEdgeData = array();
				$newEdgeData['distance'] = distance($adjNodePos['lat'], $adjNodePos['lng'], $nodePosLat, $nodePosLng, 'K');
				$newEdgeData['id_node_from'] = intval($idNodeToConnect);
				$newEdgeData['id_node_dest'] = $newId;
				$newEdgeData['traffic_index'] = 1.0;
				$newEdgeData['id_creator'] = 0;
				$newEdgeData['creator'] = "'system'";
				$newEdgeData['reversible'] = 1;
				
				require_once SRCPATH.'\models\EdgeModel.php';
				$edgeModel = new EdgeModel($mysqli);
				
				if ($newIdEdge = $edgeModel->save_edge($newEdgeData, -1)) {
					$savedEdgeData = array(
							'id' => $newIdEdge,
							'pos1' => $adjNodePos,
							'pos2' => ['lat' => $nodePosLat, 'lng' => $nodePosLng],
							'edgedata' => array(
									'edge_name' => null,
									'id_node_from' => $newEdgeData['id_node_from'],
									'id_node_dest' => $newEdgeData['id_node_dest'],
									'reversible' => $newEdgeData['reversible']
							),
							'edge_length' => floatval($newEdgeData['distance'])
					);
				} else {
					$mysqli->rollback();
					$this->_status = HTTPSTATUS_BADREQUEST;
					$this->_data = generate_error("Internal query error. Cannot create new edge.");
					return $response->withJson($this->_data, $this->_status);
				}
			}
			
			$mysqli->commit();
			//-- Output
			$savedNodeData = array(
					'id' => $newId,
					'position' => array(
							'lat' => $nodePosLat,
							'lng' => $nodePosLng
					),
					'node_data' => array(
							'node_name' => $nodeName,
							'node_type' => $nodeType
					)
			);
			$this->_data = array(
					'status' => 'ok',
					'data' => $savedNodeData
			);
			
			if (!empty($savedEdgeData)) {
				$this->_data['new_edge'] = $savedEdgeData;
			}
		} else {
			$mysqli->rollback();
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Internal error: ". mysqli_error($mysqli));
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	//-- Edit node
	public function edit_node($request, $response, $args) {
		$postData = $request->getParsedBody();
		
		require_once SRCPATH .'\models\NodeModel.php';
		require_once SRCPATH.'/helpers/geo_tools.php';
		$mysqli = $this->container->get('db');
		$nodeModel = new NodeModel($mysqli);
		
		$idNode = $args['id'];
		$updateData = [];
		
		if (isset($postData['position'])) {
			// Memindahkan node?
			$latLngData = $postData['position'];
			$latLngObj = array(
					'lat' => floatval($latLngData['lat']),
					'lng' => floatval($latLngData['lng'])
			);
			$locationQuery = db_geom_from_text(latlng_coord_to_mysql($latLngObj));
			$updateData['location'] = $locationQuery;
		}
		
		if (isset($postData['node_name'])) {
			$newLabel = $postData['node_name'];
			$updateData['node_name'] = _db_to_query($newLabel, $mysqli);
		}
		
		if (isset($postData['node_type'])) {
			$newNodeType = $postData['node_type'];
			
			$nodeTypeList = $nodeModel->get_node_types();
			if (!key_exists($newNodeType, $nodeTypeList)) {
				$this->_status = HTTPSTATUS_BADREQUEST;
				$this->_data = generate_error("Invalid node type.");
				return $response->withJson($this->_data, $this->_status);
			}
			$updateData['node_type'] = $newNodeType;
		}
		
		if (empty($updateData)) {
			$this->_status = HTTPSTATUS_BADREQUEST;
			$this->_data = generate_error("Empty parameter.");
			return $response->withJson($this->_data, $this->_status);
		}
		
		if ($nodeModel->save_node($updateData, $idNode)) {
			$this->_data = array(
					'status' => 'ok',
					'data' => $latLngData
			);
		} else {
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query failed.");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	public function delete_node_by_id($request, $response, $args) {
		$mysqli = $this->container->get('db');
		require_once SRCPATH.'\models\NodeModel.php';
		$nodeModel = new NodeModel($mysqli);
		
		$idNode = intval($args['id']);
		
		//-- Check each neighbor edges
		$isDeleteOk = true;
		$passingRouteList = [];
		
		require_once SRCPATH.'/models/EdgeModel.php';
		require_once SRCPATH.'/models/RouteModel.php';
		
		$edgeModel = new EdgeModel($mysqli);
		$routeModel = new RouteModel($mysqli);
		
		$adjEdgesList = $edgeModel->get_neighbor_edges($idNode, true);
		foreach ($adjEdgesList as $adjEdgeItem) {
			$routeList = $routeModel->get_edge_route($adjEdgeItem['id_edge']);
			if ($routeList) {
				$isDeleteOk = false;
				foreach ($routeList as $routeItem) {
					$passingRouteList[$routeItem['id_route']] = [
						'code' => $routeItem['route_code'],
						'label' => $routeItem['route_name']
					];
				}
			} // End if
		}
		
		if (!$isDeleteOk) {
			$this->_status = HTTPSTATUS_FORBIDDEN;
			$this->_data = generate_error("Ada trayek yang melalui node. Silakan edit trayek terlebih dahulu sebelum menghapus node.");
			$this->_data['routes'] = $passingRouteList;
			return $response->withJson($this->_data, $this->_status);
		}
		
		//-- Everything is OK, begin the transaction
		$mysqli->autocommit(false);
		$processResult = true;
		
		// Delete adjacent edges
		foreach ($adjEdgesList as $adjEdgeItem) {
			$processResult = $edgeModel->delete_edge($adjEdgeItem['id_edge']);
			if (!$processResult) {
				break;
			}
		}
		
		if ($processResult) {
			$processResult = $nodeModel->delete_node($idNode, false);
		}
			
		if ($processResult) {
			$mysqli->commit();
			$this->_data = array(
					'status' => 'ok'
			);
		} else {
			$mysqli->rollback();
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query error while delete the node/edge record.");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
}