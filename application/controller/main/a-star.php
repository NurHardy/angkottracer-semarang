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
	
	$dbNode = get_nodes(-1);
	$dbEdge = get_edges();
	
	foreach ($dbEdge as $edgeItem) {
		$nodeFrom = $edgeItem['id_node_from'];
		$nodeDest = $edgeItem['id_node_dest'];
		
		$dbNode[$nodeFrom]['neighbors'][$nodeDest] = $edgeItem['distance'];
		if ($edgeItem['reversible'] == 1) {
			$dbNode[$nodeDest]['neighbors'][$nodeFrom] = $edgeItem['distance'];
		}
	}
	
	//--- Validasi input
	if (!key_exists($idNodeStart, $dbNode) || !key_exists($idNodeGoal, $dbNode)) {
		echo ("Invalid input!");
		return;
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
			$finalRoute = array($idNodeGoal);
			
			$fromNode = $idNodeGoal;
			while ($fromNode != $idNodeStart) {
				$fromNode = $cameFrom[$fromNode];
				$finalRoute[] = $fromNode;
			}
			$nodeCount = count($finalRoute);
			$counter = 1;
			$verboseData .= "------------------ Search finished -----\n";
			$verboseData .= " Route result:\n";
			for ($i=$nodeCount-1; $i >= 0; $i--) {
				$currentNodeData = $dbNode[$finalRoute[$i]];
				$shortestPathSeq[] = array(
						'id' => $currentNodeData['id_node'],
						'position' => array(
								'lat' => floatval($currentNodeData['location_lat']),
								'lng' => floatval($currentNodeData['location_lng'])
						)
				);
				$verboseData .= "   ".$counter.". ".$dbNode[$finalRoute[$i]]['node_name']."\n";
				$counter++;
			}
			$shortestPathSeq[] = $dbNode[$idNodeGoal];
			
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
		foreach ($dbNode[$currentNodeId]['neighbors'] as $neighborNodeId => $neighborNodeDistance) {
			//$neighborNodeId = $neighborNode['id_node'];
			$verboseData .= " o Neighbor node : ".$neighborNodeId." (".$dbNode[$neighborNodeId]['node_name']."), dist: ".$neighborNodeDistance."\n";
			if (in_array($neighborNodeId, $visitedNodes)) {
				$verboseData .= "-- ignored\n";
				continue;
			}
			
			if (!isset($gScore[$neighborNodeId])) $gScore[$neighborNodeId] = 100.0;
			$tentativegScore = $gScore[$currentNodeId] + $neighborNodeDistance;
			
			if (!key_exists($neighborNodeId, $openNodes)) {
				$openNodes[$neighborNodeId] = 1;
			} else if ($tentativegScore >= $gScore[$neighborNodeId]) {
				$verboseData .= "-- ignored score : ".$tentativegScore."\n";
				continue;
			}
			
			$cameFrom[$neighborNodeId] = $currentNodeId;
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
	$verboseData .= "\nExecution time: ".$execution_time." seconds. Memory peak: {$memoryPeak} bytes.\n";
	
	$verboseData .= "</pre>";
	
	$jsonResponse = array(
		'status' => 'ok',
		'data' => array(
			//'verbose' => $verboseData,
			'sequence' => $shortestPathSeq
		)
	);
	
	