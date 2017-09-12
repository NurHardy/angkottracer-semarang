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
		$this->_timeStart = microtime(true);
	}

	public function refresh_distances($request, $response, $args) {
		$timeStart = $this->_timeStart;
		
		require_once SRCPATH.'/models/NodeModel.php';
		require_once SRCPATH.'/models/EdgeModel.php';
		require_once SRCPATH.'/models/RouteModel.php';
		require_once SRCPATH.'/helpers/geo_tools.php';
		require_once SRCPATH."/helpers/cache_tools.php";
		
		$mysqli = $this->container->get('db');
		$nodeModel = new NodeModel($mysqli);
		$edgeModel = new EdgeModel($mysqli);
		
		$warning = [];
		
		$edgeLengthTotal = 0.0;
		$edgeCountTotal = 0;
		$edgeLength = 0.0;
		
		$nodeCache = [];
		$edgeList = $edgeModel->get_edges(true);
		/*
		foreach ($edgeList as $edgeItem) {
			$itemTimeStart = microtime(true);
			
			$polyLineData = mysql_to_latlng_coords($edgeItem['polyline_data']);
			
			if (!key_exists($edgeItem['id_node_from'], $nodeCache)) {
				$nodeCache[$edgeItem['id_node_from']] = $nodeModel->get_node_by_id($edgeItem['id_node_from']);
				
				if ($nodeCache[$edgeItem['id_node_from']] == false) {
					$warning[] = "[Edge #".$edgeItem['id_edge']."] Node #".$edgeItem['id_node_from']." not found.";
					continue;
				}
			}
			
			if (!key_exists($edgeItem['id_node_dest'], $nodeCache)) {
				$nodeCache[$edgeItem['id_node_dest']] = $nodeModel->get_node_by_id($edgeItem['id_node_dest']);
				
				if ($nodeCache[$edgeItem['id_node_dest']] == false) {
					$warning[] = "[Edge #".$edgeItem['id_edge']."] Node #".$edgeItem['id_node_dest']." not found.";
					continue;
				}
			}
			
			//-- Append node position in edges
			array_unshift($polyLineData, array(
					'lat' => floatval($nodeCache[$edgeItem['id_node_from']]['location_lat']),
					'lng' => floatval($nodeCache[$edgeItem['id_node_from']]['location_lng'])
			));
			array_push($polyLineData, array(
					'lat' => floatval($nodeCache[$edgeItem['id_node_dest']]['location_lat']),
					'lng' => floatval($nodeCache[$edgeItem['id_node_dest']]['location_lng'])
			));
			
			$edgeLength = polyline_length($polyLineData, 'K');
			
			$updateData = ['distance' => $edgeLength];
			$procResult = $edgeModel->save_edge($updateData, $edgeItem['id_edge']);
			
			if ($procResult) {
				$edgeLengthTotal += $edgeLength;
				$edgeCountTotal++;
			} else {
				$warning[] = "[Edge #".$edgeItem['id_edge']."] Internal query error: ".$mysqli->error;
			}
			
		} // End foreach
		*/
		$routeModel = new RouteModel($mysqli);
		cache_build($nodeModel, $edgeModel, $routeModel);
		$timeEnd = microtime(true);
		$execution_time = round(($timeEnd - $this->_timeStart),4);
		
		$this->_data = array(
				'status' => 'ok',
				'message' => 'Refresh done. '.$edgeCountTotal.' edges with '.$edgeLengthTotal.' km length updated.',
				'warning' => $warning,
				'exec_time' => $execution_time
		);
		
		return $response->withJson($this->_data, $this->_status);
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
	
	public function hello($request, $response, $args) {
		require_once SRCPATH.'/helpers/geo_tools.php';
		$output = "";
		$data = [
				[-6.98958,	110.42182],
				[-6.98893,	110.42050],
				[-6.98796,	110.41846],
				[-6.98756,	110.41754],
				[-6.98746,	110.41710],
				[-6.98684,	110.41502],
				[-6.98665,	110.41431],
				[-6.98646,	110.41369],
				[-6.98614,	110.41290],
				[-6.98591,	110.41247],
				[-6.98549,	110.41157],
				[-6.98483,	110.41011]
		];
		
		$totalDist = 0.0;
		foreach ($data as $key => $item) {
			$line = sprintf(" ==[%f, %f]", $data[$key][0], $data[$key][1]);
			$output .= $line."\n";
			if ($key == 0) continue;
			
			$dist = distance($data[$key-1][0], $data[$key-1][1], $data[$key][0], $data[$key][1], 'K');
			$output .= " dist: ".$dist."\n";
			
			$totalDist += $dist;
		}
		
		$output .= " ====== TOTAL: ".$totalDist."\n";
		// Render index view
		$args['pageTitle'] = "Debug Output";
		$args['output'] = $output;
		//"Jarak: ".distance(-6.990402, 110.422958, -6.984323, 110.409318, 'K');;
		return $this->renderer->render($response, 'debug/basic_output.php', $args);
	}
}