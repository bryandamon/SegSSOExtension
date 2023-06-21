<?php

/**
 * Handles all interaction with the SSO server.
 * @see SSOUtil
 */
class SSOClient
{
	/*** Operation names ***/
	const OP_VENDOR_TOKEN_ENCRYPT = "VendorTokenEncrypt";
	const OP_CUSTOMER_TOKEN_DECRYPT = "CustomerTokenDecrypt";
	const OP_SSO_CUSTOMER_TOKEN_IS_VALID = "SSOCustomerTokenIsValid";
	const OP_SSO_CUSTOMER_LOGOUT = "SSOCustomerLogout";
	const OP_SSO_CUSTOMER_GET = "SSOCustomerGet";
	const OP_SSO_CUSTOMER_GET_BY_CUSTOMER_TOKEN = "SSOCustomerGetByCustomerToken";
	const OP_TIMSS_CUSTOMER_IDENTIFIER_GET = "TIMSSCustomerIdentifierGet";

	/*** Session keys ***/
	const SK_CUSTOMER_TOKEN = "SEG_SSO_CT";
	
	private $config;

	function __construct(SSOConfig $ssoConfig)
	{
		$this->config = $ssoConfig;
	}

	/**
	 * Validates the current customer token with the SSO server.
	 * @return boolean true if a customer token is found and valid, false otherwise.
	 */
	public function isAuthenticated()
	{
		$authenticated = false;
		
		$customerToken = $this->retrieveCustomerToken();

		if(isset($customerToken) && !empty($customerToken))
		{
			$newCustomerToken = ''; // will be populated by the validateCustomerToken() method
			$authenticated = $this->validateCustomerToken($customerToken, $newCustomerToken);
			
			if($authenticated)
			{
				if(!empty($newCustomerToken))
				{
					// save the new customer token
					$_SESSION[self::SK_CUSTOMER_TOKEN] = $newCustomerToken;
				} 
			}
			else 
			{
				// destroy any existing customer token
				unset($_SESSION[self::SK_CUSTOMER_TOKEN]);
			}
		}
		
		return $authenticated;
	}
	
	/**
	 * Constructs the complete SSO login URL containing the parameters that the SSO server expects.
	 * @param $returnUrl string [optional] if specified, the given url will be used as the return URL
	 * 		after a successful login. Otherwise the current URL will be used.
	 * @return string the complete SSO login URL.
	 */
	public function getSSOLoginUrl($returnUrl = '')
	{
		if(empty($returnUrl))
		{
			$returnUrl = HttpUtil::get_current_url();
		}
		
		$vendorToken = $this->encryptVendorToken($returnUrl);
		$vendorId = $this->config->getVendorId();
		$baseUrl = $this->config->getLoginUrl(); 
		
		$fullLoginUrl = $baseUrl . "?vi=" . $vendorId . "&vt=" . $vendorToken;
		return $fullLoginUrl;
	}

	/**
	* Constructs the complete SSO register URL containing the parameters that the SSO server expects.
	* @param $returnUrl string [optional] if specified, the given url will be used as the return URL
	* 		after a successful register. Otherwise the current URL will be used.
	* @return string the complete SSO register URL.
	*/
	public function getSSORegisterUrl($returnUrl = '')
	{
		if(empty($returnUrl))
		{
			$returnUrl = HttpUtil::get_current_url();
		}
	
		$vendorToken = $this->encryptVendorToken($returnUrl);
		$vendorId = $this->config->getVendorId();
		$baseUrl = $this->config->getRegisterUrl();
	
		$fullRegisterUrl = $baseUrl . "?vi=" . $vendorId . "&vt=" . $vendorToken;
		return $fullRegisterUrl;
	}
	
	/**
	 * Retrieves the SSO user information.
	 * If $timssCustomerId is specified, it will be used to do the lookup. Otherwise, the customer
	 * token will be used.
	 * @param $timssCustomerId string [optional] the TIMSS customer Id.
	 * @return mixed an associative array containing user SSO data, or false if user does not exist.
	 */
	public function getCustomer($timssCustomerId = null)
	{
		if(!isset($timssCustomerId) || empty($timssCustomerId))
		{
			$timssCustomerId = $this->getTimssCustomerId();
		}
		
		$result = $this->getCustomerByTimssId($timssCustomerId);
		return $result;
	}
	
	/**
	 * Retrieves the TIMSS customer Id for the current SSO user.
	 * @return string the TIMSS customer Id.
	 */
	public function getTimssCustomerId()
	{
		$customerToken = $_SESSION[self::SK_CUSTOMER_TOKEN];
			
		$serviceUrl = $this->getOperationUrl(self::OP_TIMSS_CUSTOMER_IDENTIFIER_GET);
			
		$params = array(
			'vendorUsername' =>  $this->config->getVendorUsername(),
			'vendorPassword' =>  $this->config->getVendorPassword(),
			'customerToken' => $customerToken
		);
			
		$timssCustomerId = HttpUtil::get_xml_response($serviceUrl, $params, array("CustomerIdentifier"));
		return $timssCustomerId;
	}
	
	/**
	 * Logs out the current user from the SSO server.
	 */
	public function logout()
	{
		$customerToken = '';
		if(isset($_SESSION[self::SK_CUSTOMER_TOKEN]))
		{
			$customerToken = $_SESSION[self::SK_CUSTOMER_TOKEN];
		}
		
		if(!empty($customerToken))
		{
			$serviceUrl = $this->getOperationUrl(self::OP_SSO_CUSTOMER_LOGOUT);
			
			$params = array(
				'vendorUsername' =>  $this->config->getVendorUsername(),
				'vendorPassword' =>  $this->config->getVendorPassword(),
				'customerToken' => $customerToken
			);

			HttpUtil::get_xml_response($serviceUrl, $params);
			
			// destroy the customer token
			unset($_SESSION[self::SK_CUSTOMER_TOKEN]);
		}
	}
	
	/**
	 * Retrieves the SSO user information using the TIMSS customer Id.
	 * @param $timssCustomerId string the TIMSS customer Id.
	 * @return mixed an associative array containing user SSO data, or false if user does not exist.
	 */
	private function getCustomerByTimssId($timssCustomerId)
	{
		$serviceUrl = $this->getOperationUrl(self::OP_SSO_CUSTOMER_GET);
			
		$params = array(
			'vendorUsername' =>  $this->config->getVendorUsername(),
			'vendorPassword' =>  $this->config->getVendorPassword(),
			'TIMSSCustomerId' => $timssCustomerId
		);
		
		$result = HttpUtil::get_xml_response($serviceUrl, $params, array("UserExists", "UserName", "Email"));
		
		if(strtolower($result["UserExists"]) !== 'true')
		{
			return false;
		}
		
		return $result;
	}

	/**
	 * Encrypts the given return URL into a vendor token.
	 * @param $returnUrl string the return URL
	 * @return string the encrypted vendor token
	 */
	private function encryptVendorToken($returnUrl)
	{
		$serviceUrl = $this->getOperationUrl(self::OP_VENDOR_TOKEN_ENCRYPT);
		
		// encode returnUrl to avoid loss of parameters
		$encReturnUrl = urlencode($returnUrl);
		
		$params = array(
			'vendorUsername' =>  $this->config->getVendorUsername(),
			'vendorPassword' =>  $this->config->getVendorPassword(),
			'vendorBlock' =>  $this->config->getVendorBlock(),
			'url' => $encReturnUrl
		);

		$vendorToken = HttpUtil::get_xml_response($serviceUrl, $params, array("VendorToken"));
		return $vendorToken;
	}
	
	/**
	 * Decrypts the given customer token.
	 * @param $encryptedtoken the encrypted customer token
	 * @return string the decrypted customer token
	 */
	private function decryptCustomerToken($encryptedtoken)
	{
		$serviceUrl = $this->getOperationUrl(self::OP_CUSTOMER_TOKEN_DECRYPT);
		
		$params = array(
			'vendorUsername' =>  $this->config->getVendorUsername(),
			'vendorPassword' =>  $this->config->getVendorPassword(),
			'vendorBlock' =>  $this->config->getVendorBlock(),
			'customerToken' => $encryptedtoken
		);

		$customerToken = HttpUtil::get_xml_response($serviceUrl, $params, array("CustomerToken"));
		return $customerToken;
	}
	
	/**
	 * Validates the given customer token with the SSO server.
	 * If the token is valid, the $newCustomerToken reference is populated with the new customer
	 * token returned by the SSO server.
	 * @param $customerToken string the customer token to be validated
	 * @param $newCustomerToken string a reference to a variable that will be populated with
	 * 		the new customer token returned by the SSO server
	 * @return boolean whether the customer token is valid.
	 */
	private function validateCustomerToken($customerToken, &$newCustomerToken)
	{
		$serviceUrl = $this->getOperationUrl(self::OP_SSO_CUSTOMER_TOKEN_IS_VALID);
		
		$params = array(
			'vendorUsername' =>  $this->config->getVendorUsername(),
			'vendorPassword' =>  $this->config->getVendorPassword(),
			'customerToken' => $customerToken
		);

		$result = HttpUtil::get_xml_response($serviceUrl, $params, array("Valid", "NewCustomerToken"));
		$valid = (strtolower($result["Valid"]) === 'true');
		
		if($valid)
		{
			$newCustomerToken = $result["NewCustomerToken"];
		}
		
		return $valid;
	}

	/**
	 * Retrieves the customer token. First the request is searched, then the session.
	 * @return the string customer token or null if not found.
	 */
	private function retrieveCustomerToken()
	{
		$customerToken = null;
		
		// first check for a 'ct' parameter on the URL
		if (isset($_GET["ct"]))
		{
			$encryptedToken = $_GET["ct"];
			$customerToken = $this->decryptCustomerToken($encryptedToken);
		}
		
		// if 'ct' parameter isn't present, check for a saved token in the session
		elseif (isset($_SESSION[self::SK_CUSTOMER_TOKEN]))
		{
			$customerToken = $_SESSION[self::SK_CUSTOMER_TOKEN];
		}
		
		return $customerToken;
	}
	
	/**
	 * Generates the full web service URL for the given operation name.
	 * @param $opName string the name of the web service operation
	 * @return string the full web service URL for the given operation name.
	 */
	private function getOperationUrl($opName)
	{
		$opUrl = $this->config->getServiceUrl() . '/' . $opName;
		return $opUrl;
	}
}
