<?php

require(APP_PATH."/controller/main/data.php");

$jsonResponse = array(
	'status' => 'ok',
	'data' => $nodes
);