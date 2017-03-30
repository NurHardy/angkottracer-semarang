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
	
	public function get_route_by_id() {
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
	}
}