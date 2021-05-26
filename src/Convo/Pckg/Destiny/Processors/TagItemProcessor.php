<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Processors;

use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Workflow\IConversationProcessor;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Core\Workflow\IRequestFilterResult;
use Convo\Pckg\Core\Filters\ConvoIntentReader;
use Convo\Pckg\Core\Filters\IntentRequestFilter;
use Convo\Pckg\Core\Processors\AbstractServiceProcessor;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;

class TagItemProcessor extends AbstractServiceProcessor implements IConversationProcessor
{
    /**
     * @var \Convo\Core\Factory\PackageProviderFactory
     */
    private $_packageProviderFactory;

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

    private $_equipment;

    private $_errorMessageName;

    public function __construct($properties, $packageProviderFactory, $destinyApiFactory, $service)
    {
        parent::__construct($properties);

        $this->_packageProviderFactory = $packageProviderFactory;

        parent::__construct($properties);
        $this->setService($service);

        if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key');
        }
        $this->_apiKey = $properties['api_key'];

        if (!isset($properties['access_token'])) {
            throw new \Exception('Missing access token');
        }
        $this->_accessToken = $properties['access_token'];

        $this->_packageProviderFactory = $packageProviderFactory;
        $this->_destinyApiFactory = $destinyApiFactory;

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

        $this->_equipment = $properties['equipment'];

        $this->_errorMessageName = $properties['error_message_name'];

        $this->_requestFilters = $this->_initFilters();
    }

    private function _initFilters()
    {
        $fav_item_reader = new ConvoIntentReader(['intent' => 'convo-destiny.FavoriteItemIntent'], $this->_packageProviderFactory);
        $fav_item_reader->setLogger($this->_logger);
        $fav_item_reader->setService($this->getService());

        // $equip_tag_reader = new ConvoIntentReader(['intent' => 'convo-destiny.EquipTagIntent'], $this->_packageProviderFactory);
        // $equip_tag_reader->setLogger($this->_logger);
        // $equip_tag_reader->setService($this->getService());

        $config = [
            'readers' => [$fav_item_reader, /*$equip_tag_reader*/]
        ];

        $intent_filter = new IntentRequestFilter($config);
        $intent_filter->setLogger($this->_logger);
        $intent_filter->setService($this->getService());

        $this->addChild($intent_filter);
        return [$intent_filter];
    }

    public function process(IConvoRequest $request, IConvoResponse $response, IRequestFilterResult $result)
    {
        if (!is_a($request, '\Convo\Core\Workflow\IIntentAwareRequest')) {
	        throw new \Exception('This processor requires IIntentAwareRequest environment');
        }
        
        /** @var \Convo\Core\Workflow\IIntentAwareRequest $request */
        $provider = $this->_packageProviderFactory->getProviderFromPackageIds($this->getService()->getPackageIds());
        $sys_intent = $provider->findPlatformIntent($request->getIntentName(), $request->getPlatformId());

        $this->_logger->debug('Got sys intent ['.$sys_intent->getName().']['.$sys_intent.']');

        switch ($sys_intent->getName())
        {
            case 'FavoriteItemIntent':
                $item_name = $result->getSlotValue('WeaponName') ?? $result->getSlotValue('ArmorName');
                $this->_logger->info('Going to favorite ['.$item_name.']');
                $this->_favoriteItem(
                    $request, $response,
                    $item_name
                );
                break;
            // case 'EquipTagIntent':
            //     $this->_logger->info('Going to equip item tagged ['.$item_tag.']');
            //     $this->_equipTaggedItem(
            //         $request, $response,
            //         $item_tag
            //     );
            //     break;
            default:
                $this->_readErrorFlow($request, $response, "Sorry, I couldn't quite understand what you meant. Please try again.");

                return;
        }
    }

    private function _favoriteItem(IConvoRequest $request, IConvoResponse $response, $itemName)
    {
        $params = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_INSTALLATION);
        
        $itemName = strtolower(trim($itemName));

        $char_id = $this->evaluateString($this->_characterId);

        $stored_gear = $params->getServiceParam('stored_gear') ?? [];

        if (!isset($stored_gear[$char_id])) {
            $stored_gear[$char_id] = [
                "loadouts" => [],
                "favorites" => []
            ];

            $params->setServiceParam('stored_gear', $stored_gear);
        }

        /** @var array $equipment */
        $equipment = $this->evaluateString($this->_equipment);
        $found = null;

        foreach ($equipment as $item) {
            if (!in_array($item['bucketHash'], DestinyBucketEnum::EQUIPPABLE_GEAR)) {
                continue;
            }

            $equipment_name = strtolower(trim($item['manifest']['displayProperties']['name']));

            if ($equipment_name === $itemName) {
                $this->_logger->info('Found equipped item ['.$equipment_name.']');
                $found = ["name" => $equipment_name, "itemInstanceId" => $item["itemInstanceId"]];
                break;
            }
        }

        if (!$found) {
            $this->_readErrorFlow($request, $response, 'Sorry, you don\'t have '.$itemName.' equipped.');
            return;
        }

        $stored_gear[$char_id]["favorites"][$found["name"]] = $found["itemInstanceId"];

        $params->setServiceParam('stored_gear', $stored_gear);

        foreach ($this->_ok as $ok) {
            $ok->read($request, $response);
        }
    }

    private function _equipTaggedItem(IConvoRequest $request, IConvoResponse $response, $itemTag)
    {
        
    }

    private function _readErrorFlow(IConvoRequest $request, IConvoResponse $response, $message)
    {
        $err_name = $this->evaluateString($this->_errorMessageName);
        $params = $this->getService()->getServiceParams('session');
        $params->setServiceParam($err_name, $message);

        foreach ($this->_nok as $nok) {
            $nok->read($request, $response);
        }
    }
}