<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Processors;

use Convo\Core\Workflow\IConversationProcessor;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;
use Convo\Core\Workflow\IRequestFilterResult;
use Convo\Pckg\Core\Filters\ConvoIntentReader;
use Convo\Pckg\Core\Filters\IntentRequestFilter;
use Convo\Pckg\Core\Processors\AbstractServiceProcessor;

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
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_preEquip;

    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_ok;
    
    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_multipleFound;
    
    /**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
    private $_nok;

    public function __construct($properties, $packageProviderFactory, $service)
    {
        parent::__construct($properties);
        $this->setService($service);

        $this->_packageProviderFactory = $packageProviderFactory;

        $this->_preEquip = $properties['pre_equip'] ?: [];
        foreach ($this->_preEquip as $pre_equip) {
            $this->addChild($pre_equip);
        }

        $this->_ok = $properties['ok'] ?: [];
        foreach ($this->_ok as $ok) {
            $this->addChild($ok);
        }

        $this->_multipleFound = $properties['multiple_found'] ?: [];
        foreach ($this->_multipleFound as $mf) {
            $this->addChild($mf);
        }

        $this->_nok = $properties['nok'] ?: [];
        foreach ($this->_nok as $nok) {
            $this->addChild($nok);
        }

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
        
        foreach ($this->_preEquip as $pre_equip)
        {
            $pre_equip->read($request, $response);
        }

        if ($sys_intent->getName() === 'EquipWeaponIntent')
        {
            $this->_logger->debug('Handling EquipWeaponIntent');
            return;
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