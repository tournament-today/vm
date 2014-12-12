<?php namespace Syn\Vm\Providers\GoogleComputeEngine;

use Config;

use Google_Auth_AssertionCredentials;
use Google_Client;
use Google_Service_Compute;

class Compute
{
	/**
	 * @var null
	 */
	private static $service = null;

	private static $service_token = null;


	/**
	 * constructs google api client
	 */
	private function __construct()
	{
		$client = new Google_Client();

		$client -> setApplicationName(Config::get('app.safe_name'));

		$this -> authenticate($client);

		static::$service = new Google_Service_Compute($client);

	}

	private function authenticate(Google_Client $client)
	{
		$key = file_get_contents(Config::get('vm::google-compute-engine.service-account.key-path'));
		$cred = new Google_Auth_AssertionCredentials(
			Config::get('vm::google-compute-engine.service-account.email-address'),
			['https://www.googleapis.com/auth/compute'],
			$key
		);
		$client->setAssertionCredentials($cred);
		if ($client->getAuth()->isAccessTokenExpired()) {
			$client->getAuth()->refreshTokenWithAssertion($cred);
		}
		static::$service_token = $client->getAccessToken();
	}

	/**
	 * @return null
	 */
	public static function getInstance()
	{
		if(is_null(static::$service))
			new Compute;

		return static::$service;
	}
}