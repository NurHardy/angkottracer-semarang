<?php

//=============== AJAX FUNCTIONS ==============
	function generate_error($errorStr) {
		return array(
				'status' => 'error',
				'message' => $errorStr
		);
	}

//=============== AJAX RUNTIME ==============
	$jsonResponse = array();
	$moduleName = $_GET['mod'];
	
	if ($moduleName == "data") {
		require(APP_PATH."/controller/ajax/data.php");
	} else {
		echo generate_error('Unrecognized module.');
	}

	if (!empty($jsonResponse)) {
		header('Content-Type: application/json');
		echo json_encode($jsonResponse);
	}
	