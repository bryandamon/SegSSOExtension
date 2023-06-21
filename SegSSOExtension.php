<?php

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) )
{
	exit;
}

$wgSegSSOExtensionIncludes = dirname(__FILE__) . '/includes';

## Autoload classes
$wgAutoloadClasses['SSOClient'] = $wgSegSSOExtensionIncludes . '/SSOClient.php';
$wgAutoloadClasses['SpecialSSOUserLogin'] = $wgSegSSOExtensionIncludes . '/SpecialSSOUserLogin.php';
$wgAutoloadClasses['HttpUtil'] = $wgSegSSOExtensionIncludes . '/HttpUtil.php';
$wgAutoloadClasses['SSOConfig'] = $wgSegSSOExtensionIncludes . '/SSOConfig.php';
$wgAutoloadClasses['SSOUtil'] = $wgSegSSOExtensionIncludes . '/SSOUtil.php';
$wgAutoloadClasses['SSOAuthPlugin'] = $wgSegSSOExtensionIncludes . '/SSOAuthPlugin.php';
$wgAutoloadClasses['AmsConfig'] = $wgSegSSOExtensionIncludes . '/AmsConfig.php';
$wgAutoloadClasses['AmsUtil'] = $wgSegSSOExtensionIncludes . '/AmsUtil.php';
$wgAutoloadClasses['AmsClient'] = $wgSegSSOExtensionIncludes . '/AmsClient.php';

## PHP files
require_once("$wgSegSSOExtensionIncludes/ErrorUtil.php");

## Configure the SSO client
function configureSSOClient()
{
	global $segSettings;
	
	$vendorId = $segSettings['SSO']['vendor_id'];
	$vendorUsername = $segSettings['SSO']['vendor_username'];
	$vendorPassword = $segSettings['SSO']['vendor_password'];
	$vendorBlock = $segSettings['SSO']['vendor_block'];
	$loginUrl = $segSettings['SSO']['login_url'];
	$registerUrl = $segSettings['SSO']['register_url'];
	$serviceUrl = $segSettings['SSO']['service_url'];
	
	$ssoConfig = new SSOConfig($vendorId, $vendorUsername, $vendorPassword, $vendorBlock, $loginUrl, $registerUrl, $serviceUrl);
	$ssoClient = new SSOClient($ssoConfig);
	SSOUtil::setClient($ssoClient);
}

## Configure the Ams client
function configureAmsClient()
{
	global $segSettings;
	
	$customServiceUrl = $segSettings['AMS']['custom_service_url'];
	
	$amsConfig = new AmsConfig($customServiceUrl);
	$amsClient = new AmsClient($amsConfig);
	AmsUtil::setClient($amsClient);
}

configureSSOClient();
configureAmsClient();

## Register Special pages
$wgSpecialPages['SSOUserLogin'] = 'SpecialSSOUserLogin';

## Override default UserLogin special page
function overrideUserLoginPage(&$list)
{
	$list['Userlogin'] = array( 'UnlistedSpecialPage', 'SSOUserLogin');
	// file_put_contents('php://stderr',print_r('LIST = ', TRUE));
	// file_put_contents('php://stderr',print_r($list, TRUE));
	return true;
}

function ssoLogout()
{
	try
	{
		SSOUtil::logout();
	}
	catch (Exception $e)
	{
		processException('Failed to perform SSO logout.', $e);
	}
	return true;
}

## Register Hooks
$wgHooks['SpecialPage_initList'][]='overrideUserLoginPage';
$wgHooks['UserLogout'][]='ssoLogout';

## Authentication
$ssoAuthPlugin = new SSOAuthPlugin();
$wgAuth = $ssoAuthPlugin;

// the checkSSOAuthenticationStatus() function will run for each request
$wgExtensionFunctions[] = array( 'SSOAuthPlugin', 'checkSSOAuthenticationStatus' );
