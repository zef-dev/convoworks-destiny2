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
		$formatted = [
            'values' => []
        ];

        foreach ($armors as $armor)
        {
			$id = strtoupper(StrUtil::slugify($armor));

			if (empty($id)) {
				$this->_logger->warning('Empty ID, skipping.');
				continue;
			}
			
            $a = [
                'id' => $id,
                'name' => [
                    'value' => $armor
                ],
                'synonyms' => $this->_nameToSynonyms($armor)
            ];

            $formatted['values'][] = $a;
        }

		return $formatted;
	}

	private function _nameToSynonyms($name)
    {
        $return = [];

        $return[] = trim(str_replace(['The', '-', '.', '/', '_'], ' ', $name));

        return $return;
    }

	private function _getDialogflowFormattedNames()
	{
		return $this->_getArmors();
	}

	private function _getArmors()
	{
		/** @var \SQLite3 $db */
		$db = $this->_manifests['db'];
		$query = 'SELECT DISTINCT value
        FROM DestinyInventoryItemDefinition as diid, json_each(diid.json, \'$.displayProperties.name\')
		WHERE
			diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_HELMETS.'%\' OR
			diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_GAUNTLETS.'%\' OR
			diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_CHEST_ARMOR.'%\' OR
			diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_GREAVES.'%\' OR
			diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_CLASS_ITEMS.'%\';';

		$result = $db->query($query);
		$armors = [];

		while ($row = $result->fetchArray()) {
            // $key = is_numeric($row[0]) ? sprintf('%u', $row[0] & 0xFFFFFFFF) : $row[0];
			$armors[] = $row[0];
		}

		$this->_logger->debug('Got ['.count($armors).'] armor pieces');

		return $armors;
	}
}