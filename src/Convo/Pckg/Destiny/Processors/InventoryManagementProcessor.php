<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Processors;

use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Util\ArrayUtil;
use Convo\Core\Workflow\IConversationProcessor;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Core\Workflow\IRequestFilterResult;
use Convo\Pckg\Core\Filters\ConvoIntentReader;
use Convo\Pckg\Core\Filters\IntentRequestFilter;
use Convo\Pckg\Core\Processors\AbstractServiceProcessor;
use Convo\Pckg\Destiny\Api\DestinyApiFactory;
use Convo\Pckg\Destiny\Enums\DestinyBucketEnum;

/**
 * This processor deals with transactions between characters and the vault.
 */
class InventoryManagementProcessor extends AbstractServiceProcessor implements IConversationProcessor
{
    /**
     * @var \Convo\Core\Factory\PackageProviderFactory;
     */
    private $_packageProviderFactory;

    /**
     * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory;
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

    private $_profileInventory;
    private $_characterInventory;

    private $_membershipType;
    private $_characterId;

    private $_errorMessageName;

    public function __construct($properties, $packageProviderFactory, $destinyApiFactory, $service)
    {
        parent::__construct($properties);
        $this->setService($service);

        $this->_packageProviderFactory = $packageProviderFactory;

        $this->_destinyApiFactory = $destinyApiFactory;

        if (!isset($properties['api_key']) || empty($properties['api_key'])) {
            throw new \Exception('Missing API key in ['.$this.']');
        }
        $this->_apiKey = $properties['api_key'];

        if (!isset($properties['access_token']) || empty($properties['access_token'])) {
            throw new \Exception('Missing access token in ['.$this.']');
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

        $this->_profileInventory = $properties['profile_inventory'];
        $this->_characterInventory = $properties['character_inventory'];

        $this->_characterId = $properties['character_id'];
        $this->_membershipType = $properties['membership_type'];

        $this->_errorMessageName = $properties['error_message_name'];

        $this->_requestFilters = $this->_initFilters();
    }

    private function _initFilters()
    {
        $transfer_to_vault_reader = new ConvoIntentReader(['intent' => 'convo-destiny.TransferToVaultIntent'], $this->_packageProviderFactory);
        $transfer_to_vault_reader->setLogger($this->_logger);
        $transfer_to_vault_reader->setService($this->getService());

        $transfer_to_character_reader = new ConvoIntentReader(['intent' => 'convo-destiny.TransferToCharacterIntent'], $this->_packageProviderFactory);
        $transfer_to_character_reader->setLogger($this->_logger);
        $transfer_to_character_reader->setService($this->getService());

        $config = [
            'readers' => [$transfer_to_vault_reader, $transfer_to_character_reader]
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

        // Get profile with component 102 will get inventory (this includes the vault)
        $api_key = $this->evaluateString($this->_apiKey);
        $acc_tkn = $this->evaluateString($this->_accessToken);

        /** @var \Convo\Pckg\Destiny\Api\CharacterApi $char_api */
        $char_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_CHARACTER, $api_key, $acc_tkn);

        /** @var \Convo\Pckg\Destiny\Api\ItemApi $item_api */
        // $item_api = $this->_destinyApiFactory->getApi(DestinyApiFactory::API_TYPE_ITEM, $api_key, $acc_tkn);
        
        $membership_type = $this->evaluateString($this->_membershipType);
        // $membership_id = $this->evaluateString($this->_membershipId);
        $character_id = $this->evaluateString($this->_characterId);

        /** @var array $profile_inventory */
        $profile_inventory = $this->evaluateString($this->_profileInventory);
        $this->_logger->debug('Got ['.count($profile_inventory).'] profile inventory items');
        /** @var array $char_inventory */
        $char_inventory = $this->evaluateString($this->_characterInventory);
        $this->_logger->debug('Got ['.count($char_inventory).'] character inventory items');

        $vault = [];

        foreach ($profile_inventory as $profile_inventory_item) {
            if ($profile_inventory_item['base']['bucketHash'] === DestinyBucketEnum::BUCKET_VAULT) {
                $vault[] = $profile_inventory_item;
            }
        }

        $on_char = array_values(
            array_filter(
                array_merge(
                    $char_inventory,
                    ArrayUtil::arrayDiffRecursive($profile_inventory, $vault)
                ),
                function ($item) {
                    return isset($item['manifest']['displayProperties']['name']);
                }
            )
        );

        $items = [];
        if (!$result->isSlotEmpty('WeaponName')) {
            $item_name = strtolower($result->getSlotValue('WeaponName'));
        }
        else if (!$result->isSlotEmpty('ArmorName')) {
            $item_name = strtolower($result->getSlotValue('ArmorName'));
        }
        else {
            throw new \Exception('Could not determine item to transfer.');
        }

        if ($sys_intent->getName() === 'TransferToVaultIntent')
        {   
            // find item on character, send it to the vault
            $this->_logger->debug('Handling TransferToVaultIntent with item ['.$item_name.']');

            foreach ($on_char as $oci) {
                $inventory_item_name = strtolower($oci['manifest']['displayProperties']['name']);

                if (stripos($inventory_item_name, $item_name) !== false) {
                    $this->_logger->debug('Found potential candidate with instance ID ['.$oci['base']['itemInstanceId'].']');
                    $items[] = $oci;
                }
            }

            $transfer_to_vault = true;
        }
        else if ($sys_intent->getName() === 'TransferToCharacterIntent')
        {
            // find item in the vault, check if player has enough space in destination bucket, and transfer
            $this->_logger->debug('Handling TransferToCharacterIntent with item ['.$item_name.']');

            foreach ($profile_inventory as $pii) {
                $inventory_item_name = strtolower($pii['manifest']['displayProperties']['name']);
            
                if (stripos($inventory_item_name, $item_name) !== false) {
                    $this->_logger->debug('Found potential candidate with instance ID ['.$pii['base']['itemInstanceId'].']');
                    $items[] = $pii;
                }
            }

            $transfer_to_vault = false;
        }
        else
        {
            throw new \Exception( 'Got convo intent ['.$sys_intent.'] for ['.$request->getPlatformId().']['.$request->getIntentName().'] but expected TransferToVaultIntent or TransferToCharacterIntent');
        }

        try {
            
            if (count($items) > 1)
            {
                // duplicates
            }
            else if (count($items) === 1)
            {
                $char_api->transferItem(
                    $items[0]['base']['itemHash'],
                    1,
                    $transfer_to_vault,
                    $items[0]['base']['itemInstanceId'],
                    $character_id,
                    $membership_type
                );
            }
            else if (count($items) === 0)
            {
                $err_name = $this->evaluateString($this->_errorMessageName);
                $params = $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_SESSION);
                $params->setServiceParam($err_name, "No item with that name found.");

                foreach ($this->_nok as $nok) {
                    $nok->read($request, $response);
                }
    
                return;
            }

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

                $this->_logger->debug('Got response ['.print_r($res, true).']');

                if (isset($res['Message'])) {
                    $err_name = $this->evaluateString($this->_errorMessageName);
                    $this->_logger->debug('Setting message ['.$res['Message'].'] under the name ['.$err_name.']');

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