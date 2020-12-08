<?php declare(strict_types=1);

namespace Convo\Api;

use Psr\Http\Client\ClientExceptionInterface;

abstract class BaseDestinyApi implements \Psr\Log\LoggerAwareInterface
{
	const BASE_URL = 'https://www.bungie.net/Platform';

	const COMPONENT_CHARACTER_INVENTORY = "201";
	const COMPONENT_CHARACTER_EQUIPMENT = "205";

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger;

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	protected $_httpFactory;

	private $_apiKey;
	private $_accessToken;

	public function __construct(\Convo\Core\Util\IHttpFactory $httpFactory, $apiKey, $accessToken)
	{
		$this->_logger = new \Psr\Log\NullLogger();
		$this->_httpFactory = $httpFactory;

		$this->_apiKey = $apiKey;
		$this->_accessToken = $accessToken;
	}

	public function setLogger(\Psr\Log\LoggerInterface $logger)
	{
		$this->_logger = $logger;
	}

	protected function _performRequest($uri, $method, $body = [])
	{
		$client = $this->_httpFactory->getHttpClient();

		$req = $this->_httpFactory->buildRequest($method, $uri, $this->_getAuthHeaders($this->_apiKey, $this->_accessToken), $body);

		try {
			$response = $client->sendRequest($req);

			$body = json_decode($response->getBody()->__toString(), true);
			return $body;
		} catch (ClientExceptionInterface $e) {
			$this->_logger->error($e);
			$response = [
				'errorMsg' => $e->getMessage()
			];
			return $response;
		}
	}

	protected function _getAuthHeaders($apiKey, $accessToken)
	{
		return [
			'Authorization' => "Bearer $accessToken",
			'X-API-Key' => $apiKey
		];
	}
}
