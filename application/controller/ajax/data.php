<?php
/*
 * controller/ajax/data.php
 * -----------------------------------------------------
 * Controller AJAX untuk modul data.
 * By Nur Hardyanto
 */

	$actionVerb = $_POST['verb'];
	
	$verbSegments = explode('.', $actionVerb, 2);
	$verbObject = $verbSegments[0];
	$verbMethod = (isset($verbSegments[1]) ? $verbSegments[1] : null);
	
	if ($verbObject == "node") {
		require(APP_PATH."/controller/ajax/components/ct_node.php");
		$jsonResponse = _data_ajax_node($verbMethod);
	} else if ($verbObject == "edge") {
		require(APP_PATH."/controller/ajax/components/ct_edge.php");
		$jsonResponse = _data_ajax_edge($verbMethod);
	} else if ($verbObject == "route") {
		require(APP_PATH."/controller/ajax/components/ct_route.php");
		$jsonResponse = _data_ajax_route($verbMethod);
	} else {
		$jsonResponse = generate_error('Unrecognized verb.');
	}
	