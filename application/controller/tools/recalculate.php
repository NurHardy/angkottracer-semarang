<?php

	require_once(APP_PATH."/model/node.php");
	require_once(APP_PATH."/model/edge.php");
	require_once(APP_PATH."/helper/geo-tools.php");
	
	$dbNode = get_nodes(-1);
	$dbEdge = get_edges();
	
	echo "<pre>";
	foreach ($dbEdge as $edgeItem) {
		$nodeFrom = $edgeItem['id_node_from'];
		$nodeDest = $edgeItem['id_node_dest'];
	
		echo "-- Processing edge #".$edgeItem['id_edge']."\n";
		$newDistance = distance(
				$dbNode[$nodeFrom]['location_lat'],
				$dbNode[$nodeFrom]['location_lng'],
				$dbNode[$nodeDest]['location_lat'],
				$dbNode[$nodeDest]['location_lng'], 'K'
		);
		echo "   Distance: ".$newDistance."\n";
		
		$updateEdge = array('distance' => $newDistance);
		$return = save_edge($updateEdge, $edgeItem['id_edge']);
		echo "   Update return: ".$return."\n";
		
		echo "\n";
	}
	echo "</pre>";