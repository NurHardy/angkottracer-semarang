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
	
	public function astar($request, $response, $args) {
		$idNodeFrom = intval($args['from']);
		$idNodeDest = intval($args['dest']);
		
		$isDebug = $request->getQueryParam('debug', false);
		$isDebug = !empty($isDebug);
		
		$this->_data = $this->_do_astar_algorithm($idNodeFrom, $idNodeDest, $isDebug);
		
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
	
	private function _do_astar_algorithm($idNodeStart, $idNodeGoal, $verbose = false) {
		$timeStart = $this->_timeStart;
	
		//require(APP_PATH."/controller/main/data.php");
		//$idNodeStart = $_POST['id_node_start'];
		//$idNodeGoal = $_POST['id_node_end'];
	
		$currentDate = date("Y-m-d H:i:s");
		
		if (!isset($configVerbose)) $configVerbose = true;
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
		$visitedNodes = array();
		$openNodes = array($idNodeStart => 1);
	
		$cameFrom = array();
	
		$gScore = array();
		$gScore[$idNodeStart] = 0;
	
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
				if ($fScore[$fIndex] < $fScoreCheck) {
					$fScoreCheck = $fScore[$fIndex];
					$fScoreIndexCheck = $fIndex;
				}
			}
	
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
					
				$nodeCount = count($finalRoute);
				$counter = 1;
				$verboseData .= "------------------ Search finished -----\n";
				$verboseData .= " Route result:\n";
					
				//$publicRouteOut = "== ROUTE INFO ==\n";
				$routeDir = 0;
				$routeAlts = []; // Data list solusi transit
				$currentRoute = [];
					
				$prevLoc = ['lat' => null, 'lng' => null];
				for ($i=$nodeCount-1; $i >= 0; $i--) {
					$edgeRouteData = null;
					$currentIdEdge = $finalRoute[$i][1];
	
					$currentNodeData = $dbNode[$finalRoute[$i][0]];
					$nextLoc = array(
							'lat' => floatval($currentNodeData['location_lat']),
							'lng' => floatval($currentNodeData['location_lng'])
					);
					if ($currentIdEdge) {
						$vCurrentEdge = $currentIdEdge;
						if ($currentIdEdge > 10000) {
							$currentIdEdge = $dbEdge[$currentIdEdge]['id_edge'];
						}
						$edgeData = $edgeModel->get_edge_by_id($currentIdEdge);
						
						if ($edgeData) {
							$pointArr = mysql_to_latlng_coords($edgeData['polyline_data']);
	
							if ($edgeData['id_node_dest'] == $currentNodeData['id_node']) { // Arah maju
								$routeDir = 1;
								array_unshift($pointArr, $prevLoc);
								array_push($pointArr, $nextLoc);
							} else { // Mundur...
								$routeDir = -1;
								array_reverse($pointArr);
								array_unshift($pointArr, $nextLoc);
								array_push($pointArr, $prevLoc);
							}
	
							//$publicRouteOut .= "- ". $edgeData['id_edge'] . " >> ";
							
							//-- Edge has only one route?
							/*
							if (!empty($dbEdge[$vCurrentEdge]['route'])) {
								$routeList = [ [
									'id_route' => $dbEdge[$vCurrentEdge]['route'],
									'id_edge' => $currentIdEdge,
									'direction' => $routeDir,
									'order' => 0,
								] ];
							} else {
								//-- Ambil trayek yang melalui busur... (yang angkot saja)
								$routeList = $routeModel->get_edge_route($edgeData['id_edge']);								
							} */
							
							$currentSolution = [];
							
							// Tidak dilalui angkot?
							if (count($dbEdge[$vCurrentEdge]['routes']) == 0) {
								$currentSolution[] = 0; // Jalan kaki
							} else {
								foreach ($dbEdge[$vCurrentEdge]['routes'] as $idRoute => $itemRouteDir) {
									//-- Ambil trayek yang searah saja...
									if ($routeDir == $itemRouteDir) {
										$currentSolution[] = $idRoute;
										//$publicRouteOut .= $itemRoute['id_route'].", ";
									}
								}
								
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
											'dist' => 0.0
									]];
								}
							}
	
							//-- Ambil trayek yang masih sejalur...
							$intersectRoute = array_intersect($currentRoute, $currentSolution);
	
							$newRouteSolution = [];
							//-- Untuk setiap trayek baru yang ditemukan
							foreach ($currentSolution as $itemSolution) {
								//-- Merupakan trayek baru, yang tidak sejalur..
								if (!in_array($itemSolution, $intersectRoute)) {
									//-- Clone setiap solusi...
									foreach ($routeAlts as $idxAlt => $itemAlt) {
										$newIdx = count($newRouteSolution);
											
										//-- Copy
										$newRouteSolution[$newIdx] = [];
										foreach ($itemAlt as $altRouteItem) {
											$newRouteSolution[$newIdx][] = [
													'id' => $altRouteItem['id'],
													'dist' => $altRouteItem['dist']
											];
										}
											
										//-- Append trayek baru...
										$newRouteSolution[$newIdx][] = [
												'id' => $itemSolution,
												'dist' => $edgeData['distance']
										];
									}
								} else {
									//-- Ada di intersect..., tambah jarak tempuh
									foreach ($routeAlts as $idxAlt => $itemAlt) {
										$lastIdx = count($itemAlt)-1;
										$routeAlts[$idxAlt][$lastIdx]['dist'] += $edgeData['distance'];
									}
								}
							} // End foreach
	
							//-- Untuk setiap trayek lama yang 'miss'
							foreach ($currentRoute as $itemRoute) {
								if (!in_array($itemRoute, $intersectRoute)) {
									foreach ($routeAlts as $idxAlt => $itemAlt) {
										$lastIdx = count($itemAlt)-1;
											
										//-- Solusi yang berujung trayek miss
										if ($routeAlts[$idxAlt][$lastIdx]['id'] == $itemRoute) {
											foreach ($intersectRoute as $itemIntersect) {
												$newIdx = count($newRouteSolution);
	
												//-- Copy
												$newRouteSolution[$newIdx] = [];
												foreach ($itemAlt as $altRouteItem) {
													$newRouteSolution[$newIdx][] = [
															'id' => $altRouteItem['id'],
															'dist' => $altRouteItem['dist']
													];
												}
	
												//-- Append trayek baru...
												$newRouteSolution[$newIdx][] = [
														'id' => $itemIntersect,
														'dist' => $edgeData['distance']
												];
											} // End foreach
	
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
									$routeAlts[$newIdx][] = [
											'id' => $altRouteItem['id'],
											'dist' => $altRouteItem['dist']
									];
								}
							}
							//-- Update current route
							$currentRoute = [];
							foreach ($currentSolution as $itemSolution) {
								$currentRoute[] = $itemSolution;
							}
	
							$edgeRouteData = array(
									'id_edge' => $currentIdEdge,
									'polyline' => encode_polyline($pointArr)
							);
						}
					}
	
					$shortestPathSeq[] = array(
							'id' => $currentNodeData['id_node'],
							'position' => $nextLoc,
							'edge_data' => $edgeRouteData
					);
	
					$prevLoc['lat'] = floatval($currentNodeData['location_lat']);
					$prevLoc['lng'] = floatval($currentNodeData['location_lng']);
	
					$verboseData .= "   ".$counter.". ".$dbNode[$finalRoute[$i][0]]['node_name']."\n";
					$counter++;
				}
					
				$verboseData .= "----------------------------------------\n";
				break;
			}
	
			unset($openNodes[$currentNodeId]);
			$visitedNodes[] = $currentNodeId;
	
			if (!isset($gScore[$currentNodeId])) $gScore[$currentNodeId] = 100.0;
			//$neighborNodes = get_neighbor_edges($current, false);
			$verboseData .= "------------------ Current Node: ".$currentNodeId." (".$dbNode[$currentNodeId]['node_name'].")-----\n";
			$verboseHtml .= '<div id="searchresult_step_'.$loopCount.'" data-idnode="'.$currentNodeId.'" class="searchresult_step">';
			$verboseHtml .= '<p>Current node: #'.$currentNodeId.' ('.htmlspecialchars($dbNode[$currentNodeId]['node_name']).')</p>';
			//$verboseData .= "Why? :\n";
			//print_r($fScore);
			//$verboseData .= "\n";
			//print_r($gScore);
			//$verboseData .= "\n";
			$verboseHtml .= '<table class="table table-condensed"><thead><tr><th>#id</th><th>Name</th><th>Distance</th></tr></thead><tbody>';
			foreach ($dbNode[$currentNodeId]['neighbors'] as $neighborNodeId => $neighborNodeData) {
				$verboseHtml .= '<tr><td>#'.$neighborNodeId.'</td><td>'.htmlspecialchars($dbNode[$neighborNodeId]['node_name']).
					'</td><td>'.$neighborNodeData[0];
				//$neighborNodeId = $neighborNode['id_node'];
				$verboseData .= " o Neighbor node : ".$neighborNodeId." (".$dbNode[$neighborNodeId]['node_name']."), dist: ".$neighborNodeData[0]."\n";
				if (in_array($neighborNodeId, $visitedNodes)) {
					$verboseData .= "-- ignored\n";
					$verboseHtml .= ' (ignored)';
					continue;
				}
				
				if (!isset($gScore[$neighborNodeId])) $gScore[$neighborNodeId] = 100.0;
				$tentativegScore = $gScore[$currentNodeId] + $neighborNodeData[0];
					
				if (!key_exists($neighborNodeId, $openNodes)) {
					$openNodes[$neighborNodeId] = 1;
				} else if ($tentativegScore >= $gScore[$neighborNodeId]) {
					$verboseHtml .= ' (ignored score: '.$tentativegScore.')';
					$verboseData .= "-- ignored score : ".$tentativegScore."\n";
					continue;
				}
					
				$cameFrom[$neighborNodeId] = array($currentNodeId, $neighborNodeData[1]);
				$gScore[$neighborNodeId] = $tentativegScore;
				$fScore[$neighborNodeId] = $gScore[$neighborNodeId] + distance(
						$dbNode[$neighborNodeId]['location_lat'],
						$dbNode[$neighborNodeId]['location_lng'],
						$dbNode[$idNodeGoal]['location_lat'],
						$dbNode[$idNodeGoal]['location_lng'], 'K'
						);
					
				$verboseData .= "\n";
				$verboseHtml .= '</td></tr>'.PHP_EOL;
			} // End foreach neighbor
			$verboseHtml .= '</tbody></table>'.PHP_EOL;
			$loopCount++;
			
			$verboseHtml .= "</div><!-- end step -->".PHP_EOL;
		}
	
		//---- Build transit information
		$publicRouteOut .= "\n-  ROUTE -----\n\n"; // . print_r($routeAlts, true);
	
		$altCounter = 1;
		foreach ($routeAlts as $itemAlt) {
			$publicRouteOut .= "o Cara ".$altCounter.":\n";
	
			$feeTotal = 0;
			foreach ($itemAlt as $transitItem) {
				$transitFee = 0;
				if ($transitItem['id'] == 0) {
					$publicRouteOut .= "-- Jalan kaki ".$transitItem['dist']." km.\n";
				} else {
					$transitFee = 1500 + (ceil($transitItem['dist']) * 500);
					if ($transitFee > 4500) $transitFee = 4500;
	
					$publicRouteOut .= "-- Naik angkot kode ".$dbRoute[$transitItem['id']]['route_code'].
					" (".$dbRoute[$transitItem['id']]['route_name'].") sepanjang ".$transitItem['dist'].
					" km. (Rp. ".$transitFee.").\n";
				}
				$feeTotal += $transitFee;
			}
			$publicRouteOut .= "Biaya: Rp. ".$feeTotal."\n\n";
			$altCounter++;
		}
	
		$timeEnd = microtime(true);
	
		$memoryPeak = memory_get_peak_usage();
		//dividing with 60 will give the execution time in minutes other wise seconds
		$execution_time = round(($timeEnd - $this->_timeStart),4);
	
		$benchmarkResult = "Execution time: ".$execution_time." seconds. Memory peak: {$memoryPeak} bytes.";
		$verboseData .= "\n{$benchmarkResult}\n";
	
		$verboseData .= "</pre>";
	
		$responseData = array(
						'sequence' => $shortestPathSeq,
						'benchmark' => $benchmarkResult,
						'routeinfo' => $publicRouteOut
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
	
}