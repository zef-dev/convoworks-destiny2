<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;
use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;

class InitializeCharacterElement extends AbstractWorkflowComponent implements IConversationElement
{
    private $_accessToken;
    private $_apiKey;

    private $_characterId;
    private $_membershipType;
    private $_membershipId;
    private $_initComponents;

    private $_scopeType;
    private $_storageName;

	/**
	 * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
	 */
	private $_destinyApiFactory;

    public function __construct($properties, $destinyApiFactory)
    {
		parent::__construct($properties);

        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token in element properties.');
        }

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key in element properties.');
        }

        $this->_accessToken = $properties['access_token'];
        $this->_apiKey = $properties['api_key'];

        $this->_characterId = $properties['character_id'] ?: '';
        $this->_membershipType =  $properties['membership_type'] ?: '';
        $this->_membershipId =  $properties['membership_id'] ?: '';
        $this->_initComponents = $properties['init_components'] ?: [];

        $this->_scopeType = $properties['scope_type'] ?: IServiceParamsScope::SCOPE_TYPE_SESSION;
        $this->_storageName = $properties['storage_name'] ?: 'character';

        $this->_destinyApiFactory = $destinyApiFactory;
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
		$params = $this->getService()->getServiceParams($this->_scopeType);
		$key = $this->evaluateString($this->_storageName);

		// if ($params->getServiceParam($key) !== null) { // TODO: store timestamp and check for expiry
		// 	$this->_logger->debug('Data already set. Will not bust cache yet.');
		// 	return;
		// }

    	$api_key = $this->evaluateString($this->_apiKey);
    	$acc_tkn = $this->evaluateString($this->_accessToken);

    	$capi = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);
		$iapi = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);

		$mstp = $this->evaluateString($this->_membershipType);
		$msid = $this->evaluateString($this->_membershipId);
		$chid = $this->evaluateString($this->_characterId);

        $this->_logger->debug('Going to try to load character ['.$mstp.']['.$msid.']['.$chid.']['.print_r($this->_initComponents, true).']');

        $res = $capi->getCharacter($mstp, $msid, $chid, $this->_initComponents, true);
        $character = [
			'inventory' => [],
			'equipment' => [],
			'gear' => []
		];

        if (in_array(BaseDestinyApi::COMPONENT_CHARACTER_INVENTORY, $this->_initComponents)) {
        	// deserialize inventory
			$inventory = $res['Response']['inventory']['data']['items'];

			$this->_logger->debug('Going to deserialize ['.count($inventory).'] inventory items');

			$deserialized_inventory = [];

			foreach ($inventory as $inventory_item) {
				try {
					$manifest = $iapi->getItemManifest($inventory_item['itemHash']);
					$item = [
						'base' => [
							'itemHash' => $inventory_item['itemHash'],
							'bucketHash' => $inventory_item['bucketHash']
						],
						'manifest' => [
							'displayProperties' => ['name' => $manifest['displayProperties']['name']]
						]
					];

					if (isset($inventory_item['itemInstanceId'])) {
						$item['base']['itemInstanceId'] = $inventory_item['itemInstanceId'];
					}

					$deserialized_inventory[] = $item;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			$character['inventory'] = $deserialized_inventory;
			$character['gear'] = array_merge($character['gear'], $character['inventory']);

			$this->_logger->debug('Deserialized ['.count($character['inventory']).'] inventory items');
		}

        if (in_array(BaseDestinyApi::COMPONENT_CHARACTER_EQUIPMENT, $this->_initComponents)) {
        	// deserialize equipment
			$equipment = $res['Response']['equipment']['data']['items'];

			$this->_logger->debug('Going to deserialize ['.count($equipment).'] equipment items');

			$deserialized_equipment = [];

			foreach ($equipment as $equipment_item) {
				try {
					$manifest = $iapi->getItemManifest($equipment_item['itemHash']);
					$item = [
						'base' => [
							'itemHash' => $equipment_item['itemHash'],
							'itemInstanceId' => $equipment_item['itemInstanceId'],
							'bucketHash' => $equipment_item['bucketHash']
						],
						'manifest' => [
							'displayProperties' => ['name' => $manifest['displayProperties']['name']]
						]
					];
					$deserialized_equipment[] = $item;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			$character['equipment'] = $deserialized_equipment;
			$character['gear'] = array_merge($character['gear'], $character['equipment']);

			$this->_logger->debug('Deserialized ['.count($character['equipment']).'] equipment items');
		}

		$character['gear'] = array_filter($character['gear'], function($item) {
			return in_array($item['base']['bucketHash'], DestinyBucketEnum::EQUIPPABLE_GEAR);
		});

        $params->setServiceParam($this->evaluateString($this->_storageName), $character);
    }
}