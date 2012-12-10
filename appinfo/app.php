<?php

OC::$CLASSPATH['CAFEVDB\Config'] = 'apps/cafevdb/lib/config.php';
OC::$CLASSPATH['CAFEVDB\ToolTips'] = 'apps/cafevdb/lib/tooltips.php';
OC::$CLASSPATH['CAFEVDB\Util'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Navigation'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\mySQL'] = 'apps/cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\Projects'] = 'apps/cafevdb/lib/projects.php';
OC::$CLASSPATH['CAFEVDB\Instruments'] = 'apps/cafevdb/lib/instruments.php';
OC::$CLASSPATH['CAFEVDB\Musicians'] = 'apps/cafevdb/lib/musicians.php';
OC::$CLASSPATH['CAFEVDB\Instrumentation'] = 'apps/cafevdb/lib/instrumentation.php';
OC::$CLASSPATH['CAFEVDB\BriefInstrumentation'] = 'apps/cafevdb/lib/brief-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\DetailedInstrumentation'] = 'apps/cafevdb/lib/detailed-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\EmailFilter'] = 'apps/cafevdb/lib/email.php';
OC::$CLASSPATH['CAFEVDB\Email'] = 'apps/cafevdb/lib/email.php';

/* 3rd party classes */
OC::$CLASSPATH['phpMyEdit'] = 'apps/cafevdb/3rdparty/phpMyEdit/phpMyEdit.class.php';
OC::$CLASSPATH['html2text'] = 'apps/cafevdb/3rdparty/phpMyEdit/html2text.inc';

OCP\App::registerAdmin( 'cafevdb', 'settings' );

OCP\App::addNavigationEntry( array( 
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo16x16.png' ),
	'name' => 'Camerata DB'
));
