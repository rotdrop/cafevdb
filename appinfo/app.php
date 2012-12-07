<?php

OC::$CLASSPATH['CAFEVDB_Config'] = 'apps/cafevdb/lib/config.php.inc';
OC::$CLASSPATH['CAFEVDB_Util'] = 'apps/cafevdb/lib/functions.php.inc';
OC::$CLASSPATH['CAFEVDB_mySQL'] = 'apps/cafevdb/lib/functions.php.inc';
OC::$CLASSPATH['CAFEVDB_Email'] = 'apps/cafevdb/lib/functions.php.inc';
OC::$CLASSPATH['CAFEVDB_Instruments'] = 'apps/cafevdb/lib/instruments.php';
OC::$CLASSPATH['CAFEVDB_Projects'] = 'apps/cafevdb/lib/projects.php';
OC::$CLASSPATH['CAFEVDB_Instrumentation'] = 'apps/cafevdb/lib/instrumentation.php';
OC::$CLASSPATH['CAFEVDB_ShortInstrumentation'] = 'apps/cafevdb/lib/short-instrumentation.php';

OCP\App::registerAdmin( 'cafevdb', 'settings' );

OCP\App::addNavigationEntry( array( 
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo16x16.png' ),
	'name' => 'Camerata DB'
));
