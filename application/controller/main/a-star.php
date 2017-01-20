<?php
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

function do_astar_algorithm() {
	global $timeStart;
	
	//require(APP_PATH."/controller/main/data.php");
	$idNodeStart = $_POST['id_node_start'];
	$idNodeGoal = $_POST['id_node_end'];
	
	if (!isset($configVerbose)) $configVerbose = true;
	$shortestPathSeq = array();
	$verboseData = "";
	
	//--- Ambil seluruh data dan bangun node ketentanggaan
	require_once(APP_PATH."/model/node.php");
	require_once(APP_PATH."/model/edge.php");
	require_once(APP_PATH."/helper/geo-tools.php");
	require_once(APP_PATH."/helper/gmap-tools.php");
	
	$dbNode = get_nodes(-1);
	$dbEdge = get_edges();
	
	foreach ($dbEdge as $edgeItem) {
		$nodeFrom = $edgeItem['id_node_from'];
		$nodeDest = $edgeItem['id_node_dest'];
		
		$dbNode[$nodeFrom]['neighbors'][$nodeDest] = array($edgeItem['distance'], $edgeItem['id_edge']);
		if ($edgeItem['reversible'] == 1) {
			$dbNode[$nodeDest]['neighbors'][$nodeFrom] = array($edgeItem['distance'], $edgeItem['id_edge']);
		}
	}
	
	//--- Validasi input
	if (!key_exists($idNodeStart, $dbNode) || !key_exists($idNodeGoal, $dbNode)) {
		return generate_error("Invalid input!");
	}
	
	
	// Loop maximal
	// Kita gunakan untuk trap apabila terjadi infinite loop...
	define('MAX_LOOP', 100);
	
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
					$edgeData = get_edge_by_id($currentIdEdge);
					if ($edgeData) {
						$pointArr = mysql_to_latlng_coords($edgeData['polyline_data']);
						
						if ($edgeData['id_node_dest'] == $currentNodeData['id_node']) { // Arah maju
							array_unshift($pointArr, $prevLoc);
							array_push($pointArr, $nextLoc);
						} else { // Mundur...
							array_reverse($pointArr);
							array_unshift($pointArr, $nextLoc);
							array_push($pointArr, $prevLoc);
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
		//$verboseData .= "Why? :\n";
		//print_r($fScore);
		//$verboseData .= "\n";
		//print_r($gScore);
		//$verboseData .= "\n";
		foreach ($dbNode[$currentNodeId]['neighbors'] as $neighborNodeId => $neighborNodeData) {
			//$neighborNodeId = $neighborNode['id_node'];
			$verboseData .= " o Neighbor node : ".$neighborNodeId." (".$dbNode[$neighborNodeId]['node_name']."), dist: ".$neighborNodeData[0]."\n";
			if (in_array($neighborNodeId, $visitedNodes)) {
				$verboseData .= "-- ignored\n";
				continue;
			}
			
			if (!isset($gScore[$neighborNodeId])) $gScore[$neighborNodeId] = 100.0;
			$tentativegScore = $gScore[$currentNodeId] + $neighborNodeData[0];
			
			if (!key_exists($neighborNodeId, $openNodes)) {
				$openNodes[$neighborNodeId] = 1;
			} else if ($tentativegScore >= $gScore[$neighborNodeId]) {
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
		} // End foreach neighbor
		$loopCount++;
	}
	
	$timeEnd = microtime(true);
	
	$memoryPeak = memory_get_peak_usage();
	//dividing with 60 will give the execution time in minutes other wise seconds
	$execution_time = round(($timeEnd - $timeStart),4);
	
	$benchmarkResult = "Execution time: ".$execution_time." seconds. Memory peak: {$memoryPeak} bytes.";
	$verboseData .= "\n{$benchmarkResult}\n";
	
	$verboseData .= "</pre>";
	
	$jsonResponse = array(
		'status' => 'ok',
		'data' => array(
			//'verbose' => $verboseData,
			'sequence' => $shortestPathSeq,
			'benchmark' => $benchmarkResult
		)
	);
	
	return $jsonResponse;
}
	