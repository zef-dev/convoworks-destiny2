<?php declare(strict_types=1);

namespace Convo\Api;

class ItemApi extends BaseDestinyApi
{
	public function __construct($httpFactory, $apiKey, $accessToken)
	{
		parent::__construct($httpFactory, $apiKey, $accessToken);
	}

	public function getItemManifest($itemHash)
	{
		$uri = parent::BASE_URL . "/Destiny2/Manifest/DestinyInventoryItemDefinition/$itemHash/";

		return $this->_performRequest($uri, 'GET');
	}
}