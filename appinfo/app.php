<?php

OC::$CLASSPATH['CAFEVDB\Admin'] = 'apps/cafevdb/lib/admin.php';
OC::$CLASSPATH['CAFEVDB\Config'] = 'apps/cafevdb/lib/config.php';
OC::$CLASSPATH['CAFEVDB\Events'] = 'apps/cafevdb/lib/events.php';
OC::$CLASSPATH['CAFEVDB\ToolTips'] = 'apps/cafevdb/lib/tooltips.php';
OC::$CLASSPATH['CAFEVDB\L'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Util'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Ajax'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Navigation'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\mySQL'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Projects'] = 'apps/cafevdb/lib/projects.php';
OC::$CLASSPATH['CAFEVDB\Instruments'] = 'apps/cafevdb/lib/instruments.php';
OC::$CLASSPATH['CAFEVDB\Musicians'] = 'apps/cafevdb/lib/musicians.php';
OC::$CLASSPATH['CAFEVDB\AddOneMusician'] = 'apps/cafevdb/lib/musicians.php';
OC::$CLASSPATH['CAFEVDB\Instrumentation'] = 'apps/cafevdb/lib/instrumentation.php';
OC::$CLASSPATH['CAFEVDB\BriefInstrumentation'] = 'apps/cafevdb/lib/brief-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\DetailedInstrumentation'] = 'apps/cafevdb/lib/detailed-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\ProjectInstruments'] = 'apps/cafevdb/lib/project-instruments.php';
OC::$CLASSPATH['CAFEVDB\EmailFilter'] = 'apps/cafevdb/lib/email.php';
OC::$CLASSPATH['CAFEVDB\Email'] = 'apps/cafevdb/lib/email.php';
OC::$CLASSPATH['CAFEVDB\EmailHistory'] = 'apps/cafevdb/lib/email.php';

/* 3rd party classes */
OC::$CLASSPATH['phpMyEdit'] = 'apps/cafevdb/3rdparty/phpMyEdit/phpMyEdit.class.php';
OC::$CLASSPATH['html2text'] = 'apps/cafevdb/3rdparty/class.html2text.inc';

/* Script etc. used by everyone */
OCP\App::registerAdmin( 'cafevdb', 'admin-settings' );
//OCP\App::registerPersonal( 'cafevdb', 'settings' );

OCP\Util::connectHook('OC_User','post_login','CAFEVDB\Config','loginListener');
OCP\Util::connectHook('OC_User','post_setPassword','CAFEVDB\Config','changePasswordListener');

OCP\Util::connectHook('OC_Calendar','addEvent','CAFEVDB\Events','newEventListener');
OCP\Util::connectHook('OC_Calendar','editEvent','CAFEVDB\Events','changeEventListener');
OCP\Util::connectHook('OC_Calendar','deleteEvent','CAFEVDB\Events','killEventListener');
OCP\Util::connectHook('OC_Calendar','moveEvent','CAFEVDB\Events','moveEventListener');

OCP\Util::connectHook('OC_Calendar', 'deleteCalendar', 'CAFEVDB\Events', 'killCalendarListener');
OCP\Util::connectHook('OC_Calendar', 'editCalendar', 'CAFEVDB\Events', 'editCalendarListener');

OCP\App::addNavigationEntry( array( 
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo16x16.png' ),
	'name' => 'Camerata DB'
));
