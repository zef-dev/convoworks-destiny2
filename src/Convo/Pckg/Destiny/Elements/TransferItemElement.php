<?php

declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Workflow\AbstractWorkflowContainerComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;

class TransferItemElement extends AbstractWorkflowContainerComponent implements IConversationElement
{
    /**
     * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
     */
    private $_destinyApiFactory;

    private $_apiKey;
    private $_accessToken;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_ok;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_nok;

    private $_characterId;
    private $_membershipType;

    private $_item;
    private $_transferToVault;

    private $_errorMessageName;

    public function __construct($properties, $destinyApiFactory)
    {
        $this->_destinyApiFactory = $destinyApiFactory;

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key');
        }
        $this->_apiKey = $properties['api_key'];

        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token');
        }
        $this->_accessToken = $properties['access_token'];

        $this->_ok = $properties['ok'] ?: [];
        foreach ($this->_ok as $ok) {
            $this->addChild($ok);
        }

        $this->_nok = $properties['nok'] ?: [];
        foreach ($this->_nok as $nok) {
            $this->addChild($nok);
        }

        $this->_characterId = $properties['character_id'];
        $this->_membershipType = $properties['membership_type'];

        $this->_item = $properties['item'];
        $this->_transferToVault = $properties['transfer_to_vault'];

        $this->_errorMessageName = $properties['error_message_name'];
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        $char_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);
        $item = $this->evaluateString($this->_item);

        try {
            $char_api->transferItem(
                $item['base']['itemHash'],
                1,
                boolval($this->evaluateString($this->_transferToVault)),
                $item['base']['itemInstanceId'],
                $this->evaluateString($this->_characterId),
                $this->evaluateString($this->_membershipType),
            );

            foreach ($this->_ok as $ok) {
                $ok->read($request, $response);
            }

            return;
        } catch (\Exception $e) {
            $this->_logger->error($e);

            if (method_exists($e, 'getResponse')) {
                // can get response
                $this->_logger->debug('Can get response off of error');
                $res = json_decode($e->getResponse()->getBody()->__toString(), true);

                $this->_logger->debug('Got response [' . print_r($res, true) . ']');

                if (isset($res['Message'])) {
                    $err_name = $this->evaluateString($this->_errorMessageName);
                    $this->_logger->debug('Setting message [' . $res['Message'] . '] under the name [' . $err_name . ']');

                    $params = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_SESSION);
                    $params->setServiceParam($err_name, $res['Message']);
                }
            }

            foreach ($this->_nok as $nok) {
                $nok->read($request, $response);
            }

            return;
        }
    }
}
