<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Api;

use Convo\Pckg\Destiny\Service\DestinyManifestService;

class DestinyApiFactory
{
	const API_TYPE_CHARACTER = 'character';
	const API_TYPE_ITEM = 'item';

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

	private $_manifests;

	public function __construct($logger, $httpFactory, $cache)
	{
		$this->_logger = $logger;
		$this->_httpFactory = $httpFactory;
		$this->_cache = $cache;
	}

	/**
	 * @param $type
	 * @param $apiKey
	 * @param $accessToken
	 * @return CharacterApi|ItemApi
	 * @throws \Exception
	 */
	public function getApi($type, $apiKey, $accessToken)
	{
		if (!$this->_manifests) {
			$mservice = new DestinyManifestService($this->_logger, $this->_httpFactory);
			$this->_manifests = $mservice->initManifest();
		}

		switch ($type) {
			case self::API_TYPE_CHARACTER:
				$api = new CharacterApi($this->_httpFactory, $this->_manifests, $this->_cache, $apiKey, $accessToken);
				$api->setLogger($this->_logger);
				return $api;
			case self::API_TYPE_ITEM:
				$api = new ItemApi($this->_httpFactory, $this->_manifests, $this->_cache, $apiKey, $accessToken);
				$api->setLogger($this->_logger);
				return $api;
			default:
				throw new \Exception("Unexpected API type requested: [$type]");
		}
	}
}
