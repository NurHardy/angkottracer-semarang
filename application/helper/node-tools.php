<?php

/**
 * Simpan dan pecah edge menjadi 2 edge.
 * Jangan lupa load model <code>node_model</code>, helper <code>geo-tools</code>
 * 
 * @param array $edgePoints Array of points (lat: , lng: )
 * @param int $vertexIndex Index vertex untuk dipecah
 * @param int|null $idEdge ID edge lama. NULL jika edge pecahan bagian pertama
 * 	disimpan dalam record baru.
 * @return NULL|int Kembali ID node baru jika proses berhasil, atau FALSE jika gagal.
 */
function save_and_break_edge($edgePoints, $vertexIndex, $idEdge, $replaceOld = true, &$errorMsg) {
	/*
	 * A - x - B
	 * Jadi :	A - x => disimpan pada edge lama (jika idEdge diset)
	 * 			x - B => disimpan pada edge baru
	 */
	
	$errorMsg = null;
	$isReversible = 1; // Default
	$currentDate = date('Y-m-d H:i:s');
	$vertexTotal = count($edgePoints);
	
	//-- Get edge info
	$edgeData = get_edge_by_id($idEdge);
	
	if (!$edgeData) return null;
	
	$isReversible = ($edgeData['reversible'] == 1);
	
	//-- Simpan node baru
	$nodeData = array(
			'node_name' => "'New node'",
			'location' => "GeomFromText('".latlng_point_to_mysql($edgePoints[$vertexIndex]['lng'], $edgePoints[$vertexIndex]['lat'])."')",
			'date_created' => "'".$currentDate."'",
			'id_creator' => 0,
			'creator' => "'system'"
	);
	$newIdNode = save_node($nodeData);
	
	if ($newIdNode) {
		//-- Ambil edge bagian pertama
		$polyLine1 = array();
		
		$idxCounter = 0;
		for ($idxCounter = 1; $idxCounter < $vertexIndex; $idxCounter++) {
			$polyLine1[] = $edgePoints[$idxCounter];
		}
		
		$polyDistance = polyline_length($polyLine1, 'K');
		$edgeData1 = array(
				'id_node_from' => $edgeData['id_node_from'],
				'id_node_dest' => $newIdNode,
				'polyline' => "GeomFromText('".latlng_coords_to_mysql($polyLine1)."')",
				'distance' => $polyDistance,
				'date_created' => "'".$currentDate."'",
				'id_creator' => 0,
				'creator' => "'system'",
				'reversible' => ($isReversible ? 1 : 0)
		);
		
		if ($replaceOld) {
			save_edge($edgeData1, $idEdge);
		} else {
			save_edge($edgeData1, null);
		}
		
		//-- Ambil edge bagian kedua
		$polyLine2 = array();
		
		$idxCounter = 0;
		for ($idxCounter = $vertexIndex+1; $idxCounter <= $vertexTotal; $idxCounter++) {
			$polyLine2[] = $edgePoints[$idxCounter];
		}
		
		$polyDistance = polyline_length($polyLine2, 'K');
		$edgeData2 = array(
				'id_node_from' => $newIdNode,
				'id_node_dest' => $edgeData['id_node_dest'],
				'polyline' => "GeomFromText('".latlng_coords_to_mysql($polyLine2)."')",
				'distance' => $polyDistance,
				'date_created' => "'".$currentDate."'",
				'id_creator' => 0,
				'creator' => "'system'",
				'reversible' => ($isReversible ? 1 : 0)
		);
		
		save_edge($edgeData2, null);
		return $newIdNode;
	} else {
		global $mysqli;
		$errorMsg = mysqli_error($mysql);
	}
	
	return null;
}