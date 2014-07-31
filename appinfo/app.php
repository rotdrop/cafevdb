<?php

OC::$CLASSPATH['CAFEVDB\Admin'] = 'cafevdb/lib/admin.php';
OC::$CLASSPATH['CAFEVDB\Config'] = 'cafevdb/lib/config.php';
OC::$CLASSPATH['CAFEVDB\ConfigCheck'] = 'cafevdb/lib/config.php';
OC::$CLASSPATH['CAFEVDB\Events'] = 'cafevdb/lib/events.php';
OC::$CLASSPATH['CAFEVDB\Blog'] = 'cafevdb/lib/blog.php';
OC::$CLASSPATH['CAFEVDB\ToolTips'] = 'cafevdb/lib/tooltips.php';
OC::$CLASSPATH['CAFEVDB\L'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Util'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Error'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Ajax'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Navigation'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\mySQL'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Projects'] = 'cafevdb/lib/projects.php';
OC::$CLASSPATH['CAFEVDB\Instruments'] = 'cafevdb/lib/instruments.php';
OC::$CLASSPATH['CAFEVDB\Musicians'] = 'cafevdb/lib/musicians.php';
OC::$CLASSPATH['CAFEVDB\AddOneMusician'] = 'cafevdb/lib/musicians.php';
OC::$CLASSPATH['CAFEVDB\BulkAddMusicians'] = 'cafevdb/lib/musicians.php';
OC::$CLASSPATH['CAFEVDB\Instrumentation'] = 'cafevdb/lib/instrumentation.php';
OC::$CLASSPATH['CAFEVDB\BriefInstrumentation'] = 'cafevdb/lib/brief-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\DetailedInstrumentation'] = 'cafevdb/lib/detailed-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\ProjectInstruments'] = 'cafevdb/lib/project-instruments.php';
OC::$CLASSPATH['CAFEVDB\EmailFilter'] = 'cafevdb/lib/email.php';
OC::$CLASSPATH['CAFEVDB\Email'] = 'cafevdb/lib/email.php';
OC::$CLASSPATH['CAFEVDB\EmailHistory'] = 'cafevdb/lib/email.php';
OC::$CLASSPATH['CAFEVDB\InstrumentInsurance'] = 'cafevdb/lib/instrument-insurance.php';
OC::$CLASSPATH['CAFEVDB\PHPExcel\ValueBinder'] = 'cafevdb/lib/php-excel-functions.php';

OC::$CLASSPATH['DWEMBED\App'] = 'dokuwikiembed/lib/dokuwikiembed.php';
OC::$CLASSPATH['DWEMBED\L'] = 'dokuwikiembed/lib/util.php';
OC::$CLASSPATH['DWEMBED\Util'] = 'dokuwikiembed/lib/util.php';

OC::$CLASSPATH['OC_RoundCube_App'] = 'roundcube/lib/RoundCubeApp.php'; // <<<=== why

/* 3rd party classes */
OC::$CLASSPATH['phpMyEdit'] = 'cafevdb/3rdparty/phpMyEdit/phpMyEdit.class.php';
OC::$CLASSPATH['html2text'] = 'cafevdb/3rdparty/PHPMailer/extras/class.html2text.php';

/* Script etc. used by everyone */
OC_App::registerAdmin( 'cafevdb', 'admin-settings' );
//OCP\App::registerPersonal( 'cafevdb', 'settings' );

OCP\Util::connectHook('OC_User','post_login','CAFEVDB\Config','loginListener');
OCP\Util::connectHook('OC_User','post_setPassword','CAFEVDB\Config','changePasswordListener');

OCP\Util::connectHook('OC_Calendar','addEvent','CAFEVDB\Events','newEventListener');
OCP\Util::connectHook('OC_Calendar','editEvent','CAFEVDB\Events','changeEventListener');
OCP\Util::connectHook('OC_Calendar','deleteEvent','CAFEVDB\Events','killEventListener');
OCP\Util::connectHook('OC_Calendar','moveEvent','CAFEVDB\Events','moveEventListener');

OCP\Util::connectHook('OC_Calendar', 'deleteCalendar', 'CAFEVDB\Events', 'killCalendarListener');
OCP\Util::connectHook('OC_Calendar', 'editCalendar', 'CAFEVDB\Events', 'editCalendarListener');

/* Hurray! There is a config hook!
 *
 * CAVEAT: for the headervisibility we still need to do this
 * ourselves, so the hook is not connected ATM.
 */
//OCP\Util::connectHook('\OCP\Config', 'js', 'CAFEVDB\Config', 'jsLoadHook');

OCP\App::addNavigationEntry( array( 
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo-greyf-icon.svg' ),
	'name' => 'Camerata DB'
));
