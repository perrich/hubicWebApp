<?php 
namespace Perrich\Controllers;

use Perrich\HttpHelper;


class EncryptionController extends Controller
{
	public function define()
    {
		$params = HttpHelper::getParameters();
		
        $_SESSION[$this->config->get('session_encryption_key')] = $this->config->get('encryption_key') . $params['key'];
		
		HttpHelper::disableCache();
		HttpHelper::jsonContent('{"result": "done"}');
    }
}