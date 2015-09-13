<?php

namespace Perrich;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use OpenCloud;
use OpenCloud\Common\Exceptions;
use OpenCloud\OpenStack;
use OpenCloud\ObjectStore\Resource\DataObject;
use OpenCloud\ObjectStore\Exception;
use OpenCloud\Common\Service\Endpoint;
use OpenCloud\Common\Service\CatalogItem;
use OpenCloud\Common\Service\Catalog;

class Hubic 
{
	/**
	 * @var \OpenCloud\ObjectStore\Service
	 */
	private $connection;
	/**
	 * @var \OpenCloud\ObjectStore\Resource\Container
	 */
	private $container;
	/**
	 * @var \OpenCloud\OpenStack
	 */
	private $anchor;
	/**
	 * @var string
	 */
	private $bucket;
	/**
	 * Connection parameters
	 *
	 * @var array
	 */
	private $params;
	/**
	 * Connection parameters
	 *
	 * @var array
	 */
	private $credentials;
	/**
	 * @var array
	 */
	private static $tmpFiles = array();
	/**
	 * @param string $path
	 */
	private function normalizePath($path) {
		$path = trim($path, '/');
		if (!$path) {
			$path = '.';
		}
		$path = str_replace('#', '%23', $path);
		return $path;
	}
	
	public function __construct($hubicAuth) {
		$this->bucket = 'default';
		$this->hubicAuth = $hubicAuth;
		
		register_shutdown_function(array(&$this, 'cleanupCallbackFunction')); // the & is important 
	}	
	
	/**
	 * import swift credentials in OpenStack object
	 */
	private function importCredentials() {
		$this->anchor->importCredentials(array(
			'token' => $this->credentials['token'],
			'catalog' => array(
				(object) array(
					'endpoints' => array(
						(object) array(
							'region'      => 'NCE',
							'publicURL'   => $this->credentials['endpoint']
						)
					),
					'name' => 'cloudFiles',
					'type' => 'object-store'
				)
			)
		));
	}
	
	private function retrieveCredentials() {
		$credentials = $this->hubicAuth->getCredentials();
		$this->credentials = json_decode($credentials, true);
	}
	
	/**
	 * @param string $path
	 */
	private function doesObjectExist($path) {
		try {
			$this->getContainer()->getPartialObject($path);
		} catch (ClientErrorResponseException $e) {
			return false;
		}
		return true;
	}
	
	public function mkdir($path) {
		$path = $this->normalizePath($path);
		if ($this->is_dir($path)) {
			return false;
		}
		
		try {
			$customHeaders = array('content-type' => 'application/directory');
			$metadataHeaders = DataObject::stockHeaders(array());
			$allHeaders = $customHeaders + $metadataHeaders;
			$this->getContainer()->uploadObject($path, '', $allHeaders);
		} catch (Exceptions\CreateUpdateError $e) {
			return false;
		}
		return true;
	}
	
	public function file_exists($path) {
		$path = $this->normalizePath($path);
		return $this->doesObjectExist($path);
	}
	
	public function rmdir($path) {
		$path = $this->normalizePath($path);
		if (!$this->is_dir($path)) {
			return false;
		}
		
		// First remove contents
		$files = $this->opendir($path);
		foreach ($files as $file) {
			if ($this->is_dir($path . '/' . $file['name'])) {
				$this->rmdir($path . '/' . $file['name']);
			} else {
				$this->unlink($path . '/' . $file['name']);
			}
		}
		
		// then remove folder
		try {
			$this->getContainer()->dataObject()->setName($path)->delete();
		} catch (Exceptions\DeleteError $e) {
			return false;
		}
		return true;
	}
	
	public function opendir($path) {
		$path = $this->normalizePath($path);
		if ($path === '.') {
			$path = '';
		} else {
			$path .= '/';
		}
		$path = str_replace('%23', '#', $path); // the prefix is sent as a query param, so revert the encoding of #
		try {
			$files = array();
			/** @var OpenCloud\Common\Collection $objects */
			$objects = $this->getContainer()->objectList(array(
				'prefix' => $path,
				'delimiter' => '/'
			));
			/** @var OpenCloud\ObjectStore\Resource\DataObject $object */
			foreach ($objects as $object) {				
				$is_dir_with_files = substr($object->getName(), -1) === '/';
				$filename = basename($object->getName());
				
				if ($filename === '.' || $filename === '..') {
					continue;
				}
				
				$pos = $this->getPositionFromArray($files, $filename);				
				if ($pos === null) {
					$is_dir = $object->isDirectory() || $this->isDirectory($filename);
					$files[] = array(
						'name' => $filename,
						'type' =>  $is_dir ? 'folder' : 'file',
						'empty' => !$is_dir || !$is_dir_with_files
					);
				} else {
					if ($is_dir_with_files) {
						$files[$pos] = array(
							'name' => $filename,
							'type' => 'folder',
							'empty' => false
						);
					}
				}
			}
			
			return $files;
		} catch (Exception $e) {
			return false;
		}
	}
	
	private function getPositionFromArray($files, $name) {
		for($i = 0; $i < count($files); $i++) {
			if ($files[$i]['name'] === $name)
				return $i;
		}
		
		return null;
	}
	
	private function isDirectory($filename) {
		$separator_pos = strrpos($filename, '.');
		if ($separator_pos !== false) {
			$len = strlen($filename);
			return $separator_pos > strlen($filename) - 2 || $separator_pos < strlen($filename) - 10; // .torrent .encrypted
		}
		
		return true;
	}
	
	public function stat($path) {
		$path = $this->normalizePath($path);
  
                if ($path === '.') {
                        $path = '';
                } else if ($this->is_dir($path)) {
                        $path .= '/';
                }
		try {
			$object = $this->getContainer()->getPartialObject($path);
		} catch (ClientErrorResponseException $e) {
			return false;
		}
		$dateTime = \DateTime::createFromFormat(\DateTime::RFC1123, $object->getLastModified());
		if ($dateTime !== false) {
			$mtime = $dateTime->getTimestamp();
		} else {
			$mtime = null;
		}
		$objectMetadata = $object->getMetadata();
		$metaTimestamp = $objectMetadata->getProperty('timestamp');
		if (isset($metaTimestamp)) {
			$mtime = $metaTimestamp;
		}
		if (!empty($mtime)) {
			$mtime = floor($mtime);
		}
		$stat = array();
		$stat['size'] = (int)$object->getContentLength();
		$stat['mtime'] = $mtime;
		$stat['atime'] = time();
		return $stat;
	}
	
	public function filetype($path) {
		$path = $this->normalizePath($path);
		if ($this->doesObjectExist($path)) {
                    $object = $this->container->getPartialObject($path);
                    return $this->getFileType($object);
                }
	}
	
	private function getFileType($object) {
		if ($object->getContentType() == 'application/directory') {
			return 'dir';
		}
		elseif ($object->getContentType() == 'httpd/unix-directory') {
			return 'dir';
		}
		else {
			return 'file';
		}
	}
	
	public function is_dir($path) {
		return $this->filetype($path) == 'dir';
	}
	
	public function unlink($path) {
		$path = $this->normalizePath($path);
		if ($this->is_dir($path)) {
			return $this->rmdir($path);
		}
		try {
			$this->getContainer()->dataObject()->setName($path)->delete();
		} catch (ClientErrorResponseException $e) {
			return false;
		}
		return true;
	}
	
	public function fopen($path, $mode) {
		$path = $this->normalizePath($path);
		switch ($mode) {
			case 'r':
			case 'rb':
				$tmpFile = $this->createTempFile();
				self::$tmpFiles[$tmpFile] = $path;
				try {
					$object = $this->getContainer()->getObject($path);
				} catch (ClientErrorResponseException $e) {
					return false;
				} catch (Exception\ObjectNotFoundException $e) {
					return false;
				}
				try {
					$objectContent = $object->getContent();
					$objectContent->rewind();
					$stream = $objectContent->getStream();
					file_put_contents($tmpFile, $stream);
				} catch (Exceptions\IOError $e) {
					return false;
				}
				return fopen($tmpFile, 'r');
			case 'w':
			case 'wb':
			case 'a':
			case 'ab':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				$tmpFile = $this->createTempFile();
				if ($this->file_exists($path)) {
					$source = $this->fopen($path, 'r');
					file_put_contents($tmpFile, $source);
				}
				self::$tmpFiles[$tmpFile] = $path;
				return fopen($tmpFile, $mode);
		}
	}
	
	private function createTempFile()
	{
		$dir = __DIR__ . '/../tmp/';
		if (!file_exists($dir)) {
			mkdir($dir);
		}
		return tempnam($dir, 'TMP_');
	}
	
	public function getMimeType($path) {
		$path = $this->normalizePath($path);
		if ($this->is_dir($path)) {
			return 'httpd/unix-directory';
		} else if ($this->file_exists($path)) {
			$object = $this->getContainer()->getPartialObject($path);
			return $object->getContentType();
		}
		return false;
	}
	
	public function touch($path, $mtime = null) {
		$path = $this->normalizePath($path);
		if (is_null($mtime)) {
			$mtime = time();
		}
		$metadata = array('timestamp' => $mtime);
		if ($this->file_exists($path)) {
			$object = $this->getContainer()->getPartialObject($path);
			$object->saveMetadata($metadata);
			return true;
		} else {
			$mimeType = \OC_Helper::getMimetypeDetector()->detectPath($path);
			$customHeaders = array('content-type' => $mimeType);
			$metadataHeaders = DataObject::stockHeaders($metadata);
			$allHeaders = $customHeaders + $metadataHeaders;
			$this->getContainer()->uploadObject($path, '', $allHeaders);
			return true;
		}
	}
	
	public function copy($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);
		$fileType = $this->filetype($path1);
		if ($fileType === 'file') {
			// make way
			$this->unlink($path2);
			try {
				$source = $this->getContainer()->getPartialObject($path1);
				$source->copy($this->bucket . '/' . $path2);
			} catch (ClientErrorResponseException $e) {
				return false;
			}
		} else if ($fileType === 'dir') {
			// make way
			$this->unlink($path2);
			try {
				$source = $this->getContainer()->getPartialObject($path1);
				$source->copy($this->bucket . '/' . $path2);
			} catch (ClientErrorResponseException $e) {
				return false;
			}
			$dh = $this->opendir($path1);
			while ($file = readdir($dh)) {
				if ($file === '.' || $file === '..') {
					continue;
				}
				$source = $path1 . '/' . $file;
				$target = $path2 . '/' . $file;
				$this->copy($source, $target);
			}
		} else {
			//file does not exist
			return false;
		}
		return true;
	}
	
	public function rename($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);
		$fileType = $this->filetype($path1);
		if ($fileType === 'dir' || $fileType === 'file') {
			// make way
			$this->unlink($path2);
			// copy
			if ($this->copy($path1, $path2) === false) {
				return false;
			}
			// cleanup
			if ($this->unlink($path1) === false) {
				$this->unlink($path2);
				return false;
			}
			return true;
		}
		return false;
	}
	
	public function writeBack($tmpFile) {
		if (!isset(self::$tmpFiles[$tmpFile])) {
			return false;
		}
		$fh = fopen($tmpFile, 'r');
		$this->getContainer()->uploadObject(self::$tmpFiles[$tmpFile], $fh);
		unlink($tmpFile);
		unset(self::$tmpFiles[$tmpFile]);
	}
	
	public function cleanupCallbackFunction()
	{
		foreach(self::$tmpFiles as $key => $value) {
			$this->writeBack($key);
		}			
	}
	
	public function hasUpdated($path, $time) {
			if ($this->is_file($path)) {
					return parent::hasUpdated($path, $time);
			}
			$path = $this->normalizePath($path);
			$dh = $this->opendir($path);
			$content = array();
			while (($file = readdir($dh)) !== false) {
					$content[] = $file;
			}
			if ($path === '.') {
					$path = '';
			}
			$cachedContent = $this->getCache()->getFolderContents($path);
			$cachedNames = array_map(function ($content) {
					return $content['name'];
			}, $cachedContent);
			sort($cachedNames);
			sort($content);
			return $cachedNames != $content;
	}
	

	/**
	 * Returns the connection
	 *
	 * @return OpenCloud\ObjectStore\Service connected client
	 * @throws \Exception if connection could not be made
	 */
	public function getConnection() {
		if (!is_null($this->connection)) {
			return $this->connection;
		}
		$this->anchor = new OpenStack(hubicAuth::HUBIC_URI_BASE, array());
		
		if (!isset($this->credentials)) {
			$this->retrieveCredentials();
		}
		$this->importCredentials();
		$this->connection = $this->anchor->objectStoreService('cloudFiles', 'NCE');
		return $this->connection;
	}
	/**
	 * Returns the initialized object store container.
	 *
	 * @return OpenCloud\ObjectStore\Resource\Container
	 */
	public function getContainer() {
		if (!is_null($this->container)) {
			return $this->container;
		}
		try {
			$this->container = $this->getConnection()->getContainer($this->bucket);
		} catch (ClientErrorResponseException $e) {
			$errorCode = $e->getResponse()->getStatusCode();
                        switch ($errorCode) {
				case 401:
					$this->retrieveCredentials();
					try {
						$this->container = $this->getConnection()->getContainer($this->bucket);
					} catch (ClientErrorResponseException $e) {
						$this->container = $this->getConnection()->createContainer($this->bucket);
					}
					break;
				case 404:
					$this->container = $this->getConnection()->createContainer($this->bucket);
					break;
				default:
					break;
			}
			
		}
		if (!$this->file_exists('.')) {
			$this->mkdir('.');
		}
		return $this->container;
	}	
}