<?php

namespace Perrich;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

// TO CHECK:
// If it does not work in Windows, please edit the GuzzleHttp\Client and replace true by false for 'verify' option

class HubicAuth 
{
	const HUBIC_URI_BASE = 'https://api.hubic.com';
	const HUBIC_SESSION_TOKEN = 'hubic_accessToken';
	const OAUTH_SESSION_TOKEN = 'hubic_oauth2state';
	
	var $client_id;
	var $client_secret;
	var $uri;
	
	var $hubic_token;
	
	public function __construct($client_id, $client_secret, $uri)
	{
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->uri = $uri;
		
		if (isset($_SESSION[static::HUBIC_SESSION_TOKEN])) {
			$this->hubic_token = new AccessToken(json_decode($_SESSION[static::HUBIC_SESSION_TOKEN], true));
		}
	}
	
	public function isAuthentificated()
	{
		return isset($this->hubic_token);
	}
	
	public function authentificate()
	{
		$provider = $this->create_provider();
		
		if (!isset($_GET['code']))
		{
			$authorizationUrl = $provider->getAuthorizationUrl();
			$_SESSION[self::OAUTH_SESSION_TOKEN] = urldecode($provider->getState());
			header('Location: ' . $authorizationUrl);
			exit;
		}
		
		if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION[self::OAUTH_SESSION_TOKEN]))
		{
			unset($_SESSION[self::OAUTH_SESSION_TOKEN]);
			exit('Invalid state');
		}
		
		try
		{
			$this->hubic_token = $provider->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);
			$_SESSION[static::HUBIC_SESSION_TOKEN] = json_encode($this->hubic_token);
			
			/*
			$resourceOwner = $provider->getResourceOwner($this->hubic_token);
			var_export($resourceOwner->toArray());
			*/
		} catch (IdentityProviderException $e)
		{
			// Failed to get the access token
			exit($e->getMessage());
		}		
	}
	
	public function getCredentials()
	{
		try {
			$resCred = $this->retrieveOpenStackCredentials();
		} catch (ClientErrorResponseException $e) {
			// assume token is no more valid
			$this->refreshToken();
			$resCred = $this->retrieveOpenStackCredentials();
		}
		return $resCred->getBody(TRUE);
	}
	
	private function refreshToken()
	{
		$provider = $this->create_provider();
		$refreshToken = $this->hubic_token->getRefreshToken();
		$this->hubic_token = $provider->getAccessToken('refresh_token', [
			'refresh_token' => $refreshToken
		]);
		
		// always keep the same refresh token
		$tbl = json_decode(json_encode($this->hubic_token), true);
		$tbl['refresh_token'] = $refreshToken;
		$this->hubic_token = new AccessToken($tbl);
		$_SESSION[static::HUBIC_SESSION_TOKEN] = json_encode($this->hubic_token);
	}
	
	private function retrieveOpenStackCredentials()
	{
		$access_token = $this->hubic_token->getToken();
		$hubicAuthClient = new Client(self::HUBIC_URI_BASE);
		$resCred = $hubicAuthClient->get('1.0/account/credentials',
			array('Authorization' => 'Bearer '.$access_token)
		)->send();
		return $resCred;
	}	
	
	private function create_provider() 
	{
		return new GenericProvider([
			'clientId'                => $this->client_id,
			'clientSecret'            => $this->client_secret,
			'redirectUri'             => $this->uri,
			'urlAuthorize'            => self::HUBIC_URI_BASE . '/oauth/auth',
			'urlAccessToken'          => self::HUBIC_URI_BASE . '/oauth/token',
			'urlResourceOwnerDetails' => self::HUBIC_URI_BASE . '/1.0/account',
			'scopes'                   => ['credentials.r', 'links.drw']
		]);
	}
}