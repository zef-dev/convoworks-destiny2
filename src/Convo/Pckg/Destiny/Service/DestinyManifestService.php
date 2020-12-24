<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Service;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;

class DestinyManifestService
{
	private $_basePath;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var \Convo\Core\Util\IHttpFactory
     */
    private $_httpFactory;

    public function __construct($basePath, $logger, $httpFactory)
    {
		$this->_basePath = $basePath;
        $this->_logger = $logger;
        $this->_httpFactory = $httpFactory;    
    }

	/**
	 * Fetches and instantiates the current manifest DB
	 *
	 * @param string $apiKey
	 * @return \SQLite3
	 * @throws \Exception DB could not be instantiated.
	 */
    public function initManifest($apiKey)
	{
		$url = BaseDestinyApi::BASE_URL . "/Platform/Destiny2/Manifest/";

		$this->_logger->debug('Fetching manifest from ['.$url.']');

		$client = $this->_httpFactory->getHttpClient(['timeout' => 30]);
		$manifest_request = $this->_httpFactory->buildRequest('GET', $url,
		[
			'X-API-key' => $apiKey,
			'Host' => BaseDestinyApi::BASE_URL
		]);

		$manifest_response = $client->sendRequest($manifest_request);

		$en_manifest_db_path = json_decode($manifest_response->getBody()->__toString())->Response->mobileWorldContentPaths->en;
		$cacheFilePath = $this->_basePath.'/cache/'.pathinfo($en_manifest_db_path, PATHINFO_BASENAME);

		$this->_logger->debug('Checking cache file path ['.$cacheFilePath.']');

		if (file_exists($cacheFilePath.'.zip'))
		{
			$this->_logger->warning('No need to redownload manifest');
		}
		else
		{
			$this->_logger->debug('Got manifest, fetching en path ['.$en_manifest_db_path.']');

			$en_manifest_db_req = $this->_httpFactory->buildRequest(
				'GET',
				BaseDestinyApi::BASE_URL.$en_manifest_db_path
			);
	
			$en_manifest_db_res = $client->sendRequest($en_manifest_db_req);
	
			if (!file_exists(dirname($cacheFilePath))) {
				mkdir(dirname($cacheFilePath), 0744, true);
			}
	
			file_put_contents($cacheFilePath.'.zip', $en_manifest_db_res->getBody()->__toString());
		}

		$zip = new \ZipArchive();
		if ($zip->open($cacheFilePath.'.zip') === TRUE) {
			$zip->extractTo(dirname($cacheFilePath));
			$zip->close();
		}

		if ($db = new \SQLite3($cacheFilePath)) {
			return $db;
		}

		throw new \Exception('Could not instantiate manifest DB');
	}
}