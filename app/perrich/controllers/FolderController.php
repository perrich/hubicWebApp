<?php 
namespace Perrich\Controllers;

use Perrich\HttpHelper;


class FolderController extends Controller
{
	public function getRootFiles()
    {
        return $this->getFiles('.');
    }
	
	public function getFiles($fullpath)
    {
		$files = $this->hubic->opendir(self::getRealname($fullpath));
		
		HttpHelper::disableCache();
        return HttpHelper::jsonContent($files);
    }
	
	public function create($fullpath)
    {
		$files = $this->hubic->mkdir(self::getRealname($fullpath));
		
		HttpHelper::disableCache();
		HttpHelper::jsonContent('{"result": "done"}');
    }
	
	public function delete($fullpath)
    {
		$files = $this->hubic->rmdir(self::getRealname($fullpath));
		
		HttpHelper::disableCache();
		HttpHelper::jsonContent('{"result": "done"}');
    }
	
	private static function getRealname($fullpath)
    {
		return str_replace('|', '/', urldecode($fullpath));
    }
}