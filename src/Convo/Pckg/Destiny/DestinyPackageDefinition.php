<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny;

use Convo\Core\Factory\AbstractPackageDefinition;
use Convo\Core\Factory\ComponentDefinition;
use Convo\Core\Factory\IComponentFactory;

class DestinyPackageDefinition extends AbstractPackageDefinition
{
	const NAMESPACE = 'convo-destiny';

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	private $_httpFactory;

	/**
	 * @var \Convo\Core\Factory\PackageProviderFactory
	 */
	private $_packageProviderFactory;

	/**
	 * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
	 */
	private $_destinyApiFactory;

	public function __construct(
		\Psr\Log\LoggerInterface $logger,
		\Convo\Core\Util\IHttpFactory $httpFactory,
		\Convo\Core\Factory\PackageProviderFactory $packageProviderFactory,
		\Convo\Pckg\Destiny\Api\DestinyApiFactory $destinyApiFactory
	)
	{
		$this->_httpFactory = $httpFactory;
		$this->_packageProviderFactory = $packageProviderFactory;
		$this->_destinyApiFactory = $destinyApiFactory;

		parent::__construct($logger, self::NAMESPACE, __DIR__);
	}

	protected function _initIntents()
	{
		return $this->_loadIntents(__DIR__.'/system-intents.json');
	}

