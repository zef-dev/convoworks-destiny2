<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Api;

use Convo\Pckg\Destiny\Service\DestinyManifestService;

class DestinyApiFactory
{
	const API_TYPE_CHARACTER = 'character';
	const API_TYPE_ITEM = 'item';

	private $_basePath;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $_logger;

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	private $_httpFactory;

	/**
	 * @var \Psr\SimpleCache\CacheInterface
	 */
	private $_cache;

	private $_manifestDb;

	public function __construct($basePath, $logger, $httpFactory, $cache)
	{
		$this->_basePath = $basePath;
		$this->_logger = $logger;
		$this->_httpFactory = $httpFactory;
		$this->_cache = $cache;
	}

	/**
	 * @param string $type
	 * @param string $apiKey
	 * @param string $accessToken
	 * @return CharacterApi|ItemApi
	 * @throws \Exception
	 */
	public function getApi($type, $apiKey, $accessToken)
	{
		if (!$this->_manifestDb) {
			$mservice = new DestinyManifestService($this->_basePath, $this->_logger, $this->_httpFactory);
			$this->_manifestDb = $mservice->initManifest($apiKey);
		}

		switch ($type) {
			case self::API_TYPE_CHARACTER:
				$api = new CharacterApi($this->_httpFactory, $this->_manifestDb, $this->_cache, $apiKey, $accessToken);
				$api->setLogger($this->_logger);
				return $api;
			case self::API_TYPE_ITEM:
				$api = new ItemApi($this->_httpFactory, $this->_manifestDb, $this->_cache, $apiKey, $accessToken);
				$api->setLogger($this->_logger);
				return $api;
			default:
				throw new \Exception("Unexpected API type requested: [$type]");
		}
	}
}
