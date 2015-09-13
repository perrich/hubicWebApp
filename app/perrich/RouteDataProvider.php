<?php 
namespace Perrich;

use Phroute\Phroute\RouteCollector;

class RouteDataProvider
{
	const CACHE_FILENAME = 'route.cache';
	
	private $cachePath;
	
	public function __construct($path = null)
	{
		$this->cachePath = (isset($path) ? $path : __DIR__ ). '/../'. static::CACHE_FILENAME;
	}
	
	private function addRoutes($routeCollector)
	{
		$routeCollector->get('/folder/', ['\\Perrich\\Controllers\\FolderController','getRootFiles']);
		$routeCollector->get('/folder/{fullpath}', ['\\Perrich\\Controllers\\FolderController','getFiles']);
		$routeCollector->post('/folder/{fullpath}', ['\\Perrich\\Controllers\\FolderController','create']);
		$routeCollector->delete('/folder/{fullpath}', ['\\Perrich\\Controllers\\FolderController','delete']);
		
		$routeCollector->get('/file/{fullpath}', ['\\Perrich\\Controllers\\FileController','getContent']);
		$routeCollector->post('/file/{fullpath}', ['\\Perrich\\Controllers\\FileController','saveContent']);
		$routeCollector->delete('/file/{fullpath}', ['\\Perrich\\Controllers\\FileController','delete']);
		
		
		$routeCollector->post('/crypt/define', ['\\Perrich\\Controllers\\EncryptionController','define']);
	}
	
	/**
	 * Provide the routes.
	 *
	 * @return Phroute\Phroute\RouteDataInterface
	 */	
	public function provideData()
	{
		if (file_exists($this->cachePath)) {
            $dispatchData = unserialize(file_get_contents($this->cachePath));
        } else {
			$routeCollector = new RouteCollector();
			$this->addRoutes($routeCollector);
			$dispatchData = $routeCollector->getData();
			file_put_contents($this->cachePath, serialize($dispatchData));
		}
		
        return $dispatchData;
	}
}