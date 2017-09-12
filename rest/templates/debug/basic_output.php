<?php
/*
 * templates/debug/basic_output.php
 * ---------------------
 * Console output
 */

	if (!isset($output)) $output = null;
?>
<!DOCTYPE html>

<html lang="id">
	<head>
		<meta charset="UTF-8" />
		<meta name="description" content=""/>
		<meta name="keywords" content=""/>
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		<title><?php
			if (isset($pageTitle)) echo htmlspecialchars($pageTitle);
			else echo "Untitled";
		?> | AngkotTracer</title>
		<link rel="icon" href="<?php echo $baseUrl.('/assets/favicon.ico'); ?>" type="image/x-icon" />
		<link rel="stylesheet" href="<?php echo $baseUrl.('/assets/css/bootstrap.min.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo $baseUrl.('/assets/css/font-awesome.min.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo $baseUrl.('/assets/css/toastr.min.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo $baseUrl.('/assets/css/global.css'); ?>" type="text/css" media="screen" />
		<link rel="stylesheet" href="<?php echo $baseUrl.('/assets/css/select2.min.css'); ?>" type="text/css" media="screen" />
	</head>
	<body style="background-color: #eee;">

<div class="container">

	<div class="row">
		<div class="col-md-12">
			<pre><?php echo $output; ?></pre>
		</div>
	</div>

</div>
<!-- /container -->

	</body>
</html>