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
		$this->_logger->debug('Going to get item manifest with hash ['.$itemHash.']');

		$res = $this->_queryManifest(BaseDestinyApi::ITEM_TABLE, $itemHash);

		// $this->_logger->debug('Got item manifest ['.print_r($res, true).']');
		return $res;
	}

	public function getItemInstance($membershipType, $destinyMembershipId, $itemInstanceId)
	{
		$this->_logger->debug('Going to get item instance by ID ['.$itemInstanceId.']');
		$uri = parent::BASE_URL . "/Platform/Destiny2/$membershipType/Profile/$destinyMembershipId/Item/$itemInstanceId/?components=300,301,302,303,304,307";

		return $this->_performRequest($uri, 'GET');
	}

	public function getPerkManifest($perkHash)
	{
		$this->_logger->debug('Going to get perk manifest with hash ['.$perkHash.']');

		$res = $this->_queryManifest(BaseDestinyApi::PERK_TABLE, $perkHash);
		
		// $this->_logger->debug('Got perk manifest ['.print_r($res, true).']');
		return $res;
	}
}