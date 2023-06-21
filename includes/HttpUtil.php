<?php

class HttpUtil
{
	/**
	 * Returns the complete current URL.
	 * @return string the complete current URL.
	 */
	public static function get_current_url()
	{
		// protocol
		$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
		$protocol = 'http';
		if($secure)
		{
			$protocol = 'https';
		}
		
		// port
		$port = $_SERVER['SERVER_PORT'];
		if(($secure && $port == '443') || (!$secure && $port == '80'))
		{
			$port = '';
		}		
		
		$server = $_SERVER['HTTP_HOST'];
		$requestUri = $_SERVER['REQUEST_URI'];
		$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : ''; 
		$queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
		
		// full url
		$fullUrl = $protocol . '://' . $server;
		if(!empty($port))
		{
			$fullUrl .= ':' . $port;
		}
		
		$fullUrl .= $requestUri . $pathInfo;
		// make sure the query string is not already in the requestUri, so that we don't add it twice
		if(!empty($queryString) && strpos($fullUrl, $queryString) === FALSE)
		{
			$fullUrl .= '?' . $queryString;
		}
		
		return $fullUrl;
	}
	
	/**
	 * Makes an HTTP request to the given URL and retrieves the XML response.
	 * If $returnNodeName is given, only the value of that XML node is returned.
	 * @param $url string the URL to request
	 * @param $postData array [optional] any post data to be sent with the http request
	 * @param $returnNodeNames array [optional] the names of the XML nodes whose values should be returned
	 * @return mixed If $returnNodeNames is specified, the values of the nodes are returned in an array.
	 * 		Otherwise the full response data is returned. Note: if $returnNodeNames is specified and contains
	 * 		only 1 element, the string value of that node is returned instead of an array. 
	 */
	public static function get_xml_response($url, array $postData = null, array $returnNodeNames = null)
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => TRUE, //return the actual result of the call
			CURLOPT_URL => $url
		);

		$result = self::make_curl_request($options, $postData);
		$responseData = $result['responseData'];

		if(isset($returnNodeNames) && !empty($returnNodeNames))
		{
			if(count($returnNodeNames) > 1)
			{
				$values = self::get_values_from_xml_response($returnNodeNames, $responseData);
				return $values;
			}
			else
			{
				// returnNodeNames contains only 1 name, return the string value of that node
				$value = self::get_value_from_xml_response($returnNodeNames[0], $responseData);
				return $value;
			}
		}
		else
		{
			return $responseData;
		}
	}
	
	/**
	 * Makes an HTTP request to the given URL and retrieves the JSON response.
	 * @param $url string the URL to request
	 * @param $postData array [optional] any post data to be sent with the http request
	 * @return mixed an object representation of the json response. 
	 */
	public static function get_json_response($url, array $postData = null)
	{
		$headerArray = array(
			'Accept: application/json; charset=utf-8',
			'Content-length: 0'
		);
		
		$options = array(
			CURLOPT_RETURNTRANSFER => TRUE, //return the actual result of the call
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => $headerArray,
			CURLOPT_POST => TRUE
		);

		$result = self::make_curl_request($options, $postData);
		$responseData = $result['responseData'];

		$jsonObj = json_decode($responseData);
		return $jsonObj;
	}
	
	/**
	 * Makes an HTTP HEAD request to the given URL and returns the HTTP headers along with the response code.
	 * @param $url string the URL to request
	 * @param $postData array [optional] any post data to be sent with the http request 
	 * @param $cookieData string [optional] any cookie data to be sent with http request. Must be in the form:
	 * 		cookie1=value1,cookie2=value2,..
	 * @param $followRedirects bool [optional] whether to follow redirects. Defaults to false.
	 * @return a 2-element array containing the 'responseCode' and 'headers' respectively.
	 */
	public static function get_headers_only($url, array $postData = null, $cookieData = null, $followRedirects = false)
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => TRUE, //return the actual result of the call
			CURLOPT_NOBODY => TRUE, //only perform a HEAD request
			CURLOPT_HEADER => TRUE, //include the header in the result
			CURLOPT_URL => $url
		);

		if(!$followRedirects)
		{
			//do not follow redirects
			$options[CURLOPT_FOLLOWLOCATION] = FALSE;
			$options[CURLOPT_MAXREDIRS] = 0;
		}

		if(isset($cookieData) && !empty($cookieData))
		{
			$options[CURLOPT_COOKIE] = $cookieData;
		}
		
		$result = self::make_curl_request($options, $postData);
		
		$responseCode = $result['responseCode'];
		$headers = self::parse_headers($result['responseData']);
		
		$rtnArray = array(
			'responseCode' => $responseCode,
			'headers' => $headers
		);
		
		return $rtnArray;
	}

	/**
	 * A wrapper around base64_encode which modifies the encoded string to make it URL-safe.
	 * @param $data
	 * @return string a URL-safe base64-encoded string
	 */
	public static function base64UrlEncode($data)
	{
  		return strtr(rtrim(base64_encode($data), '='), '+/', '-_');
	}

	/**
	 * A wrapper around base64_decode which handles URL-safe base64-encoded strings. 
	 * @param $base64 string a URL-safe base64-encoded string
	 * @return string
	 */
	public static function base64UrlDecode($base64)
	{
  		return base64_decode(strtr($base64, '-_', '+/'));
	}
	
	/**
	 * Makes an HTTP request using a cURL session.
	 * @param $options array the cURL options to use for this request
	 * @param $postDataArray array [optional] if set, the array data will be sent as POST data
	 * 		along with the request
	 * @return a 2-element array containing the 'responseCode' and 'responseData' respectively.
	 */
	private static function make_curl_request(array $options, array $postDataArray = null)
	{
		$ch = curl_init();
		curl_setopt_array($ch, $options);

		if(isset($postDataArray))
		{
			$postData = "";
			$postDataArrayKeys = array_keys($postDataArray);
			for ($i=0; $i<count($postDataArrayKeys); $i++)
			{
				if($i > 0)
				{
					$postData .= "&";
				}

				$key = $postDataArrayKeys[$i];
				$value = $postDataArray[$key];

				$postData .= $key . "=" . $value;
			}

			$contentLength = strlen($postData);

			$headerArray = array (
			'Content-type: application/x-www-form-urlencoded',
			'Content-length: ' . $contentLength
			);

			curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			curl_setopt($ch, CURLOPT_POST, TRUE);
		}

		$result = curl_exec($ch);
		
		$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		if (curl_errno($ch))
		{
			$exMsg = "Curl error: [" . curl_error($ch) . "] while requesting URL: "
					. curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
			curl_close($ch);
			throw new Exception($exMsg);	
		}
		else if ($responseCode >= 400)
		{
			$exMsg = "Curl received response code: [" . $responseCode . "] while requesting URL: "
					. curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . "\nResponse received: [" . rtrim($result) . "]";
			curl_close($ch);
			throw new Exception($exMsg);
		}
		else
		{
			$returnArray = array(
				"responseCode" => $responseCode,
				"responseData" => $result
			);

			curl_close($ch);
			return $returnArray;
		}
	}

	/**
	 * Parses the HTTP response header into an array of header names and values.
	 * @param $headersStr string the HTTP response header string
	 * @return an array of header names and values
	 */
	private static function parse_headers($headersStr)
	{
		$headerArray = array();

		$headers = explode("\n", $headersStr);
		foreach ($headers as $header)
		{
			$parts = explode(":", trim($header), 2);

			if(count($parts) < 2 || substr($parts[0], 0, 4) == "HTTP")
			{
				continue;
			}

			$headerKey = trim($parts[0]);
			$headerValue = trim($parts[1]);

			$headerArray[$headerKey] = $headerValue;
		}

		return $headerArray;
	}

	/**
	 * Parses the given responseData as XML then retrieves the value of the named node.
	 * @param $name string the name of the node whose value should be returned
	 * @param $responseData string a well-formatted XML document
	 * @return string the value of the named node.
	 */
	private static function get_value_from_xml_response($name, $responseData)
	{
		$xml = simplexml_load_string($responseData);
		$value = self::get_value_from_xml($name, $xml);

		return $value;
	}
	
	/**
	 * Parses the given responseData as XML then retrieves the values of the named nodes.
	 * @param $names array the names of the nodes whose values should be returned
	 * @param $responseData string a well-formatted XML document
	 * @return an array containing the names and values of the named nodes.
	 */
	private static function get_values_from_xml_response(array $names, $responseData)
	{
		$xml = simplexml_load_string($responseData);
		$result = array();
		
		foreach ($names as $name)
		{
			$value = self::get_value_from_xml($name, $xml);
			$result[$name] = $value;
		}

		return $result;
	}
	
	/**
	 * Retrieves the value of the named XML node. The noded is assumed to be a first level child.
	 * @param $name string the name of the node whose value should be returned
	 * @param $xml an XML element/document
	 * @return string the value of the named node.
	 */
	private static function get_value_from_xml($name, $xml)
	{
		$value = "";

		foreach ($xml->children() as $child)
		{
			if($child->getName() == $name)
			{
				$value = (string)$child;
			}
		}

		return $value;
	}
}
