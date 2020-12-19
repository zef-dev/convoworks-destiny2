<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Catalogs;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;
use Convo\Pckg\Destiny\Service\DestinyManifestService;
use Convo\Core\Util\StrUtil;
use Convo\Core\Workflow\AbstractWorkflowComponent;

class ArmorNameCatalog extends AbstractWorkflowComponent  implements \Convo\Core\Workflow\ICatalogSource
{
	const CATALOG_VERSION = "3";

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	private $_httpFactory;

	private $_manifests;

	private $_apiKey;

	public function __construct($logger, $httpFactory, $apiKey)
	{
		$this->_logger = $logger;
		$this->_httpFactory = $httpFactory;
		$this->_apiKey = $apiKey;
	}

	public function getCatalogVersion()
	{
		return self::CATALOG_VERSION;
	}

	public function getCatalogValues($platform)
	{
		$mservice = new DestinyManifestService($this->_logger, $this->_httpFactory);
		$api_key = $this->evaluateString($this->_apiKey);
		$this->_manifests = $mservice->initManifest($api_key);

		switch ($platform)
		{
			case 'amazon':
				return $this->_getAmazonFormattedNames();
			case 'dialogflow':
				return $this->_getDialogflowFormattedNames();
			default:
				throw new \Exception("Unexpected platform [$platform]");
		}
	}

	private function _getAmazonFormattedNames()
	{
		$armors = $this->_getArmors();

		// TODO: synonymize names by removing underscores, numbers, definite articles (the), etc.

		$formatted = [
			'values' => array_map(function ($armor) {
				return [
					'id' => StrUtil::slugify($armor['displayProperties']['name']),
					'name' => [
						'value' => $armor['displayProperties']['name']
					]
				];
			}, $armors)
		];

		return $formatted;
	}

	private function _getDialogflowFormattedNames()
	{
		$armors = $this->_getArmors();

		return array_map(function ($armor) { return $armor['displayProperties']['name']; }, $armors);
	}

	private function _getArmors()
	{
		/** @var \SQLite3 $db */
		$db = $this->_manifests['db'];

		$results = [];
		$result = $db->query('SELECT * FROM '.BaseDestinyApi::ITEM_TABLE);
		while ($row = $result->fetchArray()) {
			$key = is_numeric($row[0]) ? sprintf('%u', $row[0] & 0xFFFFFFFF) : $row[0];
			$results[$key] = json_decode($row[1], true);
		}

		$armors = array_filter($results, function($item) {
			return in_array($item['inventory']['bucketTypeHash'], [
				DestinyBucketEnum::BUCKET_HELMETS,
				DestinyBucketEnum::BUCKET_GAUNTLETS,
				DestinyBucketEnum::BUCKET_CHEST_ARMOR,
				DestinyBucketEnum::BUCKET_GREAVES,
				DestinyBucketEnum::BUCKET_CLASS_ITEMS
			]);
		});

		$this->_logger->debug('Got ['.count($armors).'] armor pieces');

		return $armors;
	}
}