<?php 
require_once  __DIR__ . '/../app/bootstrap.php';

use Perrich\HubicAuth;

session_start();

$isInit = (count($_GET) === 0);

if ($isInit) {
	session_unset(); // clean session
}

$auth = new HubicAuth($conf->get('client_id'), $conf->get('client_secret'), $conf->get('base_uri'));
if (!$auth->isAuthentificated()) {
	$auth->authentificate();
	if ($isInit) {
		return;
	}
}
?>
<!DOCTYPE html>
<html ng-app="filestorage" ng-strict-di>

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.4/angular.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.4/angular-sanitize.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.4/angular-animate.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.4/angular-resource.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.4/angular-route.min.js"></script>
  	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
	<script src="js/ng-file-upload.min.js"></script>
	<script src="js/FileSaver.min.js"></script>
	<script src="js/toaster.min.js"></script>
	<script src="js/contextMenu.js"></script>
	<!-- build:css -->
	<link rel="stylesheet" href="css/bootstrap.min.css" />
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css"/>
	<link href="css/style.css" rel="stylesheet" type="text/css" />
	<link href="css/toaster.min.css" rel="stylesheet" type="text/css" />
	<!-- endbuild -->
	<title>Hubic access</title>
</head>

<body>
	<!-- build:js -->
	<script type="text/javascript" src="js/app.js"></script>
	<script type="text/javascript" src="js/config.php"></script>
	<script type="text/javascript" src="js/controllers.js"></script>
	<script type="text/javascript" src="js/services.js"></script>
	<!-- endbuild -->
	<nav class="navbar navbar-default navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="#folder"><i class="fa fa-hdd-o"></i> hubiC file storage</a>
			</div>
			<div class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li class="active"><a href="#folder">Home</a></li>
					<li><a href="#about">About</a></li>
					<li><a href="index.php">Disconnect</a></li>
				</ul>
			</div>
		</div>
	</nav>
	<div ng-view></div>
	<toaster-container></toaster-container>
</body>

</html>