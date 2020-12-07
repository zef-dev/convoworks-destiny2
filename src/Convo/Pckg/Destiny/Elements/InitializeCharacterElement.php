<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Elements;

use Convo\Api\CharacterApi;
use Convo\Api\ItemApi;
use Convo\Core\Workflow\AbstractWorkflowComponent;
use Convo\Core\Workflow\IConversationElement;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;

class InitializeCharacterElement extends AbstractWorkflowComponent implements IConversationElement
{
    private $_accessToken;
    private $_apiKey;

    private $_characterId;

	/**
	 * @var \Convo\Api\CharacterApi;
	 */
	private $_characterApi;

	/**
	 * @var \Convo\Api\ItemApi;
	 */
	private $_itemApi;

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	private $_httpFactory;

    public function __construct($properties,  $httpFactory)
    {
        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token in element properties.');
        }

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key in element properties.');
        }

        $this->_characterApi = new CharacterApi($this->_logger, $this->_httpFactory, $this->_apiKey, $this->_accessToken);
        $this->_itemApi = new ItemApi($this->_logger, $this->_httpFactory, $this->_apiKey, $this->_accessToken);

        $this->_characterId = $properties['character_id'] ?: '';
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
        
    }
}