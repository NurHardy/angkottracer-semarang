<?php
/*
 * controller/tools/road_interpolate.php
 * --------------------------------------
 * Proses request ke google road API, mengambil data koordinat jalan
 * 
 */

	//== Ambil data koordinat
	$idEdge = $_POST['id_edge'];
	
	
	require_once APP_PATH.'/model/edge.php';
	require_once APP_PATH.'/model/node.php';
	
	
	
	
