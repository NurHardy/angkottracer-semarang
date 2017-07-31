<?php

/**
 *
 * Controller algorithm
 * @author Nur Hardyanto
 *
 */
class AlgorithmControl {

	private $_data;
	private $_status;
	private $_timeStart;
	
	private $_path_routeicon = '';
	private $_def_routeicon = '';
	
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
	
	public function search_route($request, $response, $args) {
		//-- Get params
		$startPos = $request->getQueryParam('start', null);
		$destPos = $request->getQueryParam('dest', null);
		
		$optAvoid = $request->getQueryParam('avoid', '');
		$isVerbose = $request->getQueryParam('verbose', 0);
		
		if (($startPos === null) || ($destPos === null)) {
			$this->_status = HTTPSTATUS_BADREQUEST;
			$this->_data = generate_error("Start or destination point is not specified.");
			return $response->withJson($this->_data, $this->_status);
		}
		
		$optAvoidArr = explode(',', $optAvoid);
		foreach ($optAvoidArr as $optIdx => $optAvoidItem) {
			$optAvoidArr[$optIdx] = strtolower(trim($optAvoidItem));
		}
		
		$useAngkot	= !in_array('angkot', $optAvoidArr);
		$useBrt		= !in_array('brt', $optAvoidArr);
		
		require_once(SRCPATH."/helpers/geo_tools.php");
		require_once(SRCPATH."/helpers/gmap_tools.php");
		require_once(SRCPATH."/models/NodeModel.php");
		
		$nodeModel = new NodeModel($this->container->get('db'));
		
		//-- Process input parameter
		$nodeList = $nodeModel->get_nodes_by_radius($startPos, 0.5);
		
		//-- Cari jarak paling minimal... (Posisi berangkat)
		$minDist = null;
		$minDistNode = null;
		
		$minDistShelter = null; // Shelter BRT terdekat
		$minDistShelterNode = null;
		
		foreach ($nodeList as $curKey => $nodeItem) {
			$calcDist = node_distance($startPos, ['lat' => $nodeItem['location_lat'], 'lng' => $nodeItem['location_lng']], 'K');
			if ($minDist === null) {
				$minDist = $calcDist;
				$minDistNode = $nodeItem['id_node'];
			} else if ($calcDist < $minDist) {
				$minDist = $calcDist;
				$minDistNode = $nodeItem['id_node'];
			}
			
			// Shelter BRT?
			if (($nodeItem['node_type'] == '1') || ($nodeItem['node_type'] == '2')) {
				if ($minDistShelter === null) {
					$minDistShelter = $calcDist;
					$minDistShelterNode = $nodeItem['id_node'];
				} else if ($calcDist < $minDistShelter) {
					$minDistShelter = $calcDist;
					$minDistShelterNode = $nodeItem['id_node'];
				}
			}
			
			$nodeList[$curKey]['dist'] = $calcDist;
		}
		
		if ($minDistNode === null) {
			$this->_data = generate_error('Route not found.');
			return $response->withJson($this->_data, $this->_status);
		}
		
		//-- Naik dari shelter (jika tidak menghindari BRT)
		if ($useBrt && ($minDistShelterNode != null)) {
			$idNodeFrom = $minDistShelterNode;
		} else {
			$idNodeFrom = $minDistNode;
		}
		
		$data = ($tmp);
		
		//-- Cari jarak paling minimal... (Posisi tujuan)
		$nodeList = $nodeModel->get_nodes_by_radius($destPos, 0.5);
		
		$minDist = null;
		$minDistNode = null;
		
		$minDistShelter = null; // Shelter BRT terdekat
		$minDistShelterNode = null;
		
		foreach ($nodeList as $curKey => $nodeItem) {
			$calcDist = node_distance($destPos, ['lat' => $nodeItem['location_lat'], 'lng' => $nodeItem['location_lng']], 'K');
			$isCurrentMin = false;
			if ($minDist === null) {
				$minDist = $calcDist;
				$minDistNode = $nodeItem['id_node'];
			} else if ($calcDist < $minDist) {
				$minDist = $calcDist;
				$minDistNode = $nodeItem['id_node'];
			}
			
			//-- Rekam shelter terdekat...
			if (($nodeItem['node_type'] == '1') || ($nodeItem['node_type'] == '2')) {
				if ($minDistShelter === null) {
					$minDistShelter = $calcDist;
					$minDistShelterNode = $nodeItem['id_node'];
				} else if ($calcDist < $minDistShelter) {
					$minDistShelter = $calcDist;
					$minDistShelterNode = $nodeItem['id_node'];
				}
			}
			
			$nodeList[$curKey]['dist'] = $calcDist;
		}
		
		if ($minDistNode === null) {
			$this->_data = generate_error('Route not found.');
			return $response->withJson($this->_data, $this->_status);
		}
		
		//-- Naik dari shelter (jika tidak menghindari BRT)
		if ($useBrt && ($minDistShelterNode != null)) {
			$idNodeDest = $minDistShelterNode;
		} else {
			$idNodeDest = $minDistNode;
		}
		
		//-- Action
		$searchOpts = [
			'angkot' => $useAngkot,
			'brt' => $useBrt
		];
		$this->_data = $this->_do_astar_algorithm($idNodeFrom, $idNodeDest, $searchOpts, ($isVerbose == 1));
		
		//$this->_data = generate_message('ok', 'Operation suceeded.');
		//$this->_data['data'] = $nodeList;
		
		return $response->withJson($this->_data, $this->_status);
		
	}
	public function astar($request, $response, $args) {
		$idNodeFrom = intval($args['from']);
		$idNodeDest = intval($args['dest']);
		
		$isDebug = $request->getQueryParam('debug', false);
		$isDebug = !empty($isDebug);
		
		$this->_data = $this->_do_astar_algorithm($idNodeFrom, $idNodeDest, null, $isDebug);
		
		return $response->withJson($this->_data, $this->_status);
	}
	
	/*
	 * A* search algorithm subroutine
	 * ---------------------------------------------
	 * Reference:
	 * - http://stackoverflow.com/questions/483488/strategy-to-find-your-best-route-via-public-transportation-only
	 */
	
	/*
	 * function A*(start, goal)
	 // The set of nodes already evaluated.
	 closedSet := {}
	 // The set of currently discovered nodes still to be evaluated.
	 // Initially, only the start node is known.
	 openSet := {start}
	 // For each node, which node it can most efficiently be reached from.
	 // If a node can be reached from many nodes, cameFrom will eventually contain the
	 // most efficient previous step.
	 cameFrom := the empty map
	
	 // For each node, the cost of getting from the start node to that node.
	 gScore := map with default value of Infinity
	 // The cost of going from start to start is zero.
	 gScore[start] := 0
	 // For each node, the total cost of getting from the start node to the goal
	 // by passing by that node. That value is partly known, partly heuristic.
	 fScore := map with default value of Infinity
	 // For the first node, that value is completely heuristic.
	 fScore[start] := heuristic_cost_estimate(start, goal)
	
	 while openSet is not empty
	 current := the node in openSet having the lowest fScore[] value
	 if current = goal
	 return reconstruct_path(cameFrom, current)
	
	 openSet.Remove(current)
	 closedSet.Add(current)
	 for each neighbor of current
	 if neighbor in closedSet
	 continue		// Ignore the neighbor which is already evaluated.
	 // The distance from start to a neighbor
	 tentative_gScore := gScore[current] + dist_between(current, neighbor)
	 if neighbor not in openSet	// Discover a new node
	 openSet.Add(neighbor)
	 else if tentative_gScore >= gScore[neighbor]
	 continue		// This is not a better path.
	
	 // This path is the best until now. Record it!
	 cameFrom[neighbor] := current
	 gScore[neighbor] := tentative_gScore
	 fScore[neighbor] := gScore[neighbor] + heuristic_cost_estimate(neighbor, goal)
	
	 return failure
	
	 function reconstruct_path(cameFrom, current)
	 total_path := [current]
	 while current in cameFrom.Keys:
	 current := cameFrom[current]
	 total_path.append(current)
	 return total_path
	 */
	
	private function _do_astar_algorithm($idNodeStart, $idNodeGoal, $searchOpts = null, $verbose = false) {
		$timeStart = $this->_timeStart;
	
		//require(APP_PATH."/controller/main/data.php");
		//$idNodeStart = $_POST['id_node_start'];
		//$idNodeGoal = $_POST['id_node_end'];
	
		$currentDate = date("Y-m-d H:i:s");
		
		//--- Default values...
		$useAngkot = $useBrt = true;
		
		if (!isset($configVerbose)) $configVerbose = true;
		if (isset($searchOpts['angkot']))	$useAngkot = $searchOpts['angkot'];
		if (isset($searchOpts['brt']))		$useBrt = $searchOpts['brt'];
		
		$shortestPathSeq = array();
		$verboseData = "";
		$verboseHtml = "";
		$publicRouteOut = "";
	
		//--- Ambil seluruh data dari cache...
		require_once(SRCPATH."/models/RouteModel.php");
		require_once(SRCPATH."/models/NodeModel.php");
		require_once(SRCPATH."/models/EdgeModel.php");
		require_once(SRCPATH."/helpers/geo_tools.php");
		require_once(SRCPATH."/helpers/gmap_tools.php");
		require_once(SRCPATH."/helpers/cache_tools.php");
	
		$nodeModel = new NodeModel($this->container->get('db'));
		$edgeModel = new EdgeModel($this->container->get('db'));
		$routeModel = new RouteModel($this->container->get('db'));
		
		$dbNode = [];
		$dbEdge = [];
		$dbRoute = $routeModel->get_routes();
		
		$this->_path_routeicon = $routeModel::$path_routeicon;
		$this->_def_routeicon = $routeModel::$default_routeicon;
		
		//--- Build (if not exist yet), and read cache file...
		if (!file_exists(SRCPATH."/cache/dbnode.json") ||
			!file_exists(SRCPATH."/cache/dbedge.json")) {
			cache_build($nodeModel, $edgeModel, $routeModel);
		}
		
		$dbNodeJson = file_get_contents(SRCPATH."/cache/dbnode.json");
		if (!$dbNodeJson) {
			return generate_error("Server cache data error. Please contact administrator.");
		}
		
		$dbNode = json_decode($dbNodeJson, true);
			
		$dbEdgeJson = file_get_contents(SRCPATH."/cache/dbedge.json");
		if (!$dbEdgeJson) {
			return generate_error("Server cache data error. Please contact administrator.");
		}
		
		$dbEdge = json_decode($dbEdgeJson, true);
		
		//--- Validasi data
		if (($dbNode == null) || ($dbEdge == null)) {
			return generate_error("Server cache data error. Please contact administrator.");
		}
		
		//--- Validasi input
		if (!key_exists($idNodeStart, $dbNode) || !key_exists($idNodeGoal, $dbNode)) {
			return generate_error("Invalid input!");
		}
	
	
		// Loop maximal
		// Kita gunakan untuk trap apabila terjadi infinite loop...
		define('MAX_LOOP', 10000);
	
		//--- Mulai algoritma A*
		$routeAlts = []; // Data list solusi transit
		$routeWaysData = []; // Output utama (informasi transit trayek, dsb)
		
		$visitedNodes = array();
		$openNodes = array($idNodeStart => 1);
	
		$cameFrom = array();
	
		$gScore = array();
		$gScore[$idNodeStart] = 0;
		
		//-- Kode trayek yang digunakan hingga node n
		$usedRoute = [];
		$usedRoute[$idNodeStart] = [];
		
		//-- Kode trayek yang tersedia pada node n
		$availableRoute = [];
		$availableRoute[$idNodeStart] = [];
	
		$fScore = array();
		$fScore[$idNodeStart] = distance(
				$dbNode[$idNodeStart]['location_lat'],
				$dbNode[$idNodeStart]['location_lng'],
				$dbNode[$idNodeGoal]['location_lat'],
				$dbNode[$idNodeGoal]['location_lng'], 'K'
				);
	
		$loopCount = 0;
		$verboseData .= "<pre>";
		while (!empty($openNodes) && ($loopCount < MAX_LOOP)) {
			//-- Ambil fScore terkecil
			$verboseData .= "----- Get smallest fScore...\n";
			reset($openNodes);
			$fScoreIndexCheck = key($openNodes);
			$fScoreCheck = $fScore[$fScoreIndexCheck];
			foreach($openNodes as $fIndex => $fItem) {
				$verboseData .= " [".$fIndex."] ".$dbNode[$fIndex]['node_name']." | score: ".$fScore[$fIndex]."\n";
				if ($fScore[$fIndex] < $fScoreCheck) {
					$fScoreCheck = $fScore[$fIndex];
					$fScoreIndexCheck = $fIndex;
				}
			}
			$verboseData .= "\n";
	
			$currentNodeId = $fScoreIndexCheck;
			
			//--------- Sampai di tujuan ------------------
			if ($currentNodeId == $idNodeGoal) {
				$fromNode = $idNodeGoal;
				$idEdge = $cameFrom[$fromNode][1];
					
				$finalRoute = array([$idNodeGoal, $idEdge]);
					
				while ($fromNode != $idNodeStart) {
					$fromNode = $cameFrom[$fromNode][0];
					$idEdge = ($fromNode == $idNodeStart ? null : $cameFrom[$fromNode][1]);
					$finalRoute[] = [$fromNode, $idEdge];
				}
				
				$routeWaysData = $this->_build_solution($finalRoute, $searchOpts, $dbNode, $dbEdge, $dbRoute,
						$edgeModel, $shortestPathSeq, $verboseData);
				break;
			}
	
			unset($openNodes[$currentNodeId]);
			$visitedNodes[] = $currentNodeId;
	
			if (!isset($gScore[$currentNodeId])) $gScore[$currentNodeId] = 9999.0;
			//$neighborNodes = get_neighbor_edges($current, false);
			$verboseData .= "------------------ Current Node: ".$currentNodeId." (".$dbNode[$currentNodeId]['node_name'].")-----\n";
			if (isset($availableRoute[$currentNodeId])) {
				$verboseData .= "   - Available routes: ( ".count($availableRoute[$currentNodeId]).") => ";
				foreach ($availableRoute[$currentNodeId] as $codePublicRoute) $verboseData .= $codePublicRoute.', ';
			} else {
				$verboseData .= "   - Available routes: ( undefined )";
			}
			$verboseData .= "\n";
			
			if (isset($usedRoute[$currentNodeId])) {
				$verboseData .= "   - Used routes: ( ".count($usedRoute[$currentNodeId]).") => ";
				foreach ($usedRoute[$currentNodeId] as $codePublicRoute) $verboseData .= $codePublicRoute.', ';
			} else {
				$verboseData .= "   - Used routes: ( undefined )";
			}
			$verboseData .= "\n";
			
			$verboseHtml .= '<div id="searchresult_step_'.$loopCount.'" data-idnode="'.$currentNodeId.'" class="searchresult_step">';
			$verboseHtml .= '<p>Current node: #'.$currentNodeId.' ('.htmlspecialchars($dbNode[$currentNodeId]['node_name']).')</p>';
			//$verboseData .= "Why? :\n";
			//print_r($fScore);
			//$verboseData .= "\n";
			//print_r($gScore);
			//$verboseData .= "\n";
			$verboseHtml .= '<table class="table table-condensed"><thead><tr><th>#id</th><th>Name</th><th>Distance</th></tr></thead><tbody>';
			foreach ($dbNode[$currentNodeId]['neighbors'] as $neighborNodeId => $neighborNodeData) {
				// Abaikan shuttle BRT jika opsi hindari BRT aktif...
				if (!$useBrt && ($neighborNodeData[1] >= 10000)) {
					continue;
				}
				
				$verboseHtml .= '<tr><td>#'.$neighborNodeId.'</td><td>'.htmlspecialchars($dbNode[$neighborNodeId]['node_name']).
					'</td><td>'.$neighborNodeData[0];
				//$neighborNodeId = $neighborNode['id_node'];
				$verboseData .= " o Neighbor node : ".$neighborNodeId." (".$dbNode[$neighborNodeId]['node_name']."), dist: ".$neighborNodeData[0]."\n";
				if (in_array($neighborNodeId, $visitedNodes)) {
					$verboseData .= "-- ignored\n";
					$verboseHtml .= ' (ignored)';
					continue;
				}
				
				//-- List trayek yang melalui current edge...
				$direction = ($dbEdge[$neighborNodeData[1]]['id_node_dest'] == $neighborNodeId ? 1 : -1);
				
				if (!isset($usedRoute[$neighborNodeId])) $usedRoute[$neighborNodeId] = [];
				if (!isset($availableRoute[$neighborNodeId])) $availableRoute[$neighborNodeId] = [];
				
				$heuristicDistance = 9999.99;
				$isUsedRouteEmpty = empty($usedRoute[$currentNodeId]);
				
				$shuttleCount = 0;
				foreach ($dbEdge[$neighborNodeData[1]]['routes'] as $codePublicRoute => $routeItem) {
					if ($routeItem[0] == $direction) {
						if (!$useAngkot && ($routeItem[1] == 1)) continue;
						if (!$useBrt && ($routeItem[1] == 2)) continue;
						
						/*$finishDistance = distance(
							$dbNode[$routeItem[3]]['location_lat'],
							$dbNode[$routeItem[3]]['location_lng'],
							$dbNode[$idNodeGoal]['location_lat'],
							$dbNode[$idNodeGoal]['location_lng'], 'K'
						);*/
						
						if ($routeItem[1] == 2) $shuttleCount++;
						$availableRoute[$neighborNodeId][] = $codePublicRoute;
						if ($isUsedRouteEmpty) {
							if (in_array($codePublicRoute, $availableRoute[$currentNodeId])) {
								$usedRoute[$neighborNodeId][] = $codePublicRoute;
							}
						} else {
							if (in_array($codePublicRoute, $usedRoute[$currentNodeId])) {
								$usedRoute[$neighborNodeId][] = $codePublicRoute;
							}
						}
						
					}
				}
				
				$verboseData .= "   - Available routes: ( ".count($availableRoute[$neighborNodeId]).") => ";
				foreach ($availableRoute[$neighborNodeId] as $codePublicRoute) $verboseData .= $codePublicRoute.', ';
				$verboseData .= "\n";
				
				$verboseData .= "   - Used routes: ( ".count($usedRoute[$neighborNodeId]).") => ";
				foreach ($usedRoute[$neighborNodeId] as $codePublicRoute) $verboseData .= $codePublicRoute.', ';
				$verboseData .= "\n";
				
				//-- Bandingkan angkot sekarang dengan angkot node parent...
				$intersects = [];
				//if (isset($cameFrom[$currentNodeId][0])) { // Pastikan bukan node start...
					$parentNodeId = $currentNodeId; //$cameFrom[$currentNodeId][0];
					$intersects = array_intersect($availableRoute[$parentNodeId], $availableRoute[$neighborNodeId]);
				//}
				
				$verboseData .= "   - Intersect routes: ( ".count($intersects).") => ";
				foreach ($intersects as $codePublicRoute) $verboseData .= $codePublicRoute.', ';
				$verboseData .= "\n";
				
				//-- Pengali
				$factor = 1;
				
				//-- Mengutamakan jalur yang dilalui angkot yang sama
				//   (meminimalkan ganti angkot)
				if (empty($intersects)) {
					$factor += 1;
				}
				if (empty($usedRoute[$neighborNodeId])) {
					$factor += 1;
				}
				
				//-- Meminimalkan mengunjungi jalan yang tidak dilalui angkot...
				if (empty($availableRoute[$neighborNodeId])) {
					$factor += 1;
				}
				//if ($shuttleCount == 0) {
				//	$factor += 1;
				//}
				
				if (!isset($gScore[$neighborNodeId])) $gScore[$neighborNodeId] = 9999.0;
				$tentativegScore = $gScore[$currentNodeId] + ($neighborNodeData[0]);
					
				if (!key_exists($neighborNodeId, $openNodes)) {
					$openNodes[$neighborNodeId] = 1;
				}
				
				//if ($tentativegScore >= $gScore[$neighborNodeId]) {
				//	$verboseHtml .= ' (ignored score: '.$tentativegScore.')';
				//	$verboseData .= "-- ignored score : ".$tentativegScore." > ".$gScore[$neighborNodeId]."\n";
				//	continue;
				//}
					
				$cameFrom[$neighborNodeId] = array($currentNodeId, $neighborNodeData[1]);
				
				$gScore[$neighborNodeId] = $gScore[$currentNodeId] + ($neighborNodeData[0]);
				$fScore[$neighborNodeId] = $gScore[$currentNodeId] + ($gScore[$neighborNodeId] * $factor * $factor) + (distance(
						$dbNode[$neighborNodeId]['location_lat'],
						$dbNode[$neighborNodeId]['location_lng'],
						$dbNode[$idNodeGoal]['location_lat'],
						$dbNode[$idNodeGoal]['location_lng'], 'K'
						) * $factor);
					
				$verboseData .= "  Final gScore : ".$gScore[$neighborNodeId]."\n";
				$verboseData .= "  Final fScore : ".$fScore[$neighborNodeId]." | factor: ".$factor."\n";
				$verboseData .= "\n";
				$verboseHtml .= '</td></tr>'.PHP_EOL;
			} // End foreach neighbor
			$verboseHtml .= '</tbody></table>'.PHP_EOL;
			$loopCount++;
			
			$verboseHtml .= "</div><!-- end step -->".PHP_EOL;
		}
	
		$timeEnd = microtime(true);
	
		$memoryPeak = memory_get_peak_usage();
		//dividing with 60 will give the execution time in minutes other wise seconds
		$execution_time = round(($timeEnd - $this->_timeStart),4);
	
		$benchmarkResult = "Execution time: ".$execution_time." seconds. Memory peak: {$memoryPeak} bytes.";
		$verboseData .= "\n{$benchmarkResult}\n";
	
		$verboseData .= "</pre>";
	
		$responseData = array(
						'routeways' => $routeWaysData,
						//'sequence' => $shortestPathSeq,
						'benchmark' => $benchmarkResult,
						//'routeinfo' => $publicRouteOut
				);
		
		if ($verbose) {
			$responseData['verbose'] = $verboseData;
			$responseData['loopstep'] = $verboseHtml;
		}
		$jsonResponse = array(
				'status' => 'ok',
				'data' => $responseData
		);
	
		return $jsonResponse;
	}
	
