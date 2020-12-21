<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;
use Convo\Pckg\Destiny\Enums\DestinyEnergyEnum;

class ItemInstanceElement extends AbstractWorkflowComponent implements IConversationElement
{
    /**
     * Destiny API Factory
     *
     * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
     */
    private $_destinyApiFactory;

    private $_apiKey;
    private $_accessToken;

    private $_membershipType;
    private $_membershipId;

    private $_itemInstanceId;

    private $_scopeType;
    private $_storageName;

    public function __construct($properties, $destinyApiFactory)
    {
        $this->_destinyApiFactory = $destinyApiFactory;

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key in properties');
        }
        $this->_apiKey = $properties['api_key'];

        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token in properties');
        }
        $this->_accessToken = $properties['access_token'];

        $this->_membershipType = $properties['membership_type'];
        $this->_membershipId = $properties['membership_id'];

        $this->_itemInstanceId = $properties['item_instance_id'];

        $this->_scopeType = $properties['scope_type'];
        $this->_storageName = $properties['storage_name'];
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        /** @var \Convo\Pckg\Destiny\Api\ItemApi $item_api */
        $item_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);

        $item_id = $this->evaluateString($this->_itemInstanceId);

        $instance = $item_api->getItemInstance(
            $this->evaluateString($this->_membershipType),
            $this->evaluateString($this->_membershipId),
            $item_id
        );

        $this->_logger->debug('Got item instance ['.print_r($instance, true).']');

        if (isset($instance['Response']['perks']['data']['perks'])) {
            foreach ($instance['Response']['perks']['data']['perks'] as &$perk)
            {
                try {
                    $this->_logger->debug('Working with perk ['.print_r($perk, true).']');
                    if (!$perk['visible']) {
                        $this->_logger->warning('Perk not considered visible. Skipping.');
                        continue;
                    }
                    
                    $perk_definition = $item_api->getPerkManifest($perk['perkHash']);
        
                    if (!isset($perk_definition['displayProperties']['name']) || empty($perk_definition['displayProperties']['name'])) {
                        $this->_logger->warning('This perk has no name. Skipping');
                        continue;
                    }
        
                    $perk['manifest'] = $perk_definition;
                } catch (\Exception $e) {
                    $this->_logger->error($e);
                    continue;
                }
            }

            $instance['Response']['perks']['data']['perks'] = array_values(
                array_filter(
                    $instance['Response']['perks']['data']['perks'],
                    function($perk)
                    {
                        return isset($perk['manifest']['displayProperties']['name']) &&
                        !empty($perk['manifest']['displayProperties']['name']);
                    }
                )
            );
        }

        if (isset($instance['Response']['instance']['data']['energy'])) {
            $instance['Response']['instance']['data']['energy']['energyType'] = DestinyEnergyEnum::ENERGY_TYPES[$instance['Response']['instance']['data']['energy']['energyType']];
        }

        $params = $this->getService()->getServiceParams($this->_scopeType);
        $params->setServiceParam($this->evaluateString($this->_storageName), $instance['Response']);
    }
}