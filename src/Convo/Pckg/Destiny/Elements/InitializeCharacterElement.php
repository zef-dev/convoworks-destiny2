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
		$scope_type = $this->evaluateString($this->_scopeType);
		$params = $this->getService()->getServiceParams($scope_type);

    	$api_key = $this->evaluateString($this->_apiKey);
    	$acc_tkn = $this->evaluateString($this->_accessToken);

    	$capi = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);
		$iapi = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);

		$mstp = $this->evaluateString($this->_membershipType);
		$msid = $this->evaluateString($this->_membershipId);
		$chid = $this->evaluateString($this->_characterId);

		if (!is_array($this->_initComponents)) {
			$init_components = $this->evaluateString($this->_initComponents);
		} else {
			$init_components = $this->_initComponents;
		}

		if (!is_array($init_components)) {
			$this->_logger->warning('Did not get array for components to init, got ['.$init_components.'] instead.');
			return;
		}

        $this->_logger->info('Going to try to load character ['.$mstp.']['.$msid.']['.$chid.']['.print_r($init_components, true).']');

		$res = $capi->getCharacter($mstp, $msid, $chid, $this->_initComponents, true);
		$character = [
			'inventory' => [],
			'equipment' => [],
			'gear' => [],
			'vault' => [],
			'profile_inventory' => [],
			'full_profile_inventory' => []
		];

		$storage_name = $this->evaluateString($this->_storageName);

		$existing = $this->getService()->getServiceParams($scope_type)->getServiceParam($storage_name);
		if (!empty($existing)) {
			$this->_logger->debug('Got existing character to use');
			$character = $existing;
		}

        if (in_array(BaseDestinyApi::COMPONENT_CHARACTER_INVENTORY, $this->_initComponents)) {
			// deserialize inventory
			$character['inventory'] = [];
			$inventory = $res['Response']['inventory']['data']['items'] ?? [];

			$this->_logger->debug('Going to deserialize ['.count($inventory).'] inventory items');

			foreach ($inventory as $inventory_item) {
				try {
					$manifest = $iapi->getItemManifest($inventory_item['itemHash']);
					$ii = [
						'itemHash' => $inventory_item['itemHash'],
						'itemInstanceId' => $inventory_item['itemInstanceId'] ?? null,
						'bucketHash' => $inventory_item['bucketHash'] ?? null,
						'manifest' => [
							'displayProperties' => ['name' => $manifest['displayProperties']['name']]
						]
					];

					$character['inventory'][] = $ii;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			$this->_logger->debug('Deserialized ['.count($character['inventory']).'] inventory items');
		}

        if (in_array(BaseDestinyApi::COMPONENT_CHARACTER_EQUIPMENT, $this->_initComponents)) {
			// deserialize equipment
			$character['equipment'] = [];
			$equipment = $res['Response']['equipment']['data']['items'] ?? [];

			$this->_logger->debug('Going to deserialize ['.count($equipment).'] equipment items');

			foreach ($equipment as $equipment_item) {
				try {
					$manifest = $iapi->getItemManifest($equipment_item['itemHash']);
					$eqi = [
						'itemHash' => $equipment_item['itemHash'],
						'itemInstanceId' => $equipment_item['itemInstanceId'] ?? null,
						'bucketHash' => $equipment_item['bucketHash'] ?? null,
						'manifest' => [
							'displayProperties' => ['name' => $manifest['displayProperties']['name']]
						]
					];
					$character['equipment'][] = $eqi;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			$this->_logger->debug('Deserialized ['.count($character['equipment']).'] equipment items');
		}

		if (in_array(BaseDestinyApi::COMPONENT_PROFILE_INVENTORY, $this->_initComponents)) {
			// deserialize and separate profile inventory items
			$character['vault'] = [];
			$character['profile_inventory'] = [];
			$character['full_profile_inventory'] = [];
			
			$profile_response = $capi->getUserProfile($mstp, $msid, [BaseDestinyApi::COMPONENT_PROFILE_INVENTORY], true);
			$profile_items = $profile_response['Response']['profileInventory']['data']['items'] ?? [];

			$this->_logger->debug('Going to deserialize ['.count($profile_items).'] profile items');

			foreach ($profile_items as $profile_item) {
				try {
					$manifest = $iapi->getItemManifest($profile_item['itemHash']);
					$pi = [
						'itemHash' => $profile_item['itemHash'],
						'itemInstanceId' => $profile_item['itemInstanceId'] ?? null,
						'bucketHash' => $profile_item['bucketHash'] ?? null,
						'manifest' => [
							'displayProperties' => ['name' => $manifest['displayProperties']['name']]
						]
					];

					if ($pi['bucketHash'] === DestinyBucketEnum::BUCKET_VAULT) {
						$character['vault'][] = $pi;
					} else {
						$character['profile_inventory'][] = $pi;
					}

					$character['full_profile_inventory'][] = $pi;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			if (count($character['vault']) + count($character['profile_inventory']) !== count($character['full_profile_inventory'])) {
				throw new \Exception('Count for vault and profile inventory do not add up.');
			}

			$this->_logger->debug('Deserialized ['.count($character['profile_inventory']).'] profile inventory items not including ['.count($character['vault']).'] vault items');
		}

		if (!empty($character['gear'])) {
			$character['gear'] = [];
		}
		$character['gear'] = array_filter(array_merge($character['gear'], $character['inventory']), function($item) {
			return isset($item['bucketHash']) && in_array($item['bucketHash'], DestinyBucketEnum::EQUIPPABLE_GEAR);
		});

        $params->setServiceParam($storage_name, $character);
    }
}