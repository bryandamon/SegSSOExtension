<?php

/**
 * Convenience class with static methods that in turn invoke the methods of the SSOClient.
 * This class should be used instead of directly instantiating and using an SSOClient object.
 * @see SSOClient
 */
class SSOUtil
{
	private static $client;

	/**
	 * Validates the current customer token with the SSO server.
	 * @return boolean true if a customer token is found and valid, false otherwise.
	 */
	public static function isAuthenticated()
	{
		return self::getClient()->isAuthenticated();
	}

	/**
	 * Constructs the complete SSO login URL containing the parameters that the SSO server expects.
	 * @param $returnUrl string [optional] if specified, the given url will be used as the return URL
	 * 		after a successful login. Otherwise the current URL will be used.
	 * @return string the complete SSO login URL.
	 */
	public static function getSSOLoginUrl($returnUrl = '')
	{
		return self::getClient()->getSSOLoginUrl($returnUrl);
	}

	/**
	* Constructs the complete SSO register URL containing the parameters that the SSO server expects.
	* @param $returnUrl string [optional] if specified, the given url will be used as the return URL
	* 		after a successful register. Otherwise the current URL will be used.
	* @return string the complete SSO register URL.
	*/
	public static function getSSORegisterUrl($returnUrl = '')
	{
		return self::getClient()->getSSORegisterUrl($returnUrl);
	}
	
	/**
	 * Retrieves the SSO user information.
	 * If $timssCustomerId is specified, it will be used to do the lookup. Otherwise, the customer
	 * token will be used.
	 * @param $timssCustomerId string the TIMSS customer Id.
	 * @return mixed an associative array containing user SSO data, or false if user does not exist.
	 */
	public static function getCustomer($timssCustomerId = null)
	{
		return self::getClient()->getCustomer($timssCustomerId);
	}

	/**
	 * Retrieves the TIMSS customer Id for the current SSO user.
	 * @return string the TIMSS customer Id.
	 */
	public static function getTimssCustomerId()
	{
		return self::getClient()->getTimssCustomerId();
	}

	/**
	 * Logs out the current user from the SSO server.
	 */
	public static function logout()
	{
		return self::getClient()->logout();
	}

	/**
	 * Returns the static client object.
	 * @throws Exception if the client is not set
	 * @return SSOClient
	 */
	private static function getClient()
	{
		if(!isset(self::$client))
		{
			throw new Exception("SSO client is not set!");
		}

		return self::$client;
	}

	/**
	 * Sets the static client object.
	 * @param $newClient SSOClient the client object to set.
	 */
	public static function setClient(SSOClient $newClient)
	{
		self::$client = $newClient;
	}
}
