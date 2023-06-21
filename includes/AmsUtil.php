<?php

/**
 * Convenience class with static methods that in turn invoke the methods of the AmsClient.
 * This class should be used instead of directly instantiating and using an AmsClient object.
 * @see AmsClient
 */
class AmsUtil
{
	private static $client;

	/**
	 * Retrieves the customer's basic information.
	 * @param $amsCustomerId string the TIMSS Customer Id
	 * @return an object representation of the customer information.
	 */
	public static function getCustomerBasicInfo($amsCustomerId)
	{
		return self::getClient()->getCustomerBasicInfo($amsCustomerId);
	}

	/**
	 * Returns the static client object.
	 * @throws Exception if the client is not set
	 * @return AmsClient
	 */
	private static function getClient()
	{
		if(!isset(self::$client))
		{
			throw new Exception("Ams client is not set!");
		}

		return self::$client;
	}

	/**
	 * Sets the static client object.
	 * @param $newClient AmsClient the client object to set.
	 */
	public static function setClient(AmsClient $newClient)
	{
		self::$client = $newClient;
	}
}
