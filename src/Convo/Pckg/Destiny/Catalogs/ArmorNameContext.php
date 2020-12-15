<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Catalogs;

use Convo\Core\Workflow\AbstractWorkflowComponent;

class ArmorNameContext extends AbstractWorkflowComponent  implements \Convo\Core\Workflow\IServiceContext
{
	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $_logger;

	/**
	 * @var \Convo\Core\Util\IHttpFactory
	 */
	private $_httpFactory;

	private $_componentId;

	/**
	 * @var \Convo\Core\Workflow\ICatalogSource
	 */
	private $_catalog;

	private $_apiKey;

	public function __construct($catalogName, $logger, $httpFactory, $properties)
	{
		$this->_componentId = $catalogName;

		$this->_logger = $logger;
		$this->_httpFactory = $httpFactory;

		if (!isset($properties['api_key'])) {
            throw new \Exception('Missing API key');
		}
		
		$this->_apiKey = $properties['api_key'];
	}

	public function init()
	{
		$this->_logger->debug('ArmorNameContext init');
		$api_key = $this->evaluateString($this->_apiKey);
		$this->_catalog = new ArmorNameCatalog($this->_logger, $this->_httpFactory, $api_key);
	}

	public function getId()
	{
		return $this->_componentId;
	}

	public function getComponent()
	{
		if (!$this->_catalog) {
			$this->init();
		}

		return $this->_catalog;
	}

	// UTIL
	public function __toString()
	{
		return get_class($this).'[]';
	}
}