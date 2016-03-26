<?php

$actionVerb = $_POST['verb'];
$jsonResponse = array();

if ($actionVerb == "search") {
	require(APP_PATH."/controller/main/a-star.php");
} else {
	echo generate_error('Unrecognized verb.');
}

echo json_encode($jsonResponse);

function generate_error($errorStr) {
	return array(
		'status' => 'error',
		'message' => $errorStr
	);
}