<?php
require_once  __DIR__ . '/../app/bootstrap.php';

use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Perrich\HubicAuth;
use Perrich\Hubic;
use Perrich\RouteDataProvider;
use Perrich\HandlerResolver;


session_start();

$log = new Logger('ROUTER');
$log->pushHandler(new StreamHandler($conf->get('logfile'), Logger::DEBUG));

$auth = new HubicAuth($conf->get('client_id'), $conf->get('client_secret'), $conf->get('base_uri'));
$hubic = new Hubic($auth);

$provider = new RouteDataProvider();

try {
	$prefix = basename(__FILE__);
	$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$pos = strpos($url, $prefix);
	if ($pos !== false) {
		$uri = substr($url, $pos + strlen($prefix));
	}
	
	$dispatcher = new Phroute\Phroute\Dispatcher($provider->provideData(), new HandlerResolver($log, $conf, $hubic));
	$response = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);
	echo $response;
}
catch(Phroute\Phroute\Exception\HttpRouteNotFoundException $e) {
	http_response_code(404);
}
catch(Phroute\Phroute\Exception\HttpMethodNotAllowedException $e) {
	http_response_code(405);
	header($e->getMessage());
}
catch(Exception $e) {
	echo $e;
	http_response_code(500);
}
?>