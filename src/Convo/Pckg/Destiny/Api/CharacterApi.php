<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Api;

class CharacterApi extends BaseDestinyApi
{
	public function __construct($httpFactory, $manifests, $cache, $apiKey, $accessToken)
	{
		parent::__construct($httpFactory, $manifests, $cache, $apiKey, $accessToken);
	}

	public function getUserProfile($membershipType, $membershipId)
	{
		$uri = parent::BASE_URL . "/Platform/Destiny2/$membershipType/Profile/$membershipId/?components=100";

		return $this->_performRequest($uri, 'GET');
	}

	public function getCharacter($membershipType, $membershipId, $characterId, $components)
	{
		$uri = parent::BASE_URL . "/Platform/Destiny2/$membershipType/Profile/$membershipId/Character/$characterId/?components=".(implode(',', $components));

		return $this->_performRequest($uri, 'GET');
	}

	public function equipItems($itemIds, $characterId, $membershipType)
	{
		$uri = parent::BASE_URL . '/Platform/Destiny2/Actions/Items/EquipItem/';

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