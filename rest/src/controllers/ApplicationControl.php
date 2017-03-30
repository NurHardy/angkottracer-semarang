<?php

/**
 *
 * Controller untuk aplikasi
 * @author Nur Hardyanto
 *
 */
class ApplicationControl {

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

	public function get_init_data($request, $response, $args) {
		require_once SRCPATH.'/models/NodeModel.php';
		$nodeModel = new NodeModel($this->container->get('db'));
		
		$nodeData = $nodeModel->get_nodes();
		
		// Node map memetakan id_node ke index
		$nodeMap = array();
		$nodes = array();
		
		$ctrId = 0;
		foreach ($nodeData as $nodeItem) {
			$nodes[$ctrId] = array(
					'id' => $nodeItem['id_node'],
					'position' => array(
							'lat' => floatval($nodeItem['location_lat']),
							'lng' => floatval($nodeItem['location_lng'])),
					'node_data' => array(
							'node_name' => $nodeItem['node_name'],
							'node_type' => $nodeItem['node_type']
					)
			);
		
			$nodeMap[$nodeItem['id_node']] = $ctrId;
			$ctrId++;
		}
		
		//-- List semua edge...
		require_once SRCPATH.'/models/EdgeModel.php';
		$edgeModel = new EdgeModel($this->container->get('db'));
		
		require_once SRCPATH.'/helpers/geo_tools.php';
		require_once SRCPATH.'/helpers/gmap_tools.php';
		
		$edgeList = $edgeModel->get_edges(true);
		
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
		
		$this->_data = array(
				'status' => 'ok',
				'data' => $nodes,
				'edge' => $edges
		);
		
		return $response->withJson($this->_data, $this->_status);
	}
}