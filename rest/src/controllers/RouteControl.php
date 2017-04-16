<?php

/**
 *
 * Controller untuk trayek
 * @author Nur Hardyanto
 *
 */
class RouteControl {

	private $_data;
	private $_status;
	private $_timeStart;

	protected $container;
	protected $renderer;

	// constructor receives container instance
	public function __construct($container) {
		$this->container = $container;
		$this->renderer = $this->container->get('renderer');
		$this->_data = [];
		$this->_status = 200;
	}
	
	public function get_route_by_id($request, $response, $args) {
		$mysqli = $this->container->get('db');
		$postData = $request->getParsedBody();
		
		$idRoute = intval($args['id']);
		
		require_once SRCPATH.'/models/EdgeModel.php';
		require_once SRCPATH.'/models/RouteModel.php';
		
		$edgeModel = new EdgeModel($mysqli);
		$routeModel = new RouteModel($mysqli);
		
		$routeData = $routeModel->get_route_by_id($idRoute);
		if ($routeData) {
			$warningMessage = null;
			$edgeList = $routeModel->get_route_edges($routeData['id_route'], true);
				
			if ($edgeList == null) {
				$this->_status = HTTPSTATUS_INTERNALERROR;
				$this->_data = generate_error("Internal query error: ".$mysqli->error);
				return $response->withJson($this->_data, $this->_status);
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
			$this->_data = (array(
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
			$this->_data = generate_error("Route data not found!");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	public function delete_route_by_id($request, $response, $args) {
		$mysqli = $this->container->get('db');
		
		require_once SRCPATH.'/models/RouteModel.php';
		$routeModel = new RouteModel($mysqli);
		
		$idRoute = intval($args['id']);
		
		//-- Everything is OK
		$mysqli->autocommit(false);
		
		//-- Clear route edges first
		$processResult = $routeModel->clear_route_edges($idRoute);
		
		//-- Then, clear the route itself
		if ($processResult) $processResult = $routeModel->delete_route($idRoute);
		
		if ($processResult) {
			$mysqli->commit();
			$this->_data = array(
				'status' => 'ok'
			);
		} else {
			$mysqli->rollback();
			$this->_status = HTTPSTATUS_INTERNALERROR;
			$this->_data = generate_error("Query error while delete the edge record.");
		}
		
		return $response->withJson($this->_data, $this->_status);
	}
	public function save_route($request, $response, $args) {
		$mysqli = $this->container->get('db');
		$postData = $request->getParsedBody();
	
		require_once SRCPATH.'/models/EdgeModel.php';
		require_once SRCPATH.'/models/RouteModel.php';
	
		$edgeModel = new EdgeModel($mysqli);
		$routeModel = new RouteModel($mysqli);
	
		$processError = null;
	
		$idRoute = intval($args['id']);
		$routeName = $postData['txt_route_name'];
		$routeCode = $postData['txt_route_code'];
	
		$seqEdge = $postData['seq_edge'];
		$seqNode = $postData['seq_node'];
	
		if (!is_array($seqEdge) || !is_array($seqNode)) {
			$this->_status = HTTPSTATUS_BADREQUEST;
			$this->_data = generate_error("Invalid parameter specified.");
			return $response->withJson($this->_data, $this->_status);
		}
	
		if (!empty($idRoute)) {
			//-- Rewrite existing route
			$routeModel->clear_route_edges($idRoute);
		} else {
			//-- Create new route
			$idRoute = $routeModel->save_route(array(
					'route_name' => _db_to_query($routeName, $mysqli),
					'route_code' => _db_to_query($routeCode, $mysqli),
					'vehicle_type' => 1,
					'route_length' => 0.0, // TODO: Masukkan panjang trayek
					'cost_type' => 1,
					'status' => 1,
					'date_created' => _db_to_query(date('Y-m-d H:i:s'), $mysqli)
			));
	
			if (!$idRoute) {
				$this->_status = HTTPSTATUS_INTERNALERROR;
				$this->_data = generate_error("Cannot save route. Internal database error.");
				return $response->withJson($this->_data, $this->_status);
			}
		}
	
		$mysqli->autocommit(false);
		
		//-- Buat batch setiap 100 record...
		$recCounter = 0;
	
		$assignData = array();
		foreach ($seqEdge as $idx => $itemEdge) {
			$recCounter++;
	
			$edgeData = $edgeModel->get_edge_by_id($itemEdge);
			if ($edgeData) {
				$direction = ($edgeData['id_node_dest'] == $seqNode[$idx] ? "'-1'" : "'1'");
				$assignData[] = [$idRoute, $itemEdge, $direction, ($idx+1)];
			} else {
				$this->_status = HTTPSTATUS_BADREQUEST;
				$processError = "Invalid input. Please refresh your browser and try again.";
				break;
			}
	
			if (($recCounter % 100) == 0) {
				if (!$routeModel->assign_route_edge($assignData)) {
					$this->_status = HTTPSTATUS_INTERNALERROR;
					$processError = "Internal query error: ".$mysqli->error;
					break;
				}
	
				$assignData = array(); $recCounter = 0;
			}
	
		} // End foreach
	
		if (!empty($assignData) && empty($processError)) {
			if (!$routeModel->assign_route_edge($assignData)) {
				$this->_status = HTTPSTATUS_INTERNALERROR;
				$processError = "Internal query error: ".$mysqli->error;
			}
		}
	
		if (empty($processError)) {
			$mysqli->commit();
	
			$this->_data = array(
					'status' => 'ok',
					'data' => array(
							'new_id_route' => $idRoute
					)
			);
		} else {
			$mysqli->rollback();
			$this->_data = generate_error($processError);
		}
		return $response->withJson($this->_data, $this->_status);
	}
}