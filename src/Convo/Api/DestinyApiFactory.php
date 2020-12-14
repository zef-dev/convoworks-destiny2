<?php declare(strict_types=1);

namespace Convo\Api;

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
			$this->_initManifest($apiKey);
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

	private function _initManifest($apiKey)
	{
		$url = BaseDestinyApi::BASE_URL . "/Platform/Destiny2/Manifest/";

		$this->_logger->debug('Fetching manifest from ['.$url.']');

		$client = $this->_httpFactory->getHttpClient();
		$manifest_request = $this->_httpFactory->buildRequest('GET', $url,
		[
			'Host' => BaseDestinyApi::BASE_URL
		]);

		$manifest_response = $client->sendRequest($manifest_request);

		$en_manifest_db_path = json_decode($manifest_response->getBody()->__toString())->Response->mobileWorldContentPaths->en;

		$en_manifest_db_req = $this->_httpFactory->buildRequest(
			'GET',
			BaseDestinyApi::BASE_URL.$en_manifest_db_path,
		[
			'x-api-key' => $apiKey
		]);

		$en_manifest_db_res = $client->sendRequest($en_manifest_db_req);

		$cacheFilePath = 'cache/'.pathinfo($en_manifest_db_path, PATHINFO_BASENAME);

		if (!file_exists(dirname($cacheFilePath))) {
			mkdir(dirname($cacheFilePath), 0777, true);
		}

		file_put_contents($cacheFilePath.'.zip', $en_manifest_db_res->getBody()->__toString());

		$zip = new \ZipArchive();
		if ($zip->open($cacheFilePath.'.zip') === TRUE) {
			$zip->extractTo('cache');
			$zip->close();
		}

		$tables = array();
		if ($db = new \SQLite3($cacheFilePath)) {
			$result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");

			while ($row = $result->fetchArray()) {
				$table = [];
				$pragma = $db->query("PRAGMA table_info(".$row['name'].")");

				while ($row_pragma = $pragma->fetchArray()) {
					$table[] = $row_pragma[1];
				}

				$tables[$row['name']] = $table;
			}
		}

		$this->_manifests = [
			'db' => $db,
			'tables' => $tables
		];
	}
}
