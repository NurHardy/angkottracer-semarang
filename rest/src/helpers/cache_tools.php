<?php

/**
 * Build cache file untuk graf.
 * @param NodeModel $nodeModel
 * @param EdgeModel $edgeModel
 * @param RouteModel $routeModel
 * @return boolean TRUE jika sukses, FALSE jika gagal.
 */
function cache_build($nodeModel, $edgeModel, $routeModel) {
	$BRT_DIST_FACTOR = 0.5;
	
	$dbNode = $nodeModel->get_nodes(-1);
	$dbEdge = $edgeModel->get_edges(false, true);
	$dbRoute = $routeModel->get_routes();

	foreach ($dbEdge as $edgeKey => $edgeItem) {
		// We do not need the polyline field...
		unset($dbEdge[$edgeKey]['polyline']);

		// Init values
		$dbEdge[$edgeKey]['routes'] = [];
		
		$nodeFrom = $edgeItem['id_node_from'];
		$nodeDest = $edgeItem['id_node_dest'];

		$dbNode[$nodeFrom]['neighbors'][$nodeDest] = array(floatval($edgeItem['distance']), intval($edgeItem['id_edge']));
		if ($edgeItem['reversible'] == 1) {
			$dbNode[$nodeDest]['neighbors'][$nodeFrom] = array(floatval($edgeItem['distance']), intval($edgeItem['id_edge']));
		}
		
	}

	//-- Init dbNode data
	foreach ($dbNode as $nodeKey => $nodeItem) {
		// We do not need the location field...
		unset($dbNode[$nodeKey]['location']);
		$dbNode[$nodeKey]['shuttle_buses'] = ['depart' => [], 'arrive' => []];
	}
	
	$dbShuttleSequence = [];

	$newNodeId = 10000;
	$newEdgeId = 10000;

	//-- Build route edges
	$idNodeFrom = 0; $idNodeDest = 0;
	foreach ($dbRoute as $routeKey => $routeItem) {
		$edgeSeq = $routeModel->get_route_edges($routeItem['id_route'], true);

		//-- Skip if route has no edge
		if (empty($edgeSeq)) continue;

		$lastIdNode = null;
		$lastNewIdNode = null;

		$nextNode = 0;
		// Shuttle bus...
		if ($routeItem['vehicle_type'] == 2) {
			$currentShuttleSeq = [];
			$currentShuttleLen = 0.0;
			
			$startShelter = null;
			foreach ($edgeSeq as $routeEdgeItem) {
				if ($routeEdgeItem['direction'] > 0) {
					$idNodeFrom = $routeEdgeItem['id_node_from'];
					$idNodeDest = $routeEdgeItem['id_node_dest'];
				} else {
					$idNodeFrom = $routeEdgeItem['id_node_dest'];
					$idNodeDest = $routeEdgeItem['id_node_from'];
				}

				if ($lastIdNode == null) {
					$lastIdNode = $lastNewIdNode = $idNodeFrom;
					$startShelter = $idNodeFrom;
				}
				
				// Buat busur shuttle yang menghubungkan antar dua shelter BRT...
				$currentIdEdge = [ $routeEdgeItem['id_edge'], intval($routeEdgeItem['direction']) ];
				
				//-- Rekam rute...
				$currentShuttleSeq[] = $currentIdEdge;
				$currentShuttleLen += floatval($routeEdgeItem['distance']);

				//-- Node tujuan adalah Shelter BRT?
				if ($dbNode[$idNodeDest]['node_type'] == 1) {
					$nextNode = $dbNode[$idNodeDest]['id_node'];
					$dbNode[$idNodeDest]['shuttle_buses']['arrive'][] = $routeItem['id_route'];

					//-- Cek, apakah rute antar shelter sudah dibuat?
					$routeKey = sprintf("%d-%d", $startShelter, $idNodeDest);
					$tmpIdEdge = null;
					if (key_exists($routeKey, $dbShuttleSequence)) {
						// Pastikan rute yang dilewati bus adalah sama...
						foreach ($dbShuttleSequence[$routeKey] as $idNewEdge => $shuttleSeqArr) {
							if ($currentShuttleSeq == $shuttleSeqArr) {
								$tmpIdEdge = $idNewEdge;
								break;
							}
						}
						
						// Jika ternyata tidak ada yang sama...
						if ($tmpIdEdge == null) {
							// Copy edge sequence...
							$dbShuttleSequence[$routeKey][$newEdgeId] = [];
							foreach ($currentShuttleSeq as $idEdge) $dbShuttleSequence[$routeKey][$newEdgeId][] = $idEdge;
						}
					} else {
						// Buat setting baru...
						$dbShuttleSequence[$routeKey] = [ $newEdgeId => $currentShuttleSeq ];
					}
					
					//-- Rute shuttle belum dibuat, maka buat edge baru...
					if ($tmpIdEdge == null) {
						//-- Buat edge baru berdasar jalan yang dilewati sejak shelter terakhir...
						$tmpSeq = [];
						foreach ($currentShuttleSeq as $idEdge) $tmpSeq[] = $idEdge;
						
						$dbEdge[$newEdgeId] = array(
								'id_edge' => $tmpSeq,
								'id_node_from' => $startShelter,
								'id_node_dest' => $idNodeDest,
								'routes' => [
									$routeItem['id_route'] => [1, $routeItem['vehicle_type']]
								] // Only one route
						);
						
						//-- Shelter berangkat
						$dbNode[$startShelter]['shuttle_buses']['depart'][] = $routeItem['id_route'];
						$dbNode[$startShelter]['neighbors'][$idNodeDest] =
							[$currentShuttleLen * $BRT_DIST_FACTOR, $newEdgeId];
						
						$newEdgeId++;
						
						//-- Now, start shelter is the old destination shelter
						$startShelter = $idNodeDest;
						$currentShuttleSeq = [];
						$currentShuttleLen = 0.0;
					} else {
						// Tambahkan informasi trayek ke dalam rute shuttle
						$dbEdge[$tmpIdEdge]['routes'][$routeItem['id_route']] = [1, $routeItem['vehicle_type']];
					}
					
				} else {
					//-- Clone node
					/*
					$newNodeId++;
					$dbNode[$newNodeId] = array(
							'id_node' => $dbNode[$idNodeDest]['id_node'],
							'node_name' => $dbNode[$idNodeDest]['node_name'],
							'location_lng' => $dbNode[$idNodeDest]['location_lng'],
							'location_lat' => $dbNode[$idNodeDest]['location_lat'],
							'node_type' => $dbNode[$idNodeDest]['node_type'],
							'date_created' => $currentDate,
							'neighbors' => []
					);*/

					$nextNode = $newNodeId;
				}

				//-- Update last node variable
				$lastNewIdNode = $nextNode;
				$lastIdNode = $idNodeDest;

			} // End foreach routeEdge
		} else { // Angkot biasa
			foreach ($edgeSeq as $routeEdgeItem) {
				if (isset($dbEdge[$routeEdgeItem['id_edge']])) {
					$dbEdge[$routeEdgeItem['id_edge']]['routes'][$routeItem['id_route']] =
						[$routeEdgeItem['direction'], $routeItem['vehicle_type']];
				}
			}
			
		} // End if

	} // End foreach Route


	//--- Write cache...
	$dbEdgeStringify = json_encode($dbEdge, true);
	file_put_contents(SRCPATH."/cache/dbedge.json", $dbEdgeStringify);
	$dbNodeStringify = json_encode($dbNode, true);
	file_put_contents(SRCPATH."/cache/dbnode.json", $dbNodeStringify);
	
	$dbShuttleStringify = json_encode($dbShuttleSequence, true);
	file_put_contents(SRCPATH."/cache/dbshuttle.json", $dbShuttleStringify);

	return true;
}