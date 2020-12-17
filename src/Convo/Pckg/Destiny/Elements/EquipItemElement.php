<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Core\Workflow\AbstractWorkflowContainerComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;

class EquipItemElement extends AbstractWorkflowContainerComponent implements IConversationElement
{
    /**
     * Destiny API factory
     *
     * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
     */
    private $_destinyApiFactory;

    /**
     * @var IConversationElement[]
     */
    private $_ok;

    /**
     * @var IConversationElement[]
     */
    private $_nok;

    private $_apiKey;
    private $_accessToken;

    private $_itemInstanceId;
    private $_characterId;
    private $_membershipType;

    public function __construct($properties, $destinyApiFactory)
    {
        $this->_destinyApiFactory = $destinyApiFactory;

        $this->_ok = $properties['ok'] ?: [];
        foreach ($this->_ok as $ok) {
            $this->addChild($ok);
        }

        $this->_nok = $properties['nok'] ?: [];
        foreach ($this->_nok as $nok) {
            $this->addChild($nok);
        }

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key');
        }
        $this->_apiKey = $properties['api_key'];

        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token');
        }
        $this->_accessToken = $properties['access_token'];

        $this->_itemInstanceId = $properties['item_instance_id'];
        $this->_characterId = $properties['character_id'];
        $this->_membershipType = $properties['membership_type'];
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        /** @var \Convo\Pckg\Destiny\Api\CharacterApi $character_api */
        $character_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);

        $item_id = $this->evaluateString($this->_itemInstanceId);
        
        try
        {
            $character_api->equipItems(
                [$item_id],
                $this->evaluateString($this->_characterId),
                $this->evaluateString($this->_membershipType)
            );

            $this->_logger->debug('Item ['.$item_id.'] equipped');
            
            foreach ($this->_ok as $ok)
            {
                $ok->read($request, $response);
            }
        }
        catch (\Exception $e)
        {
            $this->_logger->error($e);

            foreach ($this->_nok as $nok)
            {
                $nok->read($request, $response);
            }
        }
    }
}