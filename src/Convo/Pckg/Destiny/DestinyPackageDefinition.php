<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny;

use Convo\Core\Factory\AbstractPackageDefinition;
use Convo\Core\Factory\ComponentDefinition;
use Convo\Core\Factory\IComponentFactory;
use Convo\Pckg\Destiny\Catalogs\WeaponNameContext;

class DestinyPackageDefinition extends AbstractPackageDefinition
{
	const NAMESPACE = 'convo-destiny';

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	private $_httpFactory;

	/**
	 * @var \Convo\Pckg\Destiny\Api\DestinyApiFactory
	 */
	private $_destinyApiFactory;

	public function __construct(
		\Psr\Log\LoggerInterface $logger,
		\Convo\Core\Util\IHttpFactory $httpFactory,
		\Convo\Pckg\Destiny\Api\DestinyApiFactory $destinyApiFactory
	)
	{
		$this->_httpFactory = $httpFactory;
		$this->_destinyApiFactory = $destinyApiFactory;

		parent::__construct($logger, self::NAMESPACE, __DIR__);
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
							return new \Convo\Pckg\Destiny\Catalogs\WeaponNameContext(
								'WeaponNameCatalog',
								$this->_logger,
								$this->_httpFactory,
								$properties
							);
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
							return new \Convo\Pckg\Destiny\Catalogs\ArmorNameContext(
								'ArmorNameCatalog',
								$this->_logger,
								$this->_httpFactory,
								$properties
							);
						}
					}
				]
			)
		];
	}
}
