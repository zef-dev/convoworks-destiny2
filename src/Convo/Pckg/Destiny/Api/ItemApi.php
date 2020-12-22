<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Api;

class ItemApi extends BaseDestinyApi
{
	public function __construct($httpFactory, $manifestDb, $cache, $apiKey, $accessToken)
	{
		parent::__construct($httpFactory, $manifestDb, $cache, $apiKey, $accessToken);
	}

	public function getItemManifest($itemHash)
	{
		return $this->_queryManifest(BaseDestinyApi::ITEM_TABLE, $itemHash);
	}

	public function getItemInstance($membershipType, $destinyMembershipId, $itemInstanceId)
	{
		$uri = parent::BASE_URL . "/Platform/Destiny2/$membershipType/Profile/$destinyMembershipId/Item/$itemInstanceId/?components=300,301,302,303,304,307";

		return $this->_performRequest($uri, 'GET');
	}

	public function getPerkManifest($perkHash)
	{
		return $this->_queryManifest(BaseDestinyApi::PERK_TABLE, $perkHash);
	}
}