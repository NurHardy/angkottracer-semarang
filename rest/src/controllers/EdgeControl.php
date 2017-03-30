<?php

/**
 *
 * Controller node
 * @author Nur Hardyanto
 *
 */
class EdgeControl {

	private $_data;
	private $_status;
	protected $container;
	protected $renderer;

	// constructor receives container instance
	public function __construct($container) {
		$this->container = $container;
		$this->renderer = $this->container->get('renderer');
		$this->_data = [];
		$this->_status = HTTPSTATUS_OK;
	}
	
	public function get_edge_by_id($request, $response, $args) {
		require_once SRCPATH.'\models\EdgeModel.php';
		$edgeModel = new EdgeModel($this->container->get('db'));
		
		$idEdge = intval($args['id']);
		$edgeItem = $edgeModel->get_edge_by_id($idEdge);
		
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
			require_once SRCPATH.'\models\NodeModel.php';
			$nodeModel = new NodeModel($this->container->get('db'));
			
			$nodeFromData = $nodeModel->get_node_by_id($edgeItem['id_node_from']);
			$nodeDestData = $nodeModel->get_node_by_id($edgeItem['id_node_dest']);
		
			if (!$nodeFromData || !$nodeDestData) {
				$this->_data = generate_error("Node data not found.");
				return $response->withJson($this->_data, $this->_status);
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
			require_once SRCPATH.'\helpers\geo_tools.php';
			require_once SRCPATH.'\helpers\gmap_tools.php';
		
			$polyLineData = mysql_to_latlng_coords($edgeItem['polyline_data']);
			$decPolyLine = encode_polyline($polyLineData);
				
			$edgeInfo['polyline_data'] = $decPolyLine;
				
			$this->_data = array(
					'status' => 'ok',
					'data' => $edgeInfo
			);
		} else {
			$this->_status = HTTPSTATUS_NOTFOUND;
			$this->_data = generate_error("Edge data not found.");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	public function add_edge($request, $response, $args) {
		require_once SRCPATH.'\models\NodeModel.php';
		require_once SRCPATH.'\helpers\geo_tools.php';
		
		$postData = $request->getParsedBody();
		
		$nodeModel = new NodeModel($this->container->get('db'));
		
		$nodeData = json_decode($postData['data'], true);
		if ($nodeData === null) {
			$this->_data = generate_error("Error read parameter data.");
			return $response->withJson($this->_data, $this->_status);
		}
			
		if (!isset($nodeData['id_node_1']) || !isset($nodeData['id_node_2']) || !isset($postData['edge_direction'])) {
			$this->_data = generate_error("Incomplete parameter specified.");
			return $response->withJson($this->_data, $this->_status);
		}
			
		$dataNode1 = $nodeModel->get_node_by_id($nodeData['id_node_1']);
		$dataNode2 = $nodeModel->get_node_by_id($nodeData['id_node_2']);
			
		//-- Validation --
		if (empty($dataNode1) || empty($dataNode2)) {
			$this->_data = generate_error("Invalid node data specified.");
			return $response->withJson($this->_data, $this->_status);
		}
		$edgeDirection = intval($postData['edge_direction']);
			
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
		
		require_once SRCPATH.'\models\EdgeModel.php';
		$edgeModel = new EdgeModel($this->container->get('db'));
		
		if ($newId = $edgeModel->save_edge($newEdgeData, -1)) {
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
			$this->_data = array(
					'status' => 'ok',
					'data' => $savedEdgeData
			);
		} else {
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query error while saving the edge record.");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	/**
	 * Menyimpan busur.<br />
	 * <br />
	 * ARGS:
	 * <ul>
	 * 	<li>id			: ID busur yang diedit</li>
	 * </ul>
	 * <br />
	 * POST data parameter:
	 * <ul>
	 * 	<li>new_path		: Encoded path</li>
	 *  <li>id_node_from	: ID node awal busur</li>
	 *  <li>id_node_dest	: ID node tujuan busur</li>
	 *  <li>edge_name		: (Optional) Nama busur</li>
	 *  <li>reversible		: 1 | 0. Apakah bidirectional?</li>
	 * </ul>
	 * 
	 * @param unknown $request
	 * @param unknown $response
	 * @param unknown $args
	 */
	public function save_edge($request, $response, $args) {
		$mysqli = $this->container->get('db');
		$postData = $request->getParsedBody();
		
		$encPolyLine = $postData['new_path'];
		$idEdge = $args['id'];
		$idNodeFrom = $postData['id_node_from'];
		$idNodeDest = $postData['id_node_dest'];
		$edgeName = (isset($postData['edge_name']) ? $postData['edge_name'] : null);
		$isReversible = ($postData['reversible'] == 1 ? true : false);
		
		//-- Validation...
		//if (empty($edgeName)) {
		//	return generate_error("Please enter edge name.");
		//}
		
		require_once SRCPATH.'/helpers/gmap_tools.php';
		require_once SRCPATH.'/helpers/geo_tools.php';
		require_once SRCPATH.'/models/EdgeModel.php';
		require_once SRCPATH.'/models/NodeModel.php';
		
		$nodeModel = new NodeModel($mysqli);
		$edgeModel = new EdgeModel($mysqli);
		
		$polyLineData = decode_polyline($encPolyLine);
			
		$lastIdx = count($polyLineData)-1;
			
		//-- Start transaction
		$mysqli->autocommit(false);
			
		//-- Update posisi node ujung...
		$nodeDataQuery = array();
			
		$nodeDataQuery['location'] = "GeomFromText('".latlng_coord_to_mysql($polyLineData[$lastIdx])."')";
		if (!$nodeModel->save_node($nodeDataQuery, $idNodeDest)) {
			$mysqli->rollback();
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query failed.");
			return $response->withJson($this->_data, $this->_status);
		}
			
		$nodeDataQuery['location'] = "GeomFromText('".latlng_coord_to_mysql($polyLineData[0])."')";
		if (!$nodeModel->save_node($nodeDataQuery, $idNodeFrom)) {
			$mysqli->rollback();
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query failed.");
			return $response->withJson($this->_data, $this->_status);
		}
			
		//-- Napus vertex pertama dan terakhir karena merupakan node
		unset($polyLineData[$lastIdx]);
		unset($polyLineData[0]);
			
		$polyLineSql = latlng_coords_to_mysql($polyLineData);
		$updateData = array(
				'edge_name' => _db_to_query($edgeName, $mysqli),
				'id_node_from' => intval($idNodeFrom),
				'id_node_dest' => intval($idNodeDest),
				'polyline' => db_geom_from_text($polyLineSql),
				'reversible' => ($isReversible ? 1 : 0)
		);
			
		$queryResult = $edgeModel->save_edge($updateData, $idEdge);
			
		if ($queryResult) {
			$mysqli->commit();
			$this->_data = array(
					'status' => 'ok',
					'edgedata' => 1
			);
		} else {
			$mysqli->rollback();
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query failed.");
		}
			
		return $response->withJson($this->_data, $this->_status);
	}
	
	public function interpolate_edge($request, $response, $args) {
		require_once SRCPATH.'/models/NodeModel.php';
		$nodeModel = new NodeModel($this->container->get('db'));
		
		require_once SRCPATH.'/helpers/road_tools.php';
		require_once SRCPATH.'/helpers/gmap_tools.php';
		require_once SRCPATH.'/helpers/geo_tools.php';
		
		$postData = $request->getParsedBody();
		
		$encPath = $postData['path'];
		
		$pathData = decode_polyline($encPath);
		
		$responseFeedback = snap_road_api(
				$pathData
				);
		
		$jsonData = json_decode($responseFeedback);
		
		if (!isset($jsonData->snappedPoints)) {
			if (isset($jsonData->error->message)) {
				$messageStr = $jsonData->error->message;
				$this->_status = HTTPSTATUS_INTERNALERROR;
				$this->_data = generate_error("Query to Google API error: ".$messageStr);
			} else {
				$this->_status = HTTPSTATUS_INTERNALERROR;
				$this->_data = generate_error("Query to Google API failed.");
			}
			
		} else {
			$snappedPoints = $jsonData->snappedPoints;
				
			$snappedOutput = array();
			foreach ($snappedPoints as $itemPoint) {
				$snappedOutput[] = array('lat' => $itemPoint->location->latitude, 'lng' => $itemPoint->location->longitude);
			}
				
			$warningMessage = (isset($jsonData->warning) ? $jsonData->warning : null);
			
			$this->_data = (array(
					'status' => 'ok',
					'snapdata' => $snappedOutput,
					'warning' => $warningMessage
			));
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	public function save_edge_and_break($request, $response, $args) {
		require_once SRCPATH.'/models/NodeModel.php';
		require_once SRCPATH.'/models/EdgeModel.php';
		$nodeModel = new NodeModel($this->container->get('db'));
		$edgeModel = new EdgeModel($this->container->get('db'));
		
		require_once SRCPATH.'/helpers/gmap_tools.php';
		require_once SRCPATH.'/helpers/geo_tools.php';
		require_once SRCPATH.'/helpers/node_tools.php';
		
		$postData = $request->getParsedBody();
		
		$encPolyLine = $postData['new_path'];
		$idEdge = $postData['id'];
		$idxVertex = intval($postData['vertex_idx']);
		
		$polyLineData = decode_polyline($encPolyLine);
		
		$lastIdx = count($polyLineData)-1;
		
		//-- Napus vertex pertama dan terakhir karena merupakan node
		//unset($polyLineData[$lastIdx]);
		//unset($polyLineData[0]);
			
		$errorMsg = null;
		$newPolyline = array();
		
		$mysqli = $this->container->get('db');
		$newId = save_and_break_edge([
				$mysqli, $nodeModel, $edgeModel
		], $polyLineData, $idxVertex, $idEdge, $errorMsg, $newPolyline, true);
			
		if ($newId) {
			//-- Append node ujung...
			array_unshift($newPolyline[0]['polyline'], $polyLineData[0]);
			array_push($newPolyline[0]['polyline'], $polyLineData[$idxVertex]);
		
			array_unshift($newPolyline[1]['polyline'], $polyLineData[$idxVertex]);
			array_push($newPolyline[1]['polyline'], $polyLineData[$lastIdx]);
		
			$newPolyline[0]['polyline'] = encode_polyline($newPolyline[0]['polyline']);
			$newPolyline[1]['polyline'] = encode_polyline($newPolyline[1]['polyline']);
		
			$this->_data = (array(
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
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Process failed: ".$errorMsg);
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	public function get_direction($request, $response, $args) {
		require_once SRCPATH.'/models/NodeModel.php';
		$nodeModel = new NodeModel($this->container->get('db'));
		
		require_once SRCPATH.'/helpers/road_tools.php';
		require_once SRCPATH.'/helpers/geo_tools.php';
			
		$postData = $request->getParsedBody();
		
		$startPos = $postData['origin'];
		$destPos = $postData['dest'];
			
		//-- Validation
		if (!isset($startPos['lat']) || !isset($startPos['lng']) ||
				!isset($destPos['lat']) || !isset($destPos['lng'])) {
			$this->_status = HTTPSTATUS_BADREQUEST;
			$this->_data = generate_error("Please recheck input.");
			return $response->withJson($this->_data, $this->_status);
		}
			
		$responseFeedback = map_direction_api(
				$startPos, $destPos
		);
		
		if (!$responseFeedback) {
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query to Google API failed.");
			
			return $response->withJson($this->_data, $this->_status);
		}
		
		$jsonData = json_decode($responseFeedback);
			
		if ($jsonData->status == 'OK') {
			require_once SRCPATH.'/helpers/gmap_tools.php';

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
			$this->_data = (array(
					'status' => 'ok',
					'path' => $polyLineData
			));
		} else {
			$this->_data = (array(
					'status' => $jsonData->status,
					'data' => $responseFeedback
			));
		}
			
		return $response->withJson($this->_data, $this->_status);
		//$jsonurl = "https://maps.google.com/maps/api/geocode/json?sensor=false&address=1600+Pennsylvania+Avenue+Northwest+Washington+DC+20500";
		//echo $json = file_get_contents($jsonurl);
	}
	public function delete_edge_by_id($request, $response, $args) {
		require_once SRCPATH.'\models\EdgeModel.php';
		$edgeModel = new EdgeModel($this->container->get('db'));
	
		$idEdge = intval($args['id']);
		
		//-- Check if there are route passed the edge
		require_once SRCPATH.'\models\RouteModel.php';
		$routeModel = new RouteModel($this->container->get('db'));
		$routeList = $routeModel->get_edge_route($idEdge);
		
		if ($routeList) {
			$this->_status = HTTPSTATUS_FORBIDDEN;
			$this->_data = generate_error("Ada trayek yang melalui busur. Silakan ubah trayek terlebih dahulu sebelum menghapus busur.");
			$this->_data['routes'] = [];
			
			foreach ($routeList as $routeItem) {
				$this->_data['routes'][$routeItem['id_route']] = [
					'code' => $routeItem['route_code'],
					'label' => $routeItem['route_name']
				];
			}
			return $response->withJson($this->_data, $this->_status);
		}
		
		//-- Everything is OK
		$processResult = $edgeModel->delete_edge($idEdge, false);
			
		if ($processResult) {
			$this->_data = array(
					'status' => 'ok'
			);
		} else {
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query error while delete the edge record.");
		}
	
		return $response->withJson($this->_data, $this->_status);
	}
	
}