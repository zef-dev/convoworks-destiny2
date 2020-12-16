<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Pckg\Destiny\Api\BaseDestinyApi;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;
use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;

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

		if ($params->getServiceParam($key) !== null) { // TODO: store timestamp and check for expiry
			$this->_logger->debug('Data already set. Will not bust cache yet.');
			return;
		}

    	$api_key = $this->evaluateString($this->_apiKey);
    	$acc_tkn = $this->evaluateString($this->_accessToken);

    	$capi = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);
		$iapi = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);

		$mstp = $this->evaluateString($this->_membershipType);
		$msid = $this->evaluateString($this->_membershipId);
		$chid = $this->evaluateString($this->_characterId);

        $this->_logger->debug('Going to try to load character ['.$mstp.']['.$msid.']['.$chid.']['.print_r($this->_initComponents, true).']');

        $res = $capi->getCharacter($mstp, $msid, $chid, $this->_initComponents);
        $character = [];

        if (in_array(BaseDestinyApi::COMPONENT_CHARACTER_INVENTORY, $this->_initComponents)) {
        	// deserialize inventory
			$inventory = $res['Response']['inventory']['data']['items'];

			$this->_logger->debug('Going to deserialize ['.count($inventory).'] inventory items');

			$deserialized = [];

			foreach ($inventory as $inventory_item) {
				try {
					$item = [];

					$item['manifest'] = $iapi->getItemManifest($inventory_item['itemHash']);

					if (isset($inventory_item['itemInstanceId'])) {
						// $instance_data = $iapi->getItemInstance($mstp, $msid, $inventory_item['itemInstanceId']);
						// $item['instance_data'] = $instance_data['Response'];
						$this->_logger->debug('Inventory item has instance ID ['.$inventory_item['itemInstanceId'].']. Storing for future use.');
						$item['itemInstanceId'] = $inventory_item['itemInstanceId'];
					}

					$deserialized[] = $item;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			$character['inventory'] = $deserialized;
			$this->_logger->debug('Deserialized ['.count($character['inventory']).'] inventory items');
		}

        if (in_array(BaseDestinyApi::COMPONENT_CHARACTER_EQUIPMENT, $this->_initComponents)) {
        	// deserialize equipment
			$equipment = $res['Response']['equipment']['data']['items'];

			$this->_logger->debug('Going to deserialize ['.count($equipment).'] equipment items');

			$deserialized = [];

			foreach ($equipment as $equipment_item) {
				try {
					$item = [];

					$item['manifest'] = $iapi->getItemManifest($equipment_item['itemHash']);

					if (isset($equipment_item['itemInstanceId'])) {
						// $instance_data = $iapi->getItemInstance($mstp, $msid, $equipment_item['itemInstanceId']);
						// $item['instance_data'] = $instance_data['Response'];
						$item['itemInstanceId'] = $equipment_item['itemInstanceId'];
					}

					$deserialized[] = $item;
				} catch (\Exception $e) {
					$this->_logger->error($e);
					continue;
				}
			}

			$character['equipment'] = $deserialized;
			$this->_logger->debug('Deserialized ['.count($character['equipment']).'] equipment items');
		}

        $params->setServiceParam($this->evaluateString($this->_storageName), $character);
    }
}