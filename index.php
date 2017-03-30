<?php

header("Location: rest/public/editor");
exit;

//========================================================================
//							ANGKOT TRACER
//					by Muhammad Nur Hardyanto
//						nurhardyanto@if.undip.ac.id
//========================================================================

	// Klo udah release, yg ini dikomen aja
	error_reporting(E_ALL);

	// Definisi lokasi file di server
	define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
	define('FCPATH', str_replace(SELF, '', __FILE__));
	define('APP_PATH', FCPATH.'/application');

	// Untuk menghitung waktu eksekusi
	$timeStart = microtime(true);
	$queryCount = 0;
	
	if (!session_id()) session_start();

	global $mysqli;
	require_once(APP_PATH.'/dbconfig.php');
	require_once(APP_PATH.'/functions.php');

	$data = array();

	$pageSlug	= null;
	if (isset($_GET['p']))		$pageSlug	= $_GET['p'];
	
	if ($pageSlug == "ajax") {
		require(APP_PATH."/controller/ajax.php");
	} else if ($pageSlug == "dude") {
		require(APP_PATH."/helper/geo-tools.php");
		echo kml_coords_to_mysql('');
	} else if ($pageSlug == "polyline") {		
		require(APP_PATH."/helper/gmap-tools.php");
		
		$locArray = decode_polyline("imq~Fxh|vOvKc_KcRivI");
		echo "<pre>".print_r($locArray, true)."</pre>";
	} else if ($pageSlug == "astar") {
		require(APP_PATH."/controller/main/a-star.php");
	} else if ($pageSlug == "recalculate") {
		require(APP_PATH."/controller/tools/recalculate.php");
	} else {
		require(APP_PATH."/controller/home.php");
	}
	
	