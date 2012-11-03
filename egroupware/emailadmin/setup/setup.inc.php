<?php
/**
 * eGroupware EMailAdmin - Setup
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @author Klaus Leithoff <kl@stylite.de>
 * @package emailadmin
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: setup.inc.php 31896 2010-09-05 16:26:30Z ralfbecker $
 */

$setup_info['emailadmin']['name']      = 'emailadmin';
$setup_info['emailadmin']['title']     = 'EMailAdmin';
$setup_info['emailadmin']['version']   = '1.8';
$setup_info['emailadmin']['app_order'] = 10;
$setup_info['emailadmin']['enable']    = 2;
$setup_info['emailadmin']['index']     = 'emailadmin.emailadmin_ui.listProfiles';

$setup_info['emailadmin']['author'] = 'Lars Kneschke';
$setup_info['emailadmin']['license']  = 'GPL';
$setup_info['emailadmin']['description'] =
	'A central Mailserver management application for EGroupWare.';
$setup_info['emailadmin']['note'] =
	'';
$setup_info['emailadmin']['maintainer'] = array(
	'name'  => 'Leithoff, Klaus',
	'email' => 'kl@stylite.de'
);

$setup_info['emailadmin']['tables'][]	= 'egw_emailadmin';

/* The hooks this app includes, needed for hooks registration */
#$setup_info['emailadmin']['hooks'][] = 'preferences';
$setup_info['emailadmin']['hooks']['admin'] = 'emailadmin_hooks::admin';
$setup_info['emailadmin']['hooks']['edit_user'] = 'emailadmin_hooks::edit_user';
$setup_info['emailadmin']['hooks']['view_user'] = 'emailadmin_hooks::edit_user';
$setup_info['emailadmin']['hooks']['edit_group'] = 'emailadmin_hooks::edit_group';
$setup_info['emailadmin']['hooks']['group_manager'] = 'emailadmin_hooks::edit_group';
$setup_info['emailadmin']['hooks']['deleteaccount'] = 'emailadmin_hooks::deleteaccount';
$setup_info['emailadmin']['hooks']['deletegroup'] = 'emailadmin_hooks::deletegroup';
/* Dependencies for this app to work */
$setup_info['emailadmin']['depends'][] = array(
	'appname'  => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['emailadmin']['depends'][] = array(
	'appname'  => 'egw-pear',
	'versions' => Array('1.8','1.9')
);
// installation checks for felamimail
$setup_info['emailadmin']['check_install'] = array(
	'' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
	'Auth_SASL' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
	'Net_IMAP' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
	'imap' => array(
		'func' => 'extension_check',
		'from' => 'EMailAdmin',
	),
);