	public function _initDefintions()
	{
		return [
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Elements\InitializeCharacterElement',
				'Initialize Character Element',
				'Loads a Destiny character and initializes information about them, such as inventory, equipment, stats, etc.',
				[
					'scope_type' => [
						'editor_type' => 'select',
						'editor_properties' => [
							'multiple' => false,
							'options' => [
								'request' => 'Request', 'session' => 'Session', 'installation' => 'Installation'
							]
						],
						'defaultValue' => 'session',
						'name' => 'Scope Type',
						'description' => 'Scope under which to store fetched and initialized character',
						'valueType' => 'string'
					],
					'storage_name' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Storage Name',
						'description' => 'Name under which to store fethced data',
						'valueType' => 'string'
					],
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'access_token' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Access Token',
						'description' => 'Access token needed to identify requests that need OAuth authorization',
						'valueType' => 'string'
					],
					'membership_type' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership Type',
						'description' => 'The platform to load this character on. Possible values are 1 = Xbox, 2 = Playstation, 3 = Steam, 5 = Stadia',
						'valueType' => 'string'
					],
					'membership_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership ID',
						'description' => 'Membership ID for the chosen profile',
						'valueType' => 'string'
					],
					'character_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character ID',
						'description' => 'Character ID that you wish to initialize',
						'valueType' => 'string'
					],
					'init_components' => [
						'editor_type' => 'select',
						'editor_properties' => [
							'multiple' => true,
							'options' => [
								'100' => 'Profile',
								'102' => 'Profile Inventory (Vault)',
								'200' => 'Character',
								'201' => 'Character Inventory',
								'205' => 'Character Equipment',
								'300' => 'Item Instances',
								'302' => 'Item Perks',
								'304' => 'Item Stats',
							]
						],
						'defaultValue' => null,
						'name' => 'Components to Load',
						'description' => 'Choose which components you want to load for the given character.',
						'valueType' => 'string'
					],
					'_workflow' => 'read',
					'_factory' => new class ($this->_destinyApiFactory) implements IComponentFactory
					{
						private $_destinyApiFactory;

						public function __construct($destinyApiFactory)
						{
							$this->_destinyApiFactory = $destinyApiFactory;
						}

						public function createComponent($properties, $service)
						{
							return new \Convo\Pckg\Destiny\Elements\InitializeCharacterElement(
								$properties,
								$this->_destinyApiFactory
							);
						}
					}
				]
			),
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Elements\ItemInstanceElement',
				'Item Instance Element',
				'Takes an item instance ID (or IDs), and deserializes them to make them available to use in the service\'s scope',
				[
					'scope_type' => [
						'editor_type' => 'select',
						'editor_properties' => [
							'multiple' => false,
							'options' => [
								'request' => 'Request', 'session' => 'Session', 'installation' => 'Installation'
							]
						],
						'defaultValue' => 'session',
						'name' => 'Scope Type',
						'description' => 'Scope under which to store item instances',
						'valueType' => 'string'
					],
					'storage_name' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Storage Name',
						'description' => 'Name under which to store data',
						'valueType' => 'string'
					],
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'access_token' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Access Token',
						'description' => 'Access token needed to identify requests that need OAuth authorization',
						'valueType' => 'string'
					],
					'membership_type' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership Type',
						'description' => 'The platform to load these items on. Possible values are 1 = Xbox, 2 = Playstation, 3 = Steam, 5 = Stadia',
						'valueType' => 'string'
					],
					'membership_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership ID',
						'description' => 'Membership ID for the chosen profile',
						'valueType' => 'string'
					],
					'item_instance_ids' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Item Instance IDs',
						'description' => 'One or more item instace IDs to deserialize',
						'valueType' => 'string'
					],
					'_workflow' => 'read',
					'_preview_angular' => [
						'type' => 'html',
						'type' => 'html',
						'template' => '<div class="code">' .
							'<span class="statement">DESERIALIZE ITEM INSTANCES</span> <b>{{ component.properties.item_instance_ids }}</b>'
					],
					'_factory' => new class ($this->_destinyApiFactory) implements IComponentFactory
					{
						private $_destinyApiFactory;

						public function __construct($destinyApiFactory)
						{
							$this->_destinyApiFactory = $destinyApiFactory;
						}

						public function createComponent($properties, $service)
						{
							return new \Convo\Pckg\Destiny\Elements\ItemInstanceElement($properties, $this->_destinyApiFactory);
						}
					}
				]
			),
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Elements\EquipItemElement',
				'Equip Item Element',
				'Equips an item to a given character',
				[
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'access_token' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Access Token',
						'description' => 'Access token needed to identify requests that need OAuth authorization',
						'valueType' => 'string'
					],
					'membership_type' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership Type',
						'description' => 'Membership type for the chosen profile',
						'valueType' => 'string'
					],
					'character_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character ID',
						'description' => 'Character ID that you wish to manage equipment for',
						'valueType' => 'string'
					],
					'item_instance_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Item Instance ID',
						'description' => 'Item instace ID to equip',
						'valueType' => 'string'
					],
					'ok' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => true,
						'name' => 'OK',
						'description' => 'Runs when a given item has been sucessfully equipped',
						'valueType' => 'class'
					],
					'nok' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => false,
						'name' => 'Not OK',
						'description' => 'Runs when the given item could not be equipped',
						'valueType' => 'class'
					],
					'_workflow' => 'read',
					'_factory' => new class ($this->_destinyApiFactory) implements IComponentFactory
					{
						private $_destinyApiFactory;

						public function __construct($destinyApiFactory)
						{
							$this->_destinyApiFactory = $destinyApiFactory;
						}

						public function createComponent($properties, $service)
						{
							return new \Convo\Pckg\Destiny\Elements\EquipItemElement(
								$properties, $this->_destinyApiFactory
							);
						}
					}
				]
			),
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Processors\EquipCharacterProcessor',
				'Equip Character Processor',
				'Equip weapons, armor, and other items from your inventory to your character.',
				[
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'access_token' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Access Token',
						'description' => 'Access token needed to identify requests that need OAuth authorization',
						'valueType' => 'string'
					],
					'membership_type' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership Type',
						'description' => 'Membership type for the chosen profile',
						'valueType' => 'string'
					],
					'character_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character ID',
						'description' => 'Character ID that you wish to manage equipment for',
						'valueType' => 'string'
					],
					'inventory' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character inventory',
						'description' => 'Collection of items in the character\'s inventory.',
						'valueType' => 'string'
					],
					'duplicate_items_scope' => [
						'editor_type' => 'select',
						'editor_properties' => [
							'multiple' => false,
							'options' => [
								'request' => 'Request', 'session' => 'Session', 'installation' => 'Installation'
							]
						],
						'defaultValue' => 'session',
						'name' => 'Duplicate Items Scope',
						'description' => 'Scope under which to store duplicate item instance IDs, if found',
						'valueType' => 'string'
					],
					'duplicate_items_name' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Storage Name',
						'description' => 'Name under which to store duplicate item instance IDs',
						'valueType' => 'string'
					],
					'error_message_name' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => 'errorMsg',
						'name' => 'Error Message Name',
						'description' => 'If an error occurrs, make it available in scope under this name.',
						'valueType' => 'string'
					],
					'ok' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => true,
						'name' => 'OK',
						'description' => 'Runs when a given item has been sucessfully equipped',
						'valueType' => 'class'
					],
					'duplicates_found' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => false,
						'name' => 'Duplicates Found',
						'description' => 'Runs when duplicate items with the same name have been found. Will not equip anything.',
						'valueType' => 'class'
					],
					'nok' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => false,
						'name' => 'Not OK',
						'description' => 'Runs when the given item could not be equipped',
						'valueType' => 'class'
					],
					'_workflow' => 'process',
					'_preview_angular' => array(
                        'type' => 'html',
                        'template' => '<div class="user-say">'.
                            'User says: <b>"equip Witherhoard"</b>, <b>"put on Ancient Apocalypse Helm"</b>, <b>"equip my Night Watch"</b>...'.
                            '</div>'
                    ),
					'_factory' => new class ($this->_packageProviderFactory, $this->_destinyApiFactory) implements IComponentFactory
					{
						private $_packageProviderFactory;
						private $_destinyApiFactory;

						public function __construct($packageProviderFactory, $destinyApiFactory)
						{
							$this->_packageProviderFactory = $packageProviderFactory;
							$this->_destinyApiFactory = $destinyApiFactory;
						}

						public function createComponent($properties, $service)
						{
							return new \Convo\Pckg\Destiny\Processors\EquipCharacterProcessor(
								$properties, $this->_packageProviderFactory, $this->_destinyApiFactory, $service
							);
						}
					}
				]
			),
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Processors\InventoryManagementProcessor',
				'Inventory Management Processor',
				'Transfer gear between your vault and your character.',
				[
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'access_token' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Access Token',
						'description' => 'Access token needed to identify requests that need OAuth authorization',
						'valueType' => 'string'
					],
					'membership_type' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership Type',
						'description' => 'Membership type for the chosen profile',
						'valueType' => 'string'
					],
					'membership_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Membership ID',
						'description' => 'Membership ID for the chosen profile',
						'valueType' => 'string'
					],
					'character_id' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character ID',
						'description' => 'Character ID that you wish to manage equipment for',
						'valueType' => 'string'
					],
					'profile_inventory' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character inventory',
						'description' => 'Collection of items in the profile inventory (including the vault).',
						'valueType' => 'string'
					],
					'character_inventory' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Character inventory',
						'description' => 'Collection of items in the character\'s inventory.',
						'valueType' => 'string'
					],
					'duplicate_items_scope' => [
						'editor_type' => 'select',
						'editor_properties' => [
							'multiple' => false,
							'options' => [
								'request' => 'Request', 'session' => 'Session', 'installation' => 'Installation'
							]
						],
						'defaultValue' => 'session',
						'name' => 'Duplicate Items Scope',
						'description' => 'Scope under which to store duplicate item instance IDs, if found',
						'valueType' => 'string'
					],
					'duplicate_items_name' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'Storage Name',
						'description' => 'Name under which to store duplicate item instance IDs',
						'valueType' => 'string'
					],
					'error_message_name' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => 'errorMsg',
						'name' => 'Error Message Name',
						'description' => 'If an error occurrs, make it available in scope under this name.',
						'valueType' => 'string'
					],
					'ok' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => true,
						'name' => 'OK',
						'description' => 'Runs when a given item has been sucessfully transferred',
						'valueType' => 'class'
					],
					'duplicates_found' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => false,
						'name' => 'Duplicates Found',
						'description' => 'Runs when duplicate items with the same name have been found. Will not transfer anything.',
						'valueType' => 'class'
					],
					'nok' => [
						'editor_type' => 'service_components',
						'editor_properties' => [
							'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
							'multiple' => true,
							'hideWhenEmpty' => false
						],
						'defaultValue' => [],
						'defaultOpen' => false,
						'name' => 'Not OK',
						'description' => 'Runs when the given item could not be transferred',
						'valueType' => 'class'
					],
					'_workflow' => 'process',
					'_preview_angular' => array(
                        'type' => 'html',
                        'template' => '<div class="user-say">'.
                            'User says: <b>"get Witherhoard from my vault"</b>, <b>"transfer Ancient Apocalypse Helm to my vault"</b>, <b>"get Night Watch from vault"</b>...'.
                            '</div>'
                    ),
					'_factory' => new class ($this->_packageProviderFactory, $this->_destinyApiFactory) implements IComponentFactory
					{
						private $_packageProviderFactory;
						private $_destinyApiFactory;

						public function __construct($packageProviderFactory, $destinyApiFactory)
						{
							$this->_packageProviderFactory = $packageProviderFactory;
							$this->_destinyApiFactory = $destinyApiFactory;
						}

						public function createComponent($properties, $service)
						{
							return new \Convo\Pckg\Destiny\Processors\InventoryManagementProcessor(
								$properties, $this->_packageProviderFactory, $this->_destinyApiFactory, $service
							);
						}
					}
				]
			),
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Catalogs\WeaponNameContext',
				'Weapon Name Catalog',
				'Use a catalog entity for weapon names (currently only available on Amazon Alexa)',
				[
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'_preview_angular' => array(
						'type' => 'html',
						'template' => '<div class="code">' .
							'<span class="statement">USE CATALOG ENTITY</span> <b>WeaponName</b>'
					),
					'_class_aliases' => ['\Convo\Pckg\Destiny\Catalogs\WeaponNameCatalog'],
					'_workflow' => 'datasource',
					'_factory' => new class ($this->_logger, $this->_httpFactory) implements IComponentFactory
					{
						private $_logger;
						private $_httpFactory;

						public function __construct($logger, $httpFactory)
						{
							$this->_logger = $logger;
							$this->_httpFactory = $httpFactory;
						}

						public function createComponent($properties, $service)
						{
							$ctx = new \Convo\Pckg\Destiny\Catalogs\WeaponNameContext(
								'WeaponNameCatalog',
								$this->_logger,
								$this->_httpFactory,
								$properties
							);
							$ctx->setParent($service);
							$ctx->setService($service);
							return $ctx;
						}
					}
				]
			),
			new ComponentDefinition(
				$this->getNamespace(),
				'\Convo\Pckg\Destiny\Catalogs\ArmorNameContext',
				'Armor Name Catalog',
				'Use a catalog entity for armor names (currently only available on Amazon Alexa)',
				[
					'api_key' => [
						'editor_type' => 'text',
						'editor_properties' => [],
						'defaultValue' => null,
						'name' => 'API Key',
						'description' => 'API key used to make requests to the Destiny 2 API',
						'valueType' => 'string'
					],
					'_preview_angular' => array(
						'type' => 'html',
						'template' => '<div class="code">' .
							'<span class="statement">USE CATALOG ENTITY</span> <b>ArmorName</b>'
					),
					'_class_aliases' => ['\Convo\Pckg\Destiny\Catalogs\ArmorNameCatalog'],
					'_workflow' => 'datasource',
					'_factory' => new class ($this->_logger, $this->_httpFactory) implements IComponentFactory
					{
						private $_logger;
						private $_httpFactory;

						public function __construct($logger, $httpFactory)
						{
							$this->_logger = $logger;
							$this->_httpFactory = $httpFactory;
						}

						public function createComponent($properties, $service)
						{
							$ctx = new \Convo\Pckg\Destiny\Catalogs\ArmorNameContext(
								'ArmorNameCatalog',
								$this->_logger,
								$this->_httpFactory,
								$properties
							);
							$ctx->setParent($service);
							$ctx->setService($service);
							return $ctx;
						}
					}
				]
			)
		];
	}
}
