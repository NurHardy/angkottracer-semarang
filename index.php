<?php
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
	} else {
		
		//require_once(APP_PATH."/model/m_barang.php");
		//$data['listBarangBaru'] = get_list_barang(-1, 1, 8);
		
		require(APP_PATH."/controller/home.php");
	}