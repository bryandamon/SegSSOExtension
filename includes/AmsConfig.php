<?php
/**
 * Holds the AMS configuration parameters.
 */
class AmsConfig
{
	private $custom_service_url;
	
	/**
	 * Constructor
	 * @param $customServiceUrl string
	 */
	function __construct($customServiceUrl)
	{
		$this->custom_service_url = $customServiceUrl;
	}
	
	/**
	 * Returns the custom service url
	 */
	function getCustomServiceUrl()
	{
		return $this->custom_service_url;
	}
	
}
