<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Catalogs;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;
use Convo\Core\Util\StrUtil;
use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;

class WeaponNameCatalog extends AbstractWorkflowComponent  implements \Convo\Core\Workflow\ICatalogSource
{
    const CATALOG_VERSION = "2";

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var \Convo\Core\Util\IHttpFactory
     */
    private $_httpFactory;

    private $_apiKey;

    private $_manifests;

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
        $mservice = new \Convo\Pckg\Destiny\Service\DestinyManifestService($this->_logger, $this->_httpFactory);
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
        $weapons = $this->_getWeapons();

        $formatted = [
            'values' => []
        ];

        // TODO: synonymize names by removing underscores, numbers, definite articles (the), etc.
        $formatted['values'] = array_map(function ($weapon) {
            return [
                'id' => StrUtil::slugify($weapon['displayProperties']['name']),
                'name' => [
                    'value' => $weapon['displayProperties']['name']
                ],
                'synonyms' => $this->_nameToSynonyms($weapon['displayProperties']['name'])
            ];
        }, $weapons);

        return $formatted;
    }

    private function _nameToSynonyms($name)
    {
        $return = [$name];

        $return[] = str_ireplace(['the', '-', '.'], '', $name);

        return $return;
    }

    private function _getDialogflowFormattedNames()
    {
        $weapons = $this->_getWeapons();

        return array_map(function ($weapon) { return $weapon['displayProperties']['name']; }, $weapons);
    }

    private function _getWeapons()
	{
        $this->_logger->debug('Initializing weapons');

		/** @var \SQLite3 $db */
		$db = $this->_manifests['db'];

		$results = [];
		$result = $db->query('SELECT * FROM '.BaseDestinyApi::ITEM_TABLE);

		while ($row = $result->fetchArray()) {
			$key = is_numeric($row[0]) ? sprintf('%u', $row[0] & 0xFFFFFFFF) : $row[0];
			$results[$key] = json_decode($row[1], true);
		}

		$weapons = array_filter($results, function($item) {
			return in_array($item['inventory']['bucketTypeHash'], [
				DestinyBucketEnum::BUCKET_KINETIC_WEAPONS,
				DestinyBucketEnum::BUCKET_ENERGY_WEAPONS,
				DestinyBucketEnum::BUCKET_POWER_WEAPONS
			]);
		});

		$this->_logger->debug('Got ['.count($weapons).'] weapons');

		return $weapons;
	}
}