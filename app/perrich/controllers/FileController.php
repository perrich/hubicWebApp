<?php 
namespace Perrich\Controllers;

use Perrich\HttpHelper;
use Perrich\Helper;

class FileController extends Controller
{
	public function getContent($fullpath)
    {
		$src = $this->hubic->fopen(self::getRealname($fullpath), 'rb');
		if (Helper::endsWith($fullpath, $this->config->get('encrypted_file_ext'))) {
			$crypt = new \AESCryptFileLib(new \MCryptAES256Implementation(), false);
			$contents = $crypt->decryptFile($src, $this->getKey());
		} else {
			$contents = stream_get_contents($src);
		}
		fclose($src);
		
		header("Content-Type: application/octet-stream");
		return $contents;
    }
	
	public function saveContent($fullpath)
    {
		$result = NULL;
		if (Helper::endsWith($fullpath, $this->config->get('encrypted_file_ext'))) {
			$crypt = new \AESCryptFileLib(new \MCryptAES256Implementation(), false);
			$result = $crypt->encryptContents(file_get_contents($_FILES['file']['tmp_name']), $this->getKey());
		} else {
			$result = file_get_contents($_FILES['file']['tmp_name']);
		}
		
		$dest = $this->hubic->fopen(self::getRealname($fullpath), 'wb');
		$this->fwrite_stream($dest, $result);
		fclose($dest);
		$this->hubic->cleanupCallbackFunction();
		HttpHelper::jsonContent('{"result": "done"}');
    }
	
	public function delete($fullpath)
    {
		$files = $this->hubic->unlink(self::getRealname($fullpath));
		
		HttpHelper::disableCache();
		HttpHelper::jsonContent('{"result": "done"}');
    }
	
	private static function getRealname($fullpath)
    {
		return str_replace('|', '/', urldecode($fullpath));
    }
	
	private function getKey()
	{
		if (!isset($_SESSION[$this->config->get('session_encryption_key')])) {
			$_SESSION[$this->config->get('session_encryption_key')] = $this->config->get('encryption_key');
		}
		return $_SESSION[$this->config->get('session_encryption_key')];
	}
	
	private static function fwrite_stream($fp, $string)
	{ 
		for ($written = 0; $written < strlen($string); $written += $fwrite) { 
			$fwrite = fwrite($fp, substr($string, $written)); 
			if ($fwrite === false) { 
				return $written; 
			} 
		} 
		return $written; 
	} 
}