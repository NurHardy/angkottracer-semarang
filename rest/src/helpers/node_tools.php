<?php

/**
 * Proses simpan ujung busur
 *
 * @param NodeModel $nodeModel
 * @param array $polyLineData Array of lat, lng
 * @param int $idNodeFrom ID node asal
 * @param int $idNodeDest ID node tujuan
 * @return boolean TRUE jika berhasil, FALSE jika gagal
 */
function edge_save_ends($nodeModel, $polyLineData, $idNodeFrom, $idNodeDest) {
	$nodeDataQuery = array();

	$lastIdx = count($polyLineData)-1;

	$nodeDataQuery['location'] = "GeomFromText('".latlng_coord_to_mysql($polyLineData[$lastIdx])."')";
	if (!$nodeModel->save_node($nodeDataQuery, $idNodeDest)) {
		return false;
	}
		
	$nodeDataQuery['location'] = "GeomFromText('".latlng_coord_to_mysql($polyLineData[0])."')";
	if (!$nodeModel->save_node($nodeDataQuery, $idNodeFrom)) {
		return false;
	}

	return true;
}

/**
 * Periksa trayek terhadap arah busur baru.
 *
 * @param RouteModel $routeModel Objek model trayek
 * @param array $edgeData Row data busur lama
 * @param int $newIdNodeFrom ID node awal baru
 * @param int $newIdNodeDest ID node akhir baru
 * @param string $errorMessage [OUT] Pesan kesalahan jika ada kesalahan.
 * @return NULL|string Kembali NULL jika perubahan OK, atau string error jika ada warning
 */
function edge_check_routes($routeModel, $edgeData, $newIdNodeFrom, $newIdNodeDest, &$errorMessage) {
	$errorMessage = null;
	
	$routeData = $routeModel->get_edge_route($edgeData['id_edge']);

	if (!empty($routeData)) {
		//-- Check new ends
		if (($newIdNodeFrom != $edgeData['id_node_from']) && ($newIdNodeFrom != $edgeData['id_node_dest'])) {
			$errorMessage = "Tidak dapat mengalihkan ujung busur. Mohon cek trayek yang melewati busur.";
		} else if (($newIdNodeDest != $edgeData['id_node_from']) && ($newIdNodeDest != $edgeData['id_node_dest'])) {
			$errorMessage = "Tidak dapat mengalihkan ujung busur. Mohon cek trayek yang melewati busur.";
		}
		
		$isBeingReversed = ($newIdNodeFrom != $edgeData['id_node_from']);
		
		if ($isBeingReversed) {
			$errorMessage = "Tidak dapat membalikkan arah busur. Mohon cek trayek yang melewati busur.";
		}
	}

	//-- OK
	return $routeData;
}

/**
 * Simpan dan pecah edge menjadi 2 edge.
 * Jangan lupa load model <code>node_model</code>, helper <code>geo-tools</code>
 * 
 * @param array $handles Handle. 0: mysqli object, 1: node model, 2: edge model
 * @param array $oldEdgeData Data busur lama. Key sesuai database
 * @param array $newEdgeData Data busur baru. Key: edge_name, id_node_form, id_node_dest, reversible
 * @param array $edgePoints Array of points (lat: , lng: ). Vertex ujung merupakan
 * 	node dan akan diabaikan...
 * @param int $vertexIndex Index vertex untuk dipecah.
 * @param int|null $idEdge ID edge lama. NULL jika edge pecahan bagian pertama
 * 	disimpan dalam record baru.
 * @param string $errorMsg [OUT] Pesan error jika proses gagal
 * @param string $errorCode [OUT] Kode HTTP respons proses 200, 400, dsb.
 * @param array $breakedPolylines [OUT] Array of polyline, index 0 dan 1
 * @param bool $replaceOld Replace edge lama? Default: TRUE
 * @return NULL|int Kembali ID node baru jika proses berhasil, atau FALSE jika gagal.
 */
function save_and_break_edge($handles, $oldEdgeData, $newEdgeData, $edgePoints, $vertexIndex, $idEdge,
		&$errorMsg, &$errorCode, &$breakedPolylines, $replaceOld = true) {
	
	$mysqli = $handles[0];
	$nodeModel = $handles[1];
	$edgeModel = $handles[2];
	$routeModel = $handles[3];
	
	//-- Get edge info
	//$oldEdgeData = $edgeModel->get_edge_by_id($idEdge);
	
	if (!$oldEdgeData) {
		$errorMsg = "Specified edge data is not found.";
		return null;
	}
	
	//-- Periksa trayek yang melalui busur
	$routeData = edge_check_routes($routeModel, $oldEdgeData, $newEdgeData['id_node_from'], $newEdgeData['id_node_dest'], $errorMsg);
	if ($errorMsg) {
		$errorCode = HTTPSTATUS_BADREQUEST;
		return null;
	}
	
	//-- Update posisi node ujung...
	$procResult = edge_save_ends($nodeModel, $edgePoints, $newEdgeData['id_node_from'], $newEdgeData['id_node_dest']);
	if (!$procResult) {
		$errorCode = HTTPSTATUS_INTERNALERROR;
		$errorMsg = ("Query failed.");
		return null;
	}
	
	/*
	 * A - x - B
	 * Jadi :	A - x => disimpan pada edge lama (jika idEdge diset)
	 * 			x - B => disimpan pada edge baru
	 */
	$errorMsg = null;
	$isReversible = 1; // Default
	$currentDate = date('Y-m-d H:i:s');
	$vertexTotal = count($edgePoints)-1;
	
	$isReversible = ($newEdgeData['reversible'] == 1);
	
	//-- Simpan node baru
	$newNodeName = (empty($newEdgeData['edge_name']) ? '' : "Node ".$newEdgeData['edge_name']);
	$nodeData = array(
			'node_name' => _db_to_query($newNodeName, $mysqli),
			'location' => db_geom_from_text(latlng_point_to_mysql($edgePoints[$vertexIndex]['lng'], $edgePoints[$vertexIndex]['lat'])),
			'date_created' => "'".$currentDate."'",
			'id_creator' => 0,
			'creator' => "'system'"
	);
	$newIdNode = $nodeModel->save_node($nodeData);
	
	if ($newIdNode) {
		$breakedPolylines = array();
		
		$polylineData1 = [];
		
		//-- Ambil edge bagian pertama
		$polyDistance = 0.0; // Init
		
		$idxCounter = 0;
		for ($idxCounter = 1; $idxCounter <= $vertexIndex; $idxCounter++) {
			if ($idxCounter != $vertexIndex) $polylineData1[] = $edgePoints[$idxCounter];
			$polyDistance += node_distance($edgePoints[$idxCounter-1], $edgePoints[$idxCounter], 'K');
		}
		
		$edgeData1 = array(
				'id_node_from' => $newEdgeData['id_node_from'],
				'id_node_dest' => $newIdNode,
				'polyline' => db_geom_from_text(latlng_coords_to_mysql($polylineData1)),
				'distance' => $polyDistance,
				'date_created' => "'".$currentDate."'",
				'id_creator' => 0,
				'creator' => "'system'",
				'reversible' => ($isReversible ? 1 : 0)
		);
		
		$newEdgeName = $newEdgeData['edge_name'];
		$newIdEdge = null;
		
		if ($replaceOld) {
			$newIdEdge = $idEdge;
			$edgeData1['edge_name'] = _db_to_query($newEdgeData['edge_name'], $mysqli);
		} else {
			$edgeData1['edge_name'] = _db_to_query($newEdgeData['edge_name']." #2", $mysqli);
		}
		
		$newIdEdge = $edgeModel->save_edge($edgeData1, $newIdEdge);
		if (!$newIdEdge) {
			$errorCode = HTTPSTATUS_INTERNALERROR;
			$errorMsg = mysqli_error($mysqli);
			return null;
		} else {
			$breakedPolylines[0] = array(
					'id_edge' => strval($newIdEdge),
					'polyline' => $polylineData1,
					'edge_name' => $edgeData1['edge_name'],
					'reversible' => $isReversible
			);
		}
		
		//-- Ambil edge bagian kedua
		$newEdgeName = null;
		
		$polyDistance = 0.0; // Init
		$idxCounter = 0;
		
		$polylineData2 = [];
		for ($idxCounter = $vertexIndex+1; $idxCounter <= $vertexTotal; $idxCounter++) {
			if ($idxCounter != $vertexTotal) $polylineData2[] = $edgePoints[$idxCounter];
			$polyDistance += node_distance($edgePoints[$idxCounter-1], $edgePoints[$idxCounter], 'K');
		}
		
		$edgeData2 = array(
				'id_node_from' => $newIdNode,
				'id_node_dest' => $newEdgeData['id_node_dest'],
				'polyline' => db_geom_from_text(latlng_coords_to_mysql($polylineData2)),
				'edge_name' => _db_to_query($newEdgeName, $mysqli),
				'distance' => $polyDistance,
				'date_created' => "'".$currentDate."'",
				'id_creator' => 0,
				'creator' => "'system'",
				'reversible' => ($isReversible ? 1 : 0)
		);
		
		//-- Buat edge baru untuk pecahan kedua
		$newIdEdge = $edgeModel->save_edge($edgeData2, null);
		if (!$newIdEdge) {
			$errorCode = HTTPSTATUS_INTERNALERROR;
			$errorMsg = mysqli_error($mysqli);
			return null;
		} else {
			$breakedPolylines[1] = array(
					'id_edge' => strval($newIdEdge),
					'polyline' => $polylineData2,
					'edge_name' => $newEdgeName,
					'reversible' => $isReversible
			);
		}
		
		//-- Update routes
		if (!empty($routeData)) {
			//-- Check existing routes...
			require_once SRCPATH.'/helpers/route_tools.php';
			$procResult = true;
		
			foreach ($routeData as $itemRoute) {
				$procResult = fix_route($edgeModel, $routeModel, $itemRoute['id_route'],
						$oldEdgeData, $newId, $breakedPolylines[1]['id_edge']);
				if (!$procResult) {
					break;
				}
			}
		
			if (!$procResult) {
				$errorCode = HTTPSTATUS_INTERNALERROR;
				$errorMsg = ("Internal query error. ".mysqli_error($mysqli));
				return null;
			}
		} // End if
		return $newIdNode;
	} else {
		$errorCode = HTTPSTATUS_INTERNALERROR;
		$errorMsg = mysqli_error($mysqli);
	}
	
	return null;
}