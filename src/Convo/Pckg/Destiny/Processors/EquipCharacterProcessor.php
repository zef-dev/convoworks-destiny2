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

    private $_errorMessageName;

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
        $this->_duplicateItemsName = $properties['duplicate_items_name'] ?? 'duplicate_items';

        $this->_errorMessageName = $properties['error_message_name'] ?? 'errorMsg';

        $this->_requestFilters = $this->_initFilters();
    }

    private function _initFilters()
    {
        $equip_weapon_reader = new ConvoIntentReader(['intent' => 'convo-destiny.EquipWeaponIntent'], $this->_packageProviderFactory);
        $equip_weapon_reader->setLogger($this->_logger);
        $equip_weapon_reader->setService($this->getService());

        $equip_armor_reader = new ConvoIntentReader(['intent' => 'convo-destiny.EquipArmorIntent'], $this->_packageProviderFactory);
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

        if ($result->isSlotEmpty('WeaponName') && $result->isSlotEmpty('ArmorName'))
        {
            $this->_logger->warning('No item name to handle.');

            // none found
            $err_name = $this->evaluateString($this->_errorMessageName);
            $params = $this->getService()->getServiceParams('session');
            $params->setServiceParam($err_name, "Sorry, I couldn't quite understand which item you meant. Please try again.");

            foreach ($this->_nok as $nok) {
                $nok->read($request, $response);
            }

            return;
        }

        if ($sys_intent->getName() === 'EquipWeaponIntent')
        {   
            if ($result->isSlotEmpty('WeaponName'))
            {
                $this->_logger->warning('No item name to handle.');

                // none found
                $err_name = $this->evaluateString($this->_errorMessageName);
                $params = $this->getService()->getServiceParams('session');
                $params->setServiceParam($err_name, "Sorry, I couldn't quite understand which item you meant. Please try again.");

                foreach ($this->_nok as $nok) {
                    $nok->read($request, $response);
                }
            }
            else
            {
                // find item
                $item_name = strtolower($result->getSlotValue('WeaponName'));
                $this->_logger->debug('Handling EquipWeaponIntent with weapon ['.$item_name.']');
            }
        }
        else if ($sys_intent->getName() === 'EquipArmorIntent')
        {
            if ($result->isSlotEmpty('ArmorName'))
            {
                $this->_logger->warning('No item name to handle.');

                // none found
                $err_name = $this->evaluateString($this->_errorMessageName);
                $params = $this->getService()->getServiceParams('session');
                $params->setServiceParam($err_name, "Sorry, I couldn't quite understand which item you meant. Please try again.");

                foreach ($this->_nok as $nok) {
                    $nok->read($request, $response);
                }
            }
            else
            {
                // find item
                $item_name = strtolower($result->getSlotValue('ArmorName'));
                $this->_logger->debug('Handling EquipArmorIntent with armor ['.$item_name.']');
            }
        }
        else
        {
            throw new \Exception( 'Got convo intent ['.$sys_intent.'] for ['.$request->getPlatformId().']['.$request->getIntentName().'] but expected EquipWeaponIntent or EquipArmorIntent');
        }

        /** @var array $inventory */
        $inventory = $this->evaluateString($this->_inventory);

        if (!is_array($inventory)) {
            throw new \Exception('Expected to find array of items for the inventory property.');
        }

        $item_ids = [];

        foreach ($inventory as $item) {
            $inventory_item_name = strtolower($item['manifest']['displayProperties']['name']);
            $this->_logger->debug('Considering inventory item ['.$inventory_item_name.']');

            if (stripos($inventory_item_name, $item_name) !== false) {
                $this->_logger->debug('Found potential candidate with instance ID ['.$item['itemInstanceId'].']');
                $item_ids[] = $item['itemInstanceId'];
            }
        }

        $this->_logger->debug('Final item IDs to consider ['.print_r($item_ids, true).']');

        $character_api = $this->_destinyApiFactory->getApi(
            DestinyApiFactory::API_TYPE_CHARACTER,
            $this->evaluateString($this->_apiKey),
            $this->evaluateString($this->_accessToken)
        );

        $char_id = $this->evaluateString($this->_characterId);
        $membership_type = $this->evaluateString($this->_membershipType);

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
        }
        else if (count($item_ids) === 1)
        {
            $this->_logger->debug('One item found. Equipping.');
            try {
                // one item found, equip it
                $result = $character_api->equipItem(
                    $item_ids[0], $char_id, $membership_type
                );

                $this->_logger->debug('Item equipped. Reading OK flow.');
                
                foreach ($this->_ok as $ok) {
                    $ok->read($request, $response);
                }
            } catch (\Exception $e) {
                $this->_logger->error($e);

                if (method_exists($e, 'getResponse')) {
                    // can get response
                    $this->_logger->debug('Can get response off of error');
                    $res = json_decode($e->getResponse()->getBody()->__toString(), true);

                    $this->_logger->debug('Got response ['.print_r($res, true).']');

                    if (isset($res['Message'])) {
                        $err_name = $this->evaluateString($this->_errorMessageName);
                        $this->_logger->debug('Setting message ['.$res['Message'].'] under the name ['.$err_name.']');

                        $params = $this->getService()->getServiceParams($this->_duplicateItemsScope);
                        $params->setServiceParam($err_name, $res['Message']);
                    }
                }

                foreach ($this->_nok as $nok) {
                    $this->_logger->debug('Reading NOK');
                    $nok->read($request, $response);
                }
            }
        }
        else
        {
            // none found
            $err_name = $this->evaluateString($this->_errorMessageName);
            $params = $this->getService()->getServiceParams($this->_duplicateItemsScope);
            $params->setServiceParam($err_name, "No item with that name was found.");

            foreach ($this->_nok as $nok) {
                $nok->read($request, $response);
            }
        }

        return;
    }
}