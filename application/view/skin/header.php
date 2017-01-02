<!DOCTYPE html>

<html lang="id">
	<head>
		<meta charset="UTF-8" />
		<meta name="description" content=""/>
		<meta name="keywords" content=""/>
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		<title><?php
			if (isset($data['pageTitle'])) echo htmlspecialchars($data['pageTitle']);
			else echo "Untitled";
		?> | AngkotTracer</title>
		<link rel="icon" href="<?php echo _base_url('/assets/favicon.ico'); ?>" type="image/x-icon" />
		<link rel="stylesheet" href="<?php echo _base_url('/assets/css/bootstrap.min.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo _base_url('/assets/css/font-awesome.min.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo _base_url('/assets/css/toastr.min.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo _base_url('/assets/css/global.css'); ?>" type="text/css" media="screen" />
		
		<script src="<?php echo _base_url('/assets/js/jquery.min.js'); ?>"></script>
	</head>
	<body>
