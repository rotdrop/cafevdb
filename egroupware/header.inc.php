<?php
/**
 * eGroupWare - configuration file
 *
 * Use eGroupWare's setup to create or edit this configuration file.
 * You do NOT need to copy and edit this file manually!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * (This file was originaly written by Dan Kuykendall)
 * @version $Id: header.inc.php.template 33300 2010-12-06 08:39:04Z leithoff $
 */

// allow to migrate from phpgw_info to egw_info
if (!isset($GLOBALS['egw_info']) || in_array($GLOBALS['egw_info']['flags']['currentapp'],array('jinn','mydms','tts')))
{
	if (!isset($GLOBALS['egw_info']))
	{
		$GLOBALS['egw_info'] =& $GLOBALS['phpgw_info'];
	}
	else
	{
		$GLOBALS['phpgw_info'] =& $GLOBALS['egw_info'];
	}
	$GLOBALS['egw_info']['flags']['phpgw_compatibility'] = true;
}

// eGW install dir, need to be changed if you copy the server to an other directory
define('EGW_SERVER_ROOT','/is/htdocs/wp1173590_IDUP6S1903/admin/egroupware');

// other pathes depending on the one above
define('EGW_INCLUDE_ROOT',EGW_SERVER_ROOT);
define('EGW_API_INC',EGW_INCLUDE_ROOT.'/phpgwapi/inc');

// who is allowed to make changes to THIS config file via eGW's setup
$GLOBALS['egw_info']['server']['header_admin_user'] = 'cafev';
$GLOBALS['egw_info']['server']['header_admin_password'] = '671fec72821a2cdfc7452d9afd2f1c2e';

// restrict the access to setup to certain (comma separated) IPs or domains
$GLOBALS['egw_info']['server']['setup_acl'] = '';

/* eGroupWare domain-specific db settings */
$GLOBALS['egw_domain']['default'] = array(
	'db_host' => 'localhost',
	'db_port' => '3306',
	'db_name' => 'db1173590-egroupware',
	'db_user' => 'db1173590-egw',
	'db_pass' => '!Six6Lobed',
	// Look at the README file
	'db_type' => 'mysql',
	// This will limit who is allowed to make configuration modifications
	'config_user'   => 'domain',
	'config_passwd' => '671fec72821a2cdfc7452d9afd2f1c2e'
);


/*
** If you want to have your domains in a select box, change to True
** If not, users will have to login as user@domain
** Note: This is only for virtual domain support, default domain users (that's everyone
** form the first domain or if you have only one) can login only using just there loginid.
*/
$GLOBALS['egw_info']['server']['show_domain_selectbox'] = false;

$GLOBALS['egw_info']['server']['db_persistent'] = false;

/*
** used session handler: egw_session_files works for all build in php session handlers
** other handlers (like egw_session_memcache) can be enabled here
*/
$GLOBALS['egw_info']['server']['session_handler'] = 'egw_session_files';

/* Select which login template set you want, most people will use idots */
$GLOBALS['egw_info']['login_template_set'] = 'idots';

/* This is used to control mcrypt's use */
$GLOBALS['egw_info']['server']['mcrypt_enabled'] = true;

/*
** This is a random string used as the initialization vector for mcrypt
** feel free to change it when setting up eGrouWare on a clean database,
** but you must not change it after that point!
** It should be around 30 bytes in length.
*/
$GLOBALS['egw_info']['server']['mcrypt_iv'] = 'XMpoQ9eH8jnm23,mclosnhrdlkdNg7';

if(!isset($GLOBALS['egw_info']['flags']['nocachecontrol']) || !$GLOBALS['egw_info']['flags']['nocachecontrol'])
{
	header('Cache-Control: no-cache, must-revalidate');  // HTTP/1.1
	header('Pragma: no-cache');                          // HTTP/1.0
}
else
{
	// allow caching by browser
	session_cache_limiter('private_no_expire');
}

$GLOBALS['egw_info']['flags']['page_start_time'] = microtime(true);

define('DEBUG_API',  False);
define('DEBUG_APP',  False);

include(EGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php');
$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = $setup_info['phpgwapi']['version'];
$GLOBALS['egw_info']['server']['versions']['current_header'] = $setup_info['phpgwapi']['versions']['current_header'];
unset($setup_info);
$GLOBALS['egw_info']['server']['versions']['header'] = '1.29';

if(!isset($GLOBALS['egw_info']['flags']['noapi']) || !$GLOBALS['egw_info']['flags']['noapi'])
{
	if (substr($_SERVER['SCRIPT_NAME'],-7) != 'dav.php')	// dont do it for webdav/groupdav, as we can not safely switch it off again
	{
		ob_start();	// to prevent error messages to be send before our headers
	}
	require_once(EGW_API_INC . '/functions.inc.php');
}
else
{
	require_once(EGW_API_INC . '/common_functions.inc.php');
}

/*
  Leave off the final php closing tag, some editors will add
  a \n or space after which will mess up cookies later on
*/
