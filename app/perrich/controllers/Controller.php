<?php 
namespace Perrich\Controllers;

abstract class Controller
{
	protected $log;
	protected $hubic;
	protected $config;
	
	public function init($log, $config, $hubic)
	{
		$this->log = $log;
		$this->config = $config;
		$this->hubic = $hubic;
	}
}