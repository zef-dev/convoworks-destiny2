<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Api;

class CharacterApi extends BaseDestinyApi
{
	public function __construct($httpFactory, $manifestDb, $cache, $apiKey, $accessToken)
	{
		parent::__construct($httpFactory, $manifestDb, $cache, $apiKey, $accessToken);
	}

	public function getUserProfile($membershipType, $membershipId)
	{
		$uri = parent::BASE_URL . "/Platform/Destiny2/$membershipType/Profile/$membershipId/?components=100";

		return $this->_performRequest($uri, 'GET');
	}

	public function getCharacter($membershipType, $membershipId, $characterId, $components, $invalidateCache = false)
	{
		$uri = parent::BASE_URL . "/Platform/Destiny2/$membershipType/Profile/$membershipId/Character/$characterId/?components=".(implode(',', $components));

		return $this->_performRequest($uri, 'GET', [], $invalidateCache);
	}

	public function equipItems($itemIds, $characterId, $membershipType)
	{
		$uri = parent::BASE_URL . '/Platform/Destiny2/Actions/Items/EquipItems/';

		if (!is_array($itemIds)) {
			$itemIds = [$itemIds];
		}

		$itemIds = array_map(function ($id) { return (int) $id; }, $itemIds);

		$body = [
			"itemIds" => $itemIds,
			"characterId" => $characterId,
			"membershipType" => $membershipType
		];

		$this->_logger->debug('Going to POST EquipItems with ['.print_r($body, true).']');

		return $this->_performRequest($uri, 'POST', $body);
	}
}
