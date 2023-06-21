<?php
/**
 * Handles all interaction with the AMS server.
 * @see AmsUtil
 */
class AmsClient
{
	/*** Operation names ***/
	const OP_GET_CUSTOMER_LABEL = "GetCustomerLabel";

	private $config;

	function __construct(AmsConfig $amsConfig)
	{
		$this->config = $amsConfig;
	}

	/**
	 * Retrieves the customer's basic information.
	 * @param $amsCustomerId string the TIMSS Customer Id
	 * @return an object representation of the customer information.
	 */
	public function getCustomerBasicInfo($amsCustomerId)
	{
		$idParts = explode("|", $amsCustomerId);
		$amsMasterId = $idParts[0];
		$amsSubId = isset($idParts[1]) ? $idParts[1] : "0";

		$serviceUrl = $this->getOperationUrl(self::OP_GET_CUSTOMER_LABEL);
		$serviceUrl .= "?masterCustID=$amsMasterId&subCustID=$amsSubId";

		$customerInfo = HttpUtil::get_json_response($serviceUrl);
		return $customerInfo;
	}

	/**
	 * Generates the full web service URL for the given operation name.
	 * @param $opName string the name of the web service operation
	 * @return string the full web service URL for the given operation name.
	 */
	private function getOperationUrl($opName)
	{
		$opUrl = $this->config->getCustomServiceUrl() . '/' . $opName;
		return $opUrl;
	}
}