	private function _build_solution($finalRoute, $searchOpts, $dbNode, $dbEdge, $dbRoute, $edgeModel, &$shortestPathSeq, &$verboseData) {
		//--- Default values...
		$useAngkot = $useBrt = true;
		
		if (isset($searchOpts['angkot']))	$useAngkot = $searchOpts['angkot'];
		if (isset($searchOpts['brt']))		$useBrt = $searchOpts['brt'];
		
		$nodeCount = count($finalRoute);
		$counter = 1;
		$verboseData .= "------------------ Search finished -----\n";
		$verboseData .= " Route result:\n";
			
		//$publicRouteOut = "== ROUTE INFO ==\n";
		$routeDir = 0;
		$routeAlts = []; // Data list solusi transit
		$currentRoute = [];
		
		$edgeArrData = [];
		$edgeArrCounter = 0;
		
		$prevLoc = ['lat' => null, 'lng' => null];
		$prevNodeId = null;
		
		for ($i=$nodeCount-1; $i >= 0; $i--) {
			
			$edgeRouteData = null;
			$vCurrentEdge = $finalRoute[$i][1];
		
			$currentNodeData = $dbNode[$finalRoute[$i][0]];
			$verboseData .= " Iterate @".$currentNodeData['node_name']."-----------:\n";
						
			// Bukan node start?
			if ($vCurrentEdge) {
				$isShuttle = false;
				
				// Jalur shuttle terdiri dari lebih dari satu busur...
				if ($vCurrentEdge >= 10000) {
					$isShuttle = true;
					$edgeArr = $dbEdge[$vCurrentEdge]['id_edge'];
				} else {
					$edgeArr = [ [$vCurrentEdge, null] ];
				}
				
				$newEdgeDist = 0.0;
				$newEdgeSeq = [];
				foreach ($edgeArr as $currentIdEdge) {
					// Ambil informasi busur...
					$edgeData = $edgeModel->get_edge_by_id($currentIdEdge[0]);
					
					if ($edgeData) {
						//-- Polyline output
						$pointArr = mysql_to_latlng_coords($edgeData['polyline_data']);
					
						$routeDir = $currentIdEdge[1];
						if ($routeDir === null) {
							$routeDir = ($edgeData['id_node_from'] == $prevNodeId ? 1 : -1);
						}
						
						$prevNodeId = ($routeDir > 0 ? $edgeData['id_node_dest'] : $edgeData['id_node_from']);
						$currentNodeData = $dbNode[$prevNodeId];
						$nextLoc = array(
								'lat' => floatval($currentNodeData['location_lat']),
								'lng' => floatval($currentNodeData['location_lng'])
						);
						
						// Arah maju, searah dengan busur
						if ($routeDir > 0) {
							array_unshift($pointArr, $prevLoc);
							array_push($pointArr, $nextLoc);
						} else { // Mundur...
							$pointArr = array_reverse($pointArr, true);
							array_unshift($pointArr, $prevLoc);
							array_push($pointArr, $nextLoc);
						}
						
						$edgeArrData[$edgeArrCounter] = $pointArr;
						$edgeRouteData = array(
								'id_edge'	=> $currentIdEdge[0],
								'polyline'	=> encode_polyline($pointArr),
								'start_pos'	=> $prevLoc,
								'end_pos'	=> $nextLoc
						);
						
						$shortestPathSeq[$edgeArrCounter] = array(
								'id' => $currentNodeData['id_node'],
								'node_name' => $currentNodeData['node_name'],
								'node_type' => $currentNodeData['node_type'],
								'position' => $nextLoc,
								'edge_data' => $edgeRouteData
						);
						
						$prevLoc['lat'] = floatval($currentNodeData['location_lat']);
						$prevLoc['lng'] = floatval($currentNodeData['location_lng']);
						
						$newEdgeDist += $edgeData['distance'];
						$newEdgeSeq[] = $edgeArrCounter;
						
						$edgeArrCounter++;
						
					} // End if edgeData exist
					
				} // End foreach if next node is exist
				
				// Rute shuttle selalu sesuai dengan arah busur...
				if ($isShuttle) $routeDir = 1;
				
				$currentSolution = [];
				
				// Tidak dilalui angkot?
				if (count($dbEdge[$vCurrentEdge]['routes']) == 0) {
					$currentSolution[] = 0; // Jalan kaki
				} else {
					foreach ($dbEdge[$vCurrentEdge]['routes'] as $codePublicRoute => $itemRoute) {
						//-- Ambil trayek yang searah saja ...
						if (($routeDir == $itemRoute[0]) && !in_array($itemRoute[2], $currentSolution)) {
							if (!$useAngkot && ($itemRoute[1] == 1)) continue;
							if (!$useBrt && ($itemRoute[1] == 2)) continue;
							
							$currentSolution[] = $itemRoute[2];
							//$publicRouteOut .= $itemRoute['id_route'].", ";
						} // End if searah
				
					} // End foreach
				
					//-- Tidak ada angkot yang searah?
					if (empty($currentSolution)) {
						$currentSolution[] = 0; // Jalan kaki
					}
				}
				
				//$publicRouteOut .= "\n";
				
				// Inisiasi jika currentRoute belum ada isinya...
				if (count($currentRoute) == 0) {
					foreach ($currentSolution as $itemSolution) {
						$currentRoute[] = $itemSolution;
				
						$routeAlts[] = [[
								'id' => $itemSolution,
								'dist' => 0.0,
								'seq' => []
						]];
					}
				}
				
				//-- Ambil rute yang masih sejalur...
				$intersectRoute = array_intersect($currentRoute, $currentSolution);
				
				$newRouteSolution = [];
				
				//-- Untuk setiap trayek yang ditemukan melalui busur
				foreach ($currentSolution as $itemSolution) {
					$verboseData .= " -------- Iterate itemSolution: ".$itemSolution."\n";
					//-- Merupakan trayek baru, yang tadinya tidak sejalur..
					if (!in_array($itemSolution, $intersectRoute)) {
						//-- Diabaikan, kecuali sedang tidak ada angkot yang sejalur...
						if (empty($intersectRoute)) {
							$verboseData .= " Empty intersectRoute\n";
							//-- Clone setiap solusi...
							foreach ($routeAlts as $idxAlt => $itemAlt) {
								$newIdx = count($newRouteSolution);
				
								//-- Copy
								$newRouteSolution[$newIdx] = [];
								foreach ($itemAlt as $altRouteItem) {
									$edgeSeq = [];
									foreach ($altRouteItem['seq'] as $seqItem) $edgeSeq[] = $seqItem;
				
									$newRouteSolution[$newIdx][] = [
											'id' => $altRouteItem['id'],
											'dist' => $altRouteItem['dist'],
											'seq' => $edgeSeq
									];
								}
				
								//-- Append trayek baru...
								$tmpEdgeSeq = [];
								foreach ($newEdgeSeq as $itemSeq) $tmpEdgeSeq[] = $itemSeq;
								$newRouteSolution[$newIdx][] = [
										'id' => $itemSolution,
										'dist' => $newEdgeDist,
										'seq' => $tmpEdgeSeq
								];
							}
						} else {
							$verboseData .= " Ignored: ".$itemSolution."\n";
							//-- Abaikan solusi
						}
				
					} else {
						//-- Merupakan trayek yang masih sejalur, maka tambah jarak tempuh
						foreach ($routeAlts as $idxAlt => $itemAlt) {
							$lastIdx = count($itemAlt)-1;
							if ($routeAlts[$idxAlt][$lastIdx]['id'] == $itemSolution) {
								$routeAlts[$idxAlt][$lastIdx]['dist'] += $newEdgeDist;
								foreach ($newEdgeSeq as $itemSeq)
									$routeAlts[$idxAlt][$lastIdx]['seq'][] = $itemSeq;
							}
								
						} // End foreach alternatives
					}
				} // End foreach
				
				//-- Untuk setiap trayek lama yang 'miss'
				foreach ($currentRoute as $itemRoute) {
					if (!in_array($itemRoute, $intersectRoute)) {
						foreach ($routeAlts as $idxAlt => $itemAlt) {
							$lastIdx = count($itemAlt)-1;
								
							//-- Solusi yang berujung trayek miss, akan dihapus dari 'result'
							if ($routeAlts[$idxAlt][$lastIdx]['id'] == $itemRoute) {
								unset($routeAlts[$idxAlt]);
							} // End if
						} // End foreach alts
					} // End if
				} // End foreach
				
				//-- Append to routeAlt
				foreach ($newRouteSolution as $itemNewSolution) {
					$newIdx = count($routeAlts);
						
					$routeAlts[$newIdx] = [];
					foreach ($itemNewSolution as $altRouteItem) {
						$edgeSeq = [];
						foreach ($altRouteItem['seq'] as $seqItem) $edgeSeq[] = $seqItem;
				
						$routeAlts[$newIdx][] = [
								'id' => $altRouteItem['id'],
								'dist' => $altRouteItem['dist'],
								'seq' => $edgeSeq
						];
					} // End foreach itemSolution
				
				} // End foreach newRouteSolution
				
				//-- Update current route
				$currentRoute = [];
				foreach ($routeAlts as $altIdx => $itemAlt) {
					//-- Ambil trayek terakhir yang dinaiki..
					$idRoute = end($itemAlt)['id'];
					if (!in_array($idRoute, $currentRoute)) $currentRoute[] = $idRoute;
				}
			} else { // If is starting node
				$shortestPathSeq[$edgeArrCounter] = array(
						'id' => $currentNodeData['id_node'],
						'node_name' => $currentNodeData['node_name'],
						'node_type' => $currentNodeData['node_type'],
						'position' => $nextLoc,
						'edge_data' => $edgeRouteData
				);
				
				$prevLoc['lat'] = floatval($currentNodeData['location_lat']);
				$prevLoc['lng'] = floatval($currentNodeData['location_lng']);
				$prevNodeId = $currentNodeData['id_node'];
				
				$edgeArrCounter++;
			}
		
			$verboseData .= "   ".$counter.". ".$dbNode[$finalRoute[$i][0]]['node_name']."\n";
			$counter++; // Increment node counter
		} // End for (node counter)
			
		$verboseData .= "----------------------------------------\n";
		
		//---- Build transit information
		$routeWaysData = [];
		$publicRouteOut .= "\n-  ROUTE -----\n\n"; // . print_r($routeAlts, true);
		
		$altCounter = 1;
		foreach ($routeAlts as $itemAlt) {
			$publicRouteOut .= "o Cara ".$altCounter.":\n";
		
			$prevRouteId = null;
			
			$distTotal = 0.0;
			$walkDistTotal = 0.0;
			$feeTotal = 0;
			$stepsData = [];
			
			$nodeCounter = 1;
			$lastTransitNode = 0;
			foreach ($itemAlt as $transitItem) {
				$lastSeq = count($transitItem['seq']) - 1;
				
				$stepIcon = _base_url($this->_path_routeicon.'/'.$this->_def_routeicon.'?v='.APPVER);
				$stepType = 'UNKNOWN';
				$htmlText = '';
				
				$transitFee = 0;
				if ($transitItem['id'] == 0) { // Jalan kaki
					$stepType = 'WALK';
					$stepIcon = _base_url('/assets/images/walk-icon-200.png?v='.APPVER);
					
					$walkLengthLabel = $transitItem['dist'] . " km";
					if ($transitItem['dist'] < 1.0) {
						$walkLengthMeter = $transitItem['dist'] * 1000;
						$walkLengthLabel = $walkLengthMeter . " m";
					}
					
					if ($prevRouteId !== null) {
						//-- Jika sebelumnya sudah naik BRT, maka tidak perlu bayar lagi...
						if ($dbRoute[$prevRouteId]['vehicle_type'] == 2) {
							$htmlText = "Turun di <b>".$shortestPathSeq[$lastTransitNode]['node_name']."</b>, ".
								"kemudian jalan kaki sepanjang <b>".$walkLengthLabel."</b>";
						} else {
							$htmlText = "Jalan kaki sepanjang <b>".$walkLengthLabel."</b>";
						}
					} else {
						$htmlText = "Jalan kaki sepanjang <b>".$walkLengthLabel."</b>";
					}
					
					$publicRouteOut .= "-- Jalan kaki ".$walkLengthLabel;
					$walkDistTotal += $transitItem['dist'];
				} else {
					if (!empty($dbRoute[$transitItem['id']]['vehicle_icon'])) {
						$stepIcon = _base_url($this->_path_routeicon.'/'.
								$dbRoute[$transitItem['id']]['vehicle_icon']."?v=".APPVER);
					}
					
					$transitFee = 0;
					if ($dbRoute[$transitItem['id']]['vehicle_type'] == 2) {
						$stepType = 'SHUTTLEBUS';
						$transitFee = 3500; // BRT jauh dekat, Rp. 3500
						
						$htmlText = "Naik BRT ".$dbRoute[$transitItem['id']]['route_code'].
							" (<b>".$dbRoute[$transitItem['id']]['route_name']."</b>) #".$transitItem['id'].
							" dari <b>".$shortestPathSeq[$lastTransitNode]['node_name']."</b>".
							" sepanjang ".$transitItem['dist']." km. (Rp. ".$transitFee.")";
						
						if ($prevRouteId !== null) {
							//-- Jika sebelumnya sudah naik BRT, maka tidak perlu bayar lagi...
							if ($dbRoute[$prevRouteId]['vehicle_type'] == 2) {
								$transitFee = 0;
								
								//-- Masih di koridor yang sama?
								if ($dbRoute[$prevRouteId]['route_code'] == $dbRoute[$transitItem['id']]['route_code']) {
									$htmlText = "Tetap di armada BRT ".$dbRoute[$transitItem['id']]['route_code'].
										" (".$dbRoute[$prevRouteId]['route_name'].") ".
										" lanjutkan perjalanan sepanjang ".$transitItem['dist']." km.";
								} else {
									// Tidak bayar jika transit di shelter transit...
									if ($shortestPathSeq[$lastTransitNode]['node_type'] == 2) {
										$htmlText = sprintf("Transit di <b>%s</b>, pindah BRT %s (<b>%s</b>)",
												$shortestPathSeq[$lastTransitNode]['node_name'],
												$dbRoute[$transitItem['id']]['route_code'],
												$dbRoute[$transitItem['id']]['route_name']);
									} else {
										$transitFee = 3500;
										$htmlText = sprintf("Turun di <b>%s</b>, naik BRT %s (<b>%s</b>) (Rp. %d)",
												$shortestPathSeq[$lastTransitNode]['node_name'],
												$dbRoute[$transitItem['id']]['route_code'],
												$dbRoute[$transitItem['id']]['route_name'],
												$transitFee);
									}
									
									//$htmlText = "Transit di shelter ".$shortestPathSeq[$lastTransitNode]['node_name'].
									//	" pindah ke BRT ".$dbRoute[$transitItem['id']]['route_code'].
									//	" (".$dbRoute[$transitItem['id']]['route_name'].") #".$transitItem['id'].
									//	" sepanjang ".$transitItem['dist']." km.";
								}
							}
						}
						
					} else {
						$stepType = 'CITYTRANSPORT';
						$transitFee = 3000; // 8 km pertama Rp. 3000
						
						if ($transitItem['dist'] > 8.0) {
							// Pembulatan 500
							$transitFee += ceil((ceil($transitItem['dist'] - 8.0) * 150) / 500) * 500;
						}
						if ($transitFee > 6000) $transitFee = 6000; // Paling tinggi 6000
						
						$htmlText = "Naik angkot kode ".$dbRoute[$transitItem['id']]['route_code'].
							" (<b>".$dbRoute[$transitItem['id']]['route_name']."</b>) #".$transitItem['id'].
							" sepanjang ".$transitItem['dist']." km. (Rp. ".$transitFee.")";
					}
					
					$publicRouteOut .= "-- Naik angkot kode ".$dbRoute[$transitItem['id']]['route_code'].
					" (".$dbRoute[$transitItem['id']]['route_name'].") sepanjang ".$transitItem['dist'].
					" km. (Rp. ".$transitFee.").\n";
					
				}
				$feeTotal += $transitFee;
				$distTotal += floatval($transitItem['dist']);
		
				//-- Genereate edge polyline...
				$startLoc = null; $endLoc = null;
				$polyArr = [];
				
				if ($lastSeq >= 0) {
					$startLoc = $shortestPathSeq[$transitItem['seq'][0]]['edge_data']['start_pos'];
					$endLoc = $shortestPathSeq[$transitItem['seq'][$lastSeq]]['edge_data']['end_pos'];
					foreach ($transitItem['seq'] as $seqIdx => $edgeSeqItem) {
						$lastPolyNodeIdx = count($edgeArrData[$edgeSeqItem])-1;
						foreach ($edgeArrData[$edgeSeqItem] as $polyNodeIdx => $posItem) {
							// Node terakhir busur diappend hanya pada busur terakhir
							//  Skip jika bukan merupakan busur terakhir
							if (($polyNodeIdx == $lastPolyNodeIdx) && ($seqIdx != $lastSeq)) continue;
							
							$polyArr[] = ['lat' => $posItem['lat'], 'lng' => $posItem['lng']];
						}
					}
					
					$lastTransitNode = $transitItem['seq'][$lastSeq];
				}
				
				$stepsData[] = [
						'html_instruction' => $htmlText,
						'type' => $stepType,
						'icon' => $stepIcon,
						'start_location' => $startLoc,
						'end_location' => $endLoc,
						'polyline' => encode_polyline($polyArr),
						'distance' => floatval($transitItem['dist']),
						'cost' => intval($transitFee),
						//'debug' => [
						//	'seq' => $transitItem['seq']
						//]
				];
				
				
				//-- Update lasts
				$prevRouteId = $transitItem['id'];
				
				//-- Increment counters
				$nodeCounter++;
			}
			$publicRouteOut .= "Biaya: Rp. ".$feeTotal."\n\n";
			$altCounter++;
				
			$routeWaysData[] = [
					'est_length' => $distTotal,
					'walk_length' => $walkDistTotal,
					'est_cost' => $feeTotal,
					'steps' => $stepsData
			];
		}
		
		return $routeWaysData;
	}
}