<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Api;

use Convo\Core\Util\StrUtil;

abstract class BaseDestinyApi implements \Psr\Log\LoggerAwareInterface
{
	const BASE_URL = 'https://www.bungie.net';

	const ITEM_TABLE = 'DestinyInventoryItemDefinition';
	const PERK_TABLE = 'DestinySandboxPerkDefinition';

	const COMPONENT_PROFILE_INVENTORY = "102";
	
	const COMPONENT_CHARACTER_INVENTORY = "201";
	const COMPONENT_CHARACTER_EQUIPMENT = "205";

	const CACHE_DEFAULT_TTL = 3600;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger;

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	protected $_httpFactory;

	/**
	 * @var \SQLite3
	 */
	protected $_manifestDb;

	/**
	 * @var \Psr\SimpleCache\CacheInterface
	 */
	private $_cache;

	private $_apiKey;
	private $_accessToken;

	public function __construct(\Convo\Core\Util\IHttpFactory $httpFactory, $manifestDb, \Psr\SimpleCache\CacheInterface $cache, $apiKey, $accessToken)
	{
		$this->_logger = new \Psr\Log\NullLogger();
		$this->_httpFactory = $httpFactory;

		$this->_manifestDb = $manifestDb;

		$this->_cache = $cache;

		$this->_apiKey = $apiKey;
		$this->_accessToken = $accessToken;
	}

	public function setLogger(\Psr\Log\LoggerInterface $logger)
	{
		$this->_logger = $logger;
	}

	protected function _performRequest($uri, $method, $body = [], $invalidateCache = false)
	{
		$key = StrUtil::slugify($method.'_'.$uri);

		if ($method === 'GET' && !$invalidateCache && $this->_cache->has($key))
		{
			$this->_logger->debug('Cache hit for ['.$method.']['.$uri.']');
			return $this->_cache->get($key);
		}

		$client = $this->_httpFactory->getHttpClient();

		$req = $this->_httpFactory->buildRequest($method, $uri, $this->_getAuthHeaders($this->_apiKey, $this->_accessToken), $body);

		$response = $client->sendRequest($req);
		if ($response->getStatusCode() !== 200) {
			throw new \Exception('Request failed with status ['.$body['status'].']['.$response->getReasonPhrase().']');
		}

		$body = json_decode($response->getBody()->__toString(), true);
		$this->_cache->set($key, $body, self::CACHE_DEFAULT_TTL);
		return $body;
	}

	protected function _queryManifest($tableName, $id)
	{
		$where = ' WHERE '.(is_numeric($id) ? 'id='.$id.' OR id='.($id-4294967296) : "id=\"$id\"");
		$results = $this->_doQuery('SELECT * FROM '.$tableName.$where);

		$res = $results[$id] ?? false;

		return $res;
	}

	private function _doQuery($query)
	{
		$db = $this->_manifestDb;

		$results = [];
		$result = $db->query($query);

		while ($row = $result->fetchArray())
		{
			$key = is_numeric($row[0]) ? sprintf('%u', $row[0] & 0xFFFFFFFF) : $row[0];
			$results[$key] = json_decode($row[1], true);
		}

		return $results;
	}

	protected function _getAuthHeaders($apiKey, $accessToken)
	{
		return [
			'Authorization' => "Bearer $accessToken",
			'X-API-Key' => $apiKey
		];
	}
}
