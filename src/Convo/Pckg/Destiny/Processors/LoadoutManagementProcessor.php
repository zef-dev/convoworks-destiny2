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
use Convo\Pckg\Destiny\Api\BaseDestinyApi;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;

class LoadoutManagementProcessor extends AbstractServiceProcessor implements IConversationProcessor
{
    /**
     * @var \Convo\Core\Factory\PackageProviderFactory
     */
    private $_packageProviderFactory;

    private $_destinyApiFactory;

    private $_apiKey;
    private $_accessToken;

    private $_characterId;
    private $_membershipId;
    private $_membershipType;
    
    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_ok;    

    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_nok;

    public function __construct($properties, $packageProviderFactory, $destinyApiFactory, $service)
    {
        parent::__construct($properties);
        $this->setService($service);

        $this->_packageProviderFactory = $packageProviderFactory;
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
        $this->_membershipId = $properties['membership_id'];
        $this->_membershipType = $properties['membership_type'];

        $this->_errorMessageName = $properties['error_message_name'] ?: 'errorMsg';

        $this->_requestFilters = $this->_initFilters();
    }

    private function _initFilters()
    {
        $save_loadout = new ConvoIntentReader(['intent' => 'convo-destiny.SaveLoadoutIntent'], $this->_packageProviderFactory);
        $save_loadout->setLogger($this->_logger);
        $save_loadout->setService($this->getService());

        $equip_loadout = new ConvoIntentReader(['intent' => 'convo-destiny.EquipLoadoutIntent'], $this->_packageProviderFactory);
        $equip_loadout->setLogger($this->_logger);
        $equip_loadout->setService($this->getService());

        $config = [
            'readers' => [$save_loadout, $equip_loadout]
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
        $provider = $this->_packageProviderFactory->getProviderFromPackageIds(
            $this->getService()->getPackageIds()
        );
        
        $sys_intent = $provider->findPlatformIntent(
            $request->getIntentName(),
            $request->getPlatformId()
        );

        $loadout_name = $result->getSlotValue('LoadoutName');

        switch ($sys_intent->getName())
        {
            case 'SaveLoadoutIntent':
                $this->_logger->info('Saving current equipment as ['.$loadout_name.'] loadout');
                $this->_saveLoadout(
                    $request, $response,
                    $loadout_name
                );
                break;
            case 'EquipLoadoutIntent':
                $this->_logger->info('Going to equip loadout ['.$loadout_name.']');
                $this->_equipLoadout(
                    $request, $response,
                    $loadout_name
                );
                break;
            default:
                $this->_readErrorFlow($request, $response, "Sorry, I couldn't quite understand what you meant. Please try again.");

                return;
        }
    }

    private function _saveLoadout(IConvoRequest $request, IConvoResponse $response, $loadoutName)
    {
        $params = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_INSTALLATION);
        
        if (!$params->getServiceParam('stored_gear')) {
            $params->setServiceParam('stored_gear', '{"loadouts":[],"tags":[]}');
        }

        $data = json_decode($params->getServiceParam('stored_gear'), true);
        $loadouts = $data['loadouts'];
        
        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        /** @var \Convo\Pckg\Destiny\Api\CharacterApi $character_api */
        $character_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);

        /** @var \Convo\Pckg\Destiny\Api\ItemApi $item_api */
        $item_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);

        $char_id = $this->evaluateString($this->_characterId);
        $membership_id = $this->evaluateString($this->_membershipId);
        $membership_type = $this->evaluateString($this->_membershipType);

        $char = $character_api->getCharacter(
            $membership_type, $membership_id, $char_id, [BaseDestinyApi::COMPONENT_CHARACTER_EQUIPMENT]
        );

        $equipment = $char['Response']['equipment']['data']['items'] ?: [];

        $loadout = [
            'name' => $loadoutName,
            'items' => []
        ];
        foreach ($equipment as $item) {
            if ($this->_shouldStoreInLoadout($item)) {
                $manifest = $item_api->getItemManifest($item['itemHash']);

                $loadout['items'][] = [
                    'instance_id' => $item['itemInstanceId'],
                    'name' => $manifest['displayProperties']['name'],
                    'is_exotic' => $manifest['inventory']['tierTypeName'] === 'Exotic'
                ];
            }
        }

        $loadouts[$loadoutName] = $loadout;
        $data['loadouts'] = $loadouts;

        $params->setServiceParam('stored_gear', json_encode($data));

        $this->_logger->info('Loadout saved. Reading OK flow.');

        foreach ($this->_ok as $ok) {
            $ok->read($request, $response);
        }
    }
    
    private function _equipLoadout(IConvoRequest $request, IConvoResponse $response, $loadoutName)
    {
        $params = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_INSTALLATION);

        if (!$params->getServiceParam('stored_gear')) {
            $params->setServiceParam('stored_gear', '{"loadouts":[],"tags":[]}');
            $this->_readErrorFlow($request, $response, "Sorry, you don't have a loadout saved under the name \"$loadoutName\".");
            return;
        }

        $data = json_decode($params->getServiceParam('stored_gear'), true);
        $loadouts = $data['loadouts'];

        if (!isset($loadouts[$loadoutName])) {
            $this->_readErrorFlow($request, $response, "Sorry, you don't have a loadout saved under the name \"$loadoutName\".");
            return;
        }

        $loadout = $loadouts[$loadoutName];

        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        /** @var \Convo\Pckg\Destiny\Api\CharacterApi $character_api */
        $character_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);

        $char_id = $this->evaluateString($this->_characterId);
        $membership_type = $this->evaluateString($this->_membershipType);

        try {
            $items = $loadout['items'];
            usort($items, function ($itemA, $itemB) {
                if ($itemA['is_exotic'] && $itemB['is_exotic']) {
                    return 0;
                }
                
                if ($itemA['is_exotic'] && !$itemB['is_exotic']) {
                    return 1;
                }
                
                if (!$itemA['is_exotic'] && $itemB['is_exotic']) {
                    return -1;
                }
            });

            $items = array_map(function ($item) { return $item['instance_id']; }, $items);

            $character_api->equipItems($items, $char_id, $membership_type);
        } catch (\Exception $e) {
            $this->_readErrorFlow($request, $response, "Something went wrong while equipping loadout \"$loadoutName\".");
            return;
        }

        $this->_logger->info('Loadout equipped. Reading OK flow.');

        foreach ($this->_ok as $ok) {
            $ok->read($request, $response);
        }
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

    private function _shouldStoreInLoadout($item)
    {
        return in_array($item['bucketHash'], array_merge(
            DestinyBucketEnum::EQUIPPABLE_GEAR,
            [
                DestinyBucketEnum::BUCKET_GHOST_SHELL,
                DestinyBucketEnum::BUCKET_SUBCLASS
            ]
        ));
    }
}