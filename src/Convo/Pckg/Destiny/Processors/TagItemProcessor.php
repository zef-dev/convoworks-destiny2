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
use Convo\Pckg\Destiny\Api\DestinyApiFactory;
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

    private $_characterId;
    private $_membershipType;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_ok;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_tagFavoriteDuplicates;

    private $_tagFavoriteDuplicatesStorage;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_equipFavoriteDuplicates;

    private $_equipFavoriteDuplicatesStorage;

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

        $this->_characterId = $properties['character_id'];
        $this->_membershipType = $properties['membership_type'];

        $this->_packageProviderFactory = $packageProviderFactory;
        $this->_destinyApiFactory = $destinyApiFactory;

        $this->_ok = $properties['ok'] ?: [];
        foreach ($this->_ok as $ok) {
            $this->addChild($ok);
        }

        $this->_tagFavoriteDuplicates = $properties['tag_favorite_duplicates'] ?? [];
        foreach ($this->_tagFavoriteDuplicates as $tag_dupe) {
            $this->addChild($tag_dupe);
        }
        $this->_tagFavoriteDuplicatesStorage = $properties['tag_favorite_duplicates_storage'];

        $this->_equipFavoriteDuplicates = $properties['equip_favorite_duplicates'] ?? [];
        foreach ($this->_equipFavoriteDuplicates as $equip_dupe) {
            $this->addChild($equip_dupe);
        }
        $this->_equipFavoriteDuplicatesStorage = $properties['equip_favorite_duplicates_storage'];

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

        $equip_fav_reader = new ConvoIntentReader(['intent' => 'convo-destiny.EquipFavoriteIntent'], $this->_packageProviderFactory);
        $equip_fav_reader->setLogger($this->_logger);
        $equip_fav_reader->setService($this->getService());

        $config = [
            'readers' => [$fav_item_reader, $equip_fav_reader]
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

        try {
            $item_name = $result->getSlotValue('WeaponName');
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());

            try {
                $item_name = $result->getSlotValue('ArmorName');
            } catch (\Exception $e) {
                $this->_logger->error($e->getMessage());

                $this->_readErrorFlow($request, $response, 'I couldn\'t determine which item you meant. Please try again later.');
                return;
            }
        }

        switch ($sys_intent->getName())
        {
            case 'FavoriteItemIntent':
                $this->_logger->info('Going to favorite ['.$item_name.']');
                $this->_favoriteItem(
                    $request, $response,
                    $item_name
                );
                break;
            case 'EquipFavoriteIntent':
                $this->_logger->info('Going to equip favorite ['.$item_name.']');
                $this->_equipFavoriteItem(
                    $request, $response,
                    $item_name
                );
                break;
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
        $found = [];

        foreach ($equipment as $item) {
            if (!in_array($item['bucketHash'], DestinyBucketEnum::EQUIPPABLE_GEAR)) {
                continue;
            }

            $equipment_name = strtolower(trim($item['manifest']['displayProperties']['name']));

            if ($equipment_name === $itemName) {
                $this->_logger->info('Found equipped item ['.$equipment_name.']');
                $found[] = $item;
            }
        }

        if (count($found) === 0)
        {
            $this->_readErrorFlow($request, $response, 'Sorry, you don\'t have '.$itemName.' equipped.');
        }
        else if (count($found) === 1)
        {
            $stored_gear[$char_id]["favorites"][] = $found[0];

            $params->setServiceParam('stored_gear', $stored_gear);
    
            foreach ($this->_ok as $ok) {
                $ok->read($request, $response);
            }
        }
        else if (count($found) > 1)
        {
            $this->_logger->info('Could favorite ['.print_r($found, true).']');

            $dupe_name = $this->evaluateString($this->_tagFavoriteDuplicatesStorage);

            $session_params = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_SESSION);
            $session_params->setServiceParam($dupe_name, $found);

            foreach ($this->_tagFavoriteDuplicates as $tfd) {
                $tfd->read($request, $response);
            }
        }
    }

    private function _equipFavoriteItem(IConvoRequest $request, IConvoResponse $response, $itemName)
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

        $found = [];

        if (count($stored_gear[$char_id]["favorites"]) === 0) {
            $this->_readErrorFlow($request, $response, 'Sorry, you don\'t have a favorite '.$itemName.'.');
            return;
        }

        foreach ($stored_gear[$char_id]["favorites"] as $favorite) {
            $fav_name = strtolower(trim($favorite["manifest"]["displayProperties"]["name"]));

            if ($fav_name === $itemName) {
                $found[] = $favorite;
            }
        }

        $api_key = $this->evaluateString($this->_apiKey);
        $access_token = $this->evaluateString($this->_accessToken);

        $char_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $access_token);

        $membership_type = $this->evaluateString($this->_membershipType);

        if (count($found) === 0)
        {
            $this->_readErrorFlow($request, $response, "You don't have a favorite $itemName.");
            return;
        }
        else if (count($found) === 1)
        {
            try
            {
                $char_api->equipItems(
                    [$found[0]["itemInstanceId"]],
                    $char_id, $membership_type
                );
        
                foreach ($this->_ok as $ok) {
                    $ok->read($request, $response);
                }
            }
            catch (\Exception $e)
            {
                $this->_logger->error($e);
                $this->_readErrorFlow($request, $response, 'Sorry, something went wrong. Please try again later.');
            }

            return;
        }
        else if (count($found) > 1)
        {
            $session_parms = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_SESSION);

            $storage_name = $this->evaluateString($this->_equipFavoriteDuplicatesStorage);
            $session_parms->setServiceParam($storage_name, $found);

            foreach ($this->_equipFavoriteDuplicates as $efd) {
                $efd->read($request, $response);
            }
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
}