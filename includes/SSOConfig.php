<?php
/**
 * Holds the SSO configuration parameters.
 */
class SSOConfig
{
	private $vendor_id;
	private $vendor_username;
	private $vendor_password;
	private $vendor_block;
	private $login_url;
	private $register_url;
	private $service_url;

	/**
	 * Constructor
	 * @param $vendorId string
	 * @param $vendorUsername string
	 * @param $vendorPassword string
	 * @param $vendorBlock string
	 * @param $loginUrl string the url of the SSO login page, i.e. https://../login.aspx
	 * @param $serviceUrl string the webservice url, i.e. http://../../..asmx
	 */
	function __construct($vendorId, $vendorUsername, $vendorPassword, $vendorBlock, $loginUrl, $register_url, $serviceUrl)
	{
		$this->vendor_id = $vendorId;
		$this->vendor_username = $vendorUsername;
		$this->vendor_password = $vendorPassword;
		$this->vendor_block = $vendorBlock;
		$this->login_url = $loginUrl;
		$this->register_url = $register_url;
		$this->service_url = $serviceUrl;
	}

	/**
	 * Returns the vendor ID
	 */
	function getVendorId()
	{
		return $this->vendor_id;
	}

	/**
	 * Returns the vendor username
	 */
	function getVendorUsername()
	{
		return $this->vendor_username;
	}


	/**
	 * Returns the vendor password
	 */
	function getVendorPassword()
	{
		return $this->vendor_password;
	}

	/**
	 * Returns the vendor block
	 */
	function getVendorBlock()
	{
		return $this->vendor_block;
	}

	/**
	 * Returns the login url
	 */
	function getLoginUrl()
	{
		return $this->login_url;
	}

	/**
	 * Returns the register url
	 */
	function getRegisterUrl()
	{
		return $this->register_url;
	}
	
	/**
	 * Returns the service url
	 */
	function getServiceUrl()
	{
		return $this->service_url;
	}
}
