<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Catalogs;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;
use Convo\Core\Util\StrUtil;
use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;
use Convo\Pckg\Destiny\Service\DestinyManifestService;

class WeaponNameCatalog extends AbstractWorkflowComponent  implements \Convo\Core\Workflow\ICatalogSource
{
    const CATALOG_VERSION = "4";

    private $_basePath;
    /**
     * @var \Convo\Core\Util\IHttpFactory
     */
    private $_httpFactory;

    private $_apiKey;

    private $_manifestDb;

    public function __construct($basePath, $logger, $httpFactory, $apiKey)
    {
        $this->_basePath = $basePath;
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
        $mservice = new DestinyManifestService($this->_basePath, $this->_logger, $this->_httpFactory);
        $api_key = $this->evaluateString($this->_apiKey);
        $this->_manifestDb = $mservice->initManifest($api_key);

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

        foreach ($weapons as $weapon)
        {
            $id = strtoupper(StrUtil::slugify($weapon));

			if (empty($id)) {
				$this->_logger->warning('Empty ID, skipping.');
				continue;
            }
            
            $w = [
                'id' => $id,
                'name' => [
                    'value' => $weapon
                ],
                'synonyms' => $this->_nameToSynonyms($weapon)
            ];

            $formatted['values'][] = $w;
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
        return $this->_getWeapons();
    }

    private function _getWeapons()
	{
        $query = 'SELECT DISTINCT value
        FROM DestinyInventoryItemDefinition as diid, json_each(diid.json, \'$.displayProperties.name\')
        WHERE
            diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_KINETIC_WEAPONS.'%\' OR
            diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_ENERGY_WEAPONS.'%\' OR
            diid.json LIKE \'%'.DestinyBucketEnum::BUCKET_POWER_WEAPONS.'%\';';

		$result = $this->_manifestDb->query($query);
        $weapons = [];

		while ($row = $result->fetchArray()) {
			$weapons[] = $row[0];
		}

		$this->_logger->debug('Got ['.count($weapons).'] weapons');

		return $weapons;
	}
}