<?php

class SSOAuthPlugin extends AuthPlugin
{
	/**
	 * Check whether there exists a user account with the given name.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @return bool
	 */
	public function userExists( $username )
	{
		// Pretend all users exist.  This is checked by authenticateUserData to
		// determine if a user exists in our 'db'.  By returning true we tell it that
		// it can create a local wiki user automatically.
		return true;
	}

	/**
	 * Check if a username+password pair is a valid login.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @param $password String: user password.
	 * @return bool
	 */
	public function authenticate( $username, $password )
	{
		// This is handled outside of here, so just check for the dummy password.
		// This means that our SSO code initiated the login request.
		if($password == 'DummyP@ssword')
		{
			return true;
		}
		
		// if it's not the dummy password, check if this is one of the approved bot logins
		$lowercaseusername = strtolower($username);
		
		global $segSettings;
		
		if(isset($segSettings['approvedbots'][$lowercaseusername]))
		{
			if($password == $segSettings['approvedbots'][$lowercaseusername])
			{
				error_log('Login successful for bot: ' . $lowercaseusername);
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Modify options in the login template.
	 *
	 * @param $template UserLoginTemplate object.
	 * @param $type String 'signup' or 'login'.
	 */
	public function modifyUITemplate( &$template, &$type )
	{
		// Doesn't apply since we're not using mediawiki's login page.
		$template->set( 'usedomain', false );
	}

	/**
	 * Check to see if the specific domain is a valid domain.
	 *
	 * @param $domain String: authentication domain.
	 * @return bool
	 */
	public function validDomain( $domain )
	{
		return true;
	}

	/**
	 * When a user logs in, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param $user User object
	 */
	public function updateUser( &$user )
	{
		$this->updateUserInfo($user);

		$user->saveSettings();

		return true;
	}

	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * If you don't automatically create accounts, you must still create
	 * accounts in some way. It's not possible to authenticate without
	 * a local account.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return Boolean
	 */
	public function autoCreate()
	{
		return true;
	}

	/**
	 * Can users change their passwords?
	 *
	 * @return bool
	 */
	public function allowPasswordChange()
	{
		// Disallow password change
		return false;
	}

	/**
	 * Set the given password in the authentication database.
	 * As a special case, the password may be set to null to request
	 * locking the password to an unusable value, with the expectation
	 * that it will be set later through a mail reset or other method.
	 *
	 * Return true if successful.
	 *
	 * @param $user User object.
	 * @param $password String: password.
	 * @return bool
	 */
	public function setPassword( $user, $password )
	{
		// This should not be called because we do not allow password change.
		// Always fail by returning false.
		return false;
	}

	/**
	 * Update user information in the external authentication database.
	 * Return true if successful.
	 *
	 * @param $user User object.
	 * @return Boolean
	 */
	public function updateExternalDB( $user )
	{
		// We don't support this but we have to return true for preferences to save.
		return true;
	}

	/**
	 * Check to see if external accounts can be created.
	 * Return true if external accounts can be created.
	 * @return Boolean
	 */
	public function canCreateAccounts()
	{
		return false;
	}

	/**
	 * Add a user to the external authentication database.
	 * Return true if successful.
	 *
	 * @param $user User: only the name should be assumed valid at this point
	 * @param $password String
	 * @param $email String
	 * @param $realname String
	 * @return Boolean
	 */
	public function addUser( $user, $password, $email = '', $realname = '' )
	{
		// We don't support adding users to the external DB. Always fail by returning false.
		return false;
	}

	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return Boolean
	 */
	public function strict()
	{
		return true;
	}

	/**
	 * Check if a user should authenticate locally if the global authentication fails.
	 * If either this or strict() returns true, local authentication is not used.
	 *
	 * @param $username String: username.
	 * @return Boolean
	 */
	public function strictUserAuth( $username )
	{
		return true;
	}

	/**
	 * When creating a user account, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param $user User object.
	 * @param $autocreate Boolean: True if user is being autocreated on login
	 */
	public function initUser( &$user, $autocreate = false )
	{
		$user->mEmailAuthenticated = wfTimestampNow();

		$this->updateUserInfo($user);
	}

	/**
	 * If you want to munge the case of an account name before the final
	 * check, now is your chance.
	 */
	public function getCanonicalName( $username )
	{
		return $username;
	}

	private function updateUserInfo(&$user)
	{
		try
		{
			$timssCustomerId = SSOUtil::getTimssCustomerId();

			if(!empty($timssCustomerId))
			{
				$user->setOption('amsCustomerId', $timssCustomerId);
					
				$customerInfo = AmsUtil::getCustomerBasicInfo($timssCustomerId);
					
				$user->setRealName($customerInfo->LabelName);
				$user->setEmail($customerInfo->PrimaryEmail);
					
				// handle membership
				$memberType = $customerInfo->MembershipType;
				if(!empty($memberType))
				{
					$user->setOption('memberType', $memberType);
					$user->addGroup('Member');
				}
				else
				{
					$user->removeGroup('Member');
				}
			}
		}
		catch (Exception $e)
		{
			processException('Failed to import user info.', $e);
		}
	}

	/**
	 * Checks the SSO authentication status to provide auto login/logout functionality.
	 * This function is registered as an extension function (i.e. $wgExtensionFucntions)
	 * so that it runs on every request. 
	 */
	public static function checkSSOAuthenticationStatus()
	{
		global $wgRequest, $wgOut, $wgUser;

		// file_put_contents('php://stderr', print_r('checkSSOAuthenticationStatus', TRUE));

		$title = $wgRequest->getVal('title');

		// if request for login or logout, don't do anything
		if($title == Title::makeName(NS_SPECIAL, 'Userlogin') ||
			$title == Title::makeName(NS_SPECIAL, 'UserLogin') ||
			$title == Title::makeName(NS_SPECIAL, 'SSOUserLogin') ||
			$title == Title::makeName(NS_SPECIAL, 'Userlogout') ||
			$title == Title::makeName(NS_SPECIAL, 'UserLogout'))
		{
			return;
		}
		
		if ($wgUser->isLoggedIn())
		{
			self::autoLogoutIfNecessary();
		}
		else
		{
			self::autoLoginIfNecessary();
		}
	}
	
	/**
	 * Checks if logged out from SSO elsewhere, and if so logs the current user out from mediawiki.
	 */
	private static function autoLogoutIfNecessary()
	{
		global $wgOut;
		
		try
		{
			$authenticated = SSOUtil::isAuthenticated();
			// file_put_contents('php://stderr', print_r('autoLogoutIfNecessary IsAuthnticated = ' . $authenticated, TRUE));

			if(!$authenticated)
			{
				$logoutPage = self::makeSpecialUrl('Userlogout');
				$wgOut->redirect($logoutPage);
			}
		}
		catch (Exception $e)
		{
			processException('Failed to auto logout user.', $e);
		}
	}
	
	/**
	 * Checks if logged in with SSO elsewhere, and if so logs the current user in to mediawiki.
	 */
	private static function autoLoginIfNecessary()
	{
		global $wgOut;
		
		try
		{
			if(isset($_COOKIE["SSO"]))
			{
				$loginPage = self::makeSpecialUrl('Userlogin', true);
				$ssoLoginUrl = SSOUtil::getSSOLoginUrl($loginPage);
					
				$cookieData = "SSO=" . $_COOKIE["SSO"];
					
				$result = HttpUtil::get_headers_only($ssoLoginUrl, null, $cookieData, false);
					
				if($result['responseCode'] == 302)
				{
					if (!isset($_COOKIE['username']))
					{
					$headers = $result['headers'];
					$location = $headers['Location'];
					
					$wgOut->redirect($location);
					}
				}
			}
		}
		catch (Exception $e)
		{
			processException('Failed to auto login user.', $e);
		}
	}
	
	/**
	 * Constructs a complete URL to the named special page and sets the current page
	 * and query parameters as the 'returnto' and 'returntoquery' parameters, respectively.
	 * @param $specialPageName string the name of the special page
	 * @param $base64encodeReturnToQuery bool [optional] whether to encode the returntoquery using
	 * 		base64 encoding instead of url-encoding. If true, the 'b64returntoquery' parameter
	 * 		will be set on the resulting URL instead of 'returntoquery'.
	 * @return string the complete URL to the named special page.
	 */
	private static function makeSpecialUrl($specialPageName, $base64encodeReturnToQuery = false)
	{
		global $wgRequest;
		
		$query = array();
		if ( !$wgRequest->wasPosted() )
		{
			$query = $wgRequest->getValues();
			unset( $query['title'] );
			unset( $query['returnto'] );
			unset( $query['returntoquery'] );
		}
		
		$queryString = wfArrayToCGI($query);
		
		$mainPage = Title::newMainPage();
		$title = $wgRequest->getVal('title', $mainPage->getDBkey());
		$page = $wgRequest->getVal('returnto', $title);
		$rtq = $wgRequest->getVal('returntoquery', $queryString);
		
		$returnto = "returnto=$page";
		if($rtq != '')
		{
			if($base64encodeReturnToQuery)
			{
				$b64rtq = HttpUtil::base64UrlEncode($rtq);
				$returnto .= "&b64returntoquery=$b64rtq";
			}
			else
			{
				$rtq = wfUrlencode($rtq);
				$returnto .= "&returntoquery=$rtq";				
			}
		}
		
		$specialPage = Title::newFromText($specialPageName, NS_SPECIAL);
		$specialUrl = $specialPage->getFullURL($returnto);
		
		return $specialUrl;
	}
}
