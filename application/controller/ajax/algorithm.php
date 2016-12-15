<?php
/*
 * controller/ajax/algorithm.php
 * ------------------------------
 * AJAX algorithm module
 */

	$actionVerb = $_POST['verb'];
	
	$verbSegments = explode('.', $actionVerb, 2);
	$verbObject = $verbSegments[0];
	$verbMethod = (isset($verbSegments[1]) ? $verbSegments[1] : null);
	
	if ($verbObject == "algorithm") {
		if ($verbMethod == "astar") {
			require(APP_PATH."/controller/main/a-star.php");
		}
	} else {
		$jsonResponse = generate_error('Unrecognized verb.');
	}
	