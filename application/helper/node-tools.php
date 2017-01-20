<?php

/**
 * Simpan dan pecah edge menjadi 2 edge.
 * Jangan lupa load model <code>node_model</code>, helper <code>geo-tools</code>
 * 
 * @param array $edgePoints Array of points (lat: , lng: ). Vertex ujung merupakan
 * 	node dan akan diabaikan...
 * @param int $vertexIndex Index vertex untuk dipecah.
 * @param int|null $idEdge ID edge lama. NULL jika edge pecahan bagian pertama
 * 	disimpan dalam record baru.
 * @param string $errorMsg [OUT] Pesan error jika proses gagal
 * @param array $breakedPolylines [OUT] Array of polyline, index 0 dan 1
 * @param bool $replaceOld Replace edge lama? Default: TRUE
 * @return NULL|int Kembali ID node baru jika proses berhasil, atau FALSE jika gagal.
 */
function save_and_break_edge($edgePoints, $vertexIndex, $idEdge, &$errorMsg, &$breakedPolylines, $replaceOld = true) {
	global $mysqli;
	/*
	 * A - x - B
	 * Jadi :	A - x => disimpan pada edge lama (jika idEdge diset)
	 * 			x - B => disimpan pada edge baru
	 */
	
	$errorMsg = null;
	$isReversible = 1; // Default
	$currentDate = date('Y-m-d H:i:s');
	$vertexTotal = count($edgePoints)-1;
	
	//-- Get edge info
	$edgeData = get_edge_by_id($idEdge);
	
	if (!$edgeData) {
		$errorMsg = "Specified edge data is not found.";
		return null;
	}
	
	$isReversible = ($edgeData['reversible'] == 1);
	
	$mysqli->autocommit(false);
	//-- Simpan node baru
	$nodeData = array(
			'node_name' => "'New node'",
			'location' => db_geom_from_text(latlng_point_to_mysql($edgePoints[$vertexIndex]['lng'], $edgePoints[$vertexIndex]['lat'])),
			'date_created' => "'".$currentDate."'",
			'id_creator' => 0,
			'creator' => "'system'"
	);
	$newIdNode = save_node($nodeData);
	
	if ($newIdNode) {
		$breakedPolylines = array();
		
		$polylineData1 = [];
		//-- Ambil edge bagian pertama
		$idxCounter = 0;
		for ($idxCounter = 1; $idxCounter < $vertexIndex; $idxCounter++) {
			$polylineData1[] = $edgePoints[$idxCounter];
		}
		
		$polyDistance = polyline_length($polylineData1, 'K');
		$edgeData1 = array(
				'id_node_from' => $edgeData['id_node_from'],
				'id_node_dest' => $newIdNode,
				'polyline' => db_geom_from_text(latlng_coords_to_mysql($polylineData1)),
				'distance' => $polyDistance,
				'date_created' => "'".$currentDate."'",
				'id_creator' => 0,
				'creator' => "'system'",
				'reversible' => ($isReversible ? 1 : 0)
		);
		
		$newEdgeName = $edgeData['edge_name'];
		$newIdEdge = null;
		
		if ($replaceOld) {
			$newIdEdge = $idEdge;
			$edgeData1['edge_name'] = _db_to_query($edgeData['edge_name']);
		} else {
			$edgeData1['edge_name'] = _db_to_query($edgeData['edge_name']." #2");
		}
		
		$newIdEdge = save_edge($edgeData1, $newIdEdge);
		if (!$newIdEdge) {
			$errorMsg = mysqli_error($mysqli);
			$mysqli->rollback();
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
		$idxCounter = 0;
		
		$polylineData2 = [];
		for ($idxCounter = $vertexIndex+1; $idxCounter < $vertexTotal; $idxCounter++) {
			$polylineData2[] = $edgePoints[$idxCounter];
		}
		
		$polyDistance = polyline_length($polylineData2, 'K');
		$edgeData2 = array(
				'id_node_from' => $newIdNode,
				'id_node_dest' => $edgeData['id_node_dest'],
				'polyline' => db_geom_from_text(latlng_coords_to_mysql($polylineData2)),
				'edge_name' => _db_to_query($newEdgeName),
				'distance' => $polyDistance,
				'date_created' => "'".$currentDate."'",
				'id_creator' => 0,
				'creator' => "'system'",
				'reversible' => ($isReversible ? 1 : 0)
		);
		
		//-- Buat edge baru untuk pecahan kedua
		$newIdEdge = save_edge($edgeData2, null);
		if (!$newIdEdge) {
			$errorMsg = mysqli_error($mysqli);
			$mysqli->rollback();
			return null;
		} else {
			$breakedPolylines[1] = array(
					'id_edge' => strval($newIdEdge),
					'polyline' => $polylineData2,
					'edge_name' => $newEdgeName,
					'reversible' => $isReversible
			);
		}
		
		$mysqli->commit();
		return $newIdNode;
	} else {
		$errorMsg = mysqli_error($mysqli);
		$mysqli->rollback();
	}
	
	return null;
}