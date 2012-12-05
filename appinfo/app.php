<?php

OC::$CLASSPATH['CAFEV_DB_Config'] = 'apps/cafevdb/lib/config.php.inc';
OC::$CLASSPATH['CAFEV_DB_Util'] = 'apps/cafevdb/lib/functions.php.inc';
OC::$CLASSPATH['CAFEV_DB_Instruments'] = 'apps/cafevdb/lib/Instruments.php';
OC::$CLASSPATH['CAFEV_DB_Projects'] = 'apps/cafevdb/lib/Projekte.php';

OCP\App::registerAdmin( 'cafevdb', 'settings' );

OCP\App::addNavigationEntry( array( 
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo16x16.png' ),
	'name' => 'Camerata DB'
));
