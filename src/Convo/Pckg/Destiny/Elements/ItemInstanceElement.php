<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;

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

    private $_itemInstanceIds;

    private $_scopeType;
    private $_storageName;

    public function __construct($properties, $destinyApiFactory)
    {
        $this->_destinyApiFactory = $destinyApiFactory;

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key in properties');
        }

        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token in properties');
        }

        $this->_membershipType = $properties['membership_type'];
        $this->_membershipId = $properties['membership_id'];

        $this->_itemInstanceIds = $properties['item_instance_ids'];

        $this->_scopeType = $properties['scope_type'];
        $this->_storageName = $properties['storage_name'];
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        $item_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);

        $item_ids = $this->evaluateString($this->_itemInstanceIds);
        $instances = [];

        $item_ids = explode(',', $item_ids); 

        foreach ($item_ids as $item_id) {
            $instance = $item_api->getItemInstance(
                $this->evaluateString($this->_membershipType),
                $this->evaluateString($this->_membershipId),
                $item_id);

            $instances[] = new \Convo\Pckg\Destiny\ItemInstance($instance['Response']);
        }

        $params = $this->getService()->getServiceParams($this->_scopeType);
        $params->setServiceParam($this->evaluateString($this->_storageName), $instances);
    }
}