<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Processors;

use Convo\Core\Workflow\IConversationProcessor;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Core\Workflow\IRequestFilterResult;
use Convo\Pckg\Core\Filters\ConvoIntentReader;
use Convo\Pckg\Core\Filters\IntentRequestFilter;
use Convo\Pckg\Core\Processors\AbstractServiceProcessor;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;

/**
 * This processor equips items from the character's inventory onto the character itself
 */
class EquipCharacterProcessor extends AbstractServiceProcessor implements IConversationProcessor
{
    /**
     * @var \Convo\Core\Factory\PackageProviderFactory
     */
    private $_packageProviderFactory;

    /**
     * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
     */
    private $_destinyApiFactory;

    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_ok;
    
    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_duplicatesFound;
    
    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_nok;

    private $_apiKey;
    private $_accessToken;

    private $_membershipType;
    private $_characterId;

    private $_inventory;

    private $_duplicateItemsScope;
    private $_duplicateItemsName;

    public function __construct($properties, $packageProviderFactory, $destinyApiFactory, $service)
    {
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

        $this->_duplicatesFound = $properties['duplicates_found'] ?: [];
        foreach ($this->_duplicatesFound as $df) {
            $this->addChild($df);
        }

        $this->_nok = $properties['nok'] ?: [];
        foreach ($this->_nok as $nok) {
            $this->addChild($nok);
        }

        $this->_characterId = $properties['character_id'];
        $this->_membershipType = $properties['membership_type'];

        $this->_inventory = $properties['inventory'];

        $this->_duplicateItemsScope = $properties['duplicate_items_scope'];
        $this->_duplicateItemsName = $properties['duplicate_items_name'] ?: 'duplicate_items';

        $this->_requestFilters = $this->_initFilters();
    }

    private function _initFilters()
    {
        $equip_weapon_reader = new ConvoIntentReader(['intent' => 'convo-destiny2.EquipWeaponIntent'], $this->_packageProviderFactory);
        $equip_weapon_reader->setLogger($this->_logger);
        $equip_weapon_reader->setService($this->getService());

        $equip_armor_reader = new ConvoIntentReader(['intent' => 'convo-destiny2.EquipArmorIntent'], $this->_packageProviderFactory);
        $equip_armor_reader->setLogger($this->_logger);
        $equip_armor_reader->setService($this->getService());

        $config = [
            'readers' => [$equip_weapon_reader, $equip_armor_reader]
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

        $character_api = $this->_destinyApiFactory->getApi(
            DestinyApiFactory::API_TYPE_CHARACTER,
            $this->evaluateString($this->_apiKey),
            $this->evaluateString($this->_accessToken)
        );

        $char_id = $this->evaluateString($this->_characterId);
        $membership_type = $this->evaluateString($this->_membershipType);

        if ($sys_intent->getName() === 'EquipWeaponIntent')
        {
            $this->_logger->debug('Handling EquipWeaponIntent');
            
            // find item
            $weapon_name = $result->getSlotValue('WeaponName');

            /** @var array $inventory */
            $inventory = $this->evaluateString($this->_inventory);
            $item_ids = [];

            foreach ($inventory as $item) {
                if ($item['manifest_data']['displayProperties']['name'] === $weapon_name) {
                    $item_ids[] = $item['base']['itemInstanceId'];
                }
            }

            if (count($item_ids) > 1)
            {
                // duplicate items with the same name found
                $params = $this->getService()->getServiceParams($this->_duplicateItemsScope);

                $name = $this->evaluateString($this->_duplicateItemsName);
                $this->_logger->debug('Going to store duplicate item IDs ['.implode(', ', $item_ids).'] as ['.$this->_duplicateItemsScope.'.'.$name.']');
                
                $params->setServiceParam($name, $item_ids);

                foreach ($this->_duplicatesFound as $df) {
                    $df->read($request, $response);
                }

                return;
            }
            else if (count($item_ids) === 1)
            {
                // one item found, equip it
                $character_api->equipItems(
                    $item_ids, $char_id, $membership_type
                );

                foreach ($this->_ok as $ok) {
                    $ok->read($request, $response);
                }

                return;
            }
            else
            {
                // none found
                foreach ($this->_nok as $nok) {
                    $nok->read($request, $response);
                }
                
                return;
            }
        }

        if ($sys_intent->getName() === 'EquipArmorIntent')
        {
            $this->_logger->debug('Handling EquipArmorIntent');
            return;
        }
        
        throw new \Exception( 'Got convo intent ['.$sys_intent.'] for ['.$request->getPlatformId().']['.$request->getIntentName().']'.
		    ' but expected EquipWeaponIntent or EquipArmorIntent');
    }
}