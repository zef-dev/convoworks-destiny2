<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Service;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;

class DestinyManifestService
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var \Convo\Core\Util\IHttpFactory
     */
    private $_httpFactory;

    public function __construct($logger, $httpFactory)
    {
        $this->_logger = $logger;
        $this->_httpFactory = $httpFactory;    
    }

    public function initManifest()
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

		$this->_logger->debug('Got manifest, fetching en path ['.$en_manifest_db_path.']');

		$en_manifest_db_req = $this->_httpFactory->buildRequest(
			'GET',
			BaseDestinyApi::BASE_URL.$en_manifest_db_path
		);

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

		return [
			'db' => $db,
			'tables' => $tables
		];
	}
}