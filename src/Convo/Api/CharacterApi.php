<?php declare(strict_types=1);

namespace Convo\Api;

class CharacterApi extends BaseApi
{
	public function __construct($logger, $httpFactory, $apiKey, $accessToken)
	{
		parent::__construct($logger, $httpFactory, $apiKey, $accessToken);
	}

	public function getUserProfile($membershipType, $membershipId)
	{
		$uri = parent::BASE_URL . "/Destiny2/$membershipType/Profile/$membershipId/?components=100";

		return $this->_performRequest($uri, 'GET');
	}

	public function getCharacter($membershipType, $membershipId, $characterId)
	{
		$uri = parent::BASE_URL . "/Destiny2/$membershipType/Profile/$membershipId/Character/$characterId/?components=200";

		return $this->_performRequest($uri, 'GET');
	}

	public function equipItems($itemIds, $characterId, $membershipType)
	{
		$uri = parent::BASE_URL . '/Destiny2/Actions/Items/EquipItem/';

		if (!is_array($itemIds)) {
			$itemsIds = [$itemIds];
		}

		$body = [
			"itemIds" => $itemsIds,
			"characterId" => $characterId,
			"membershipType" => $membershipType
		];

		return $this->_performRequest($uri, 'POST', $body);
	}
}
