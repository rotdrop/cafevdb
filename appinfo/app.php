<?php
/**Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

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
OC::$CLASSPATH['CAFEVDB\Finance'] = 'cafevdb/lib/finance.php';
OC::$CLASSPATH['CAFEVDB\SepaDebitMandates'] = 'cafevdb/lib/sepa-debit-mandates.php';

OC::$CLASSPATH['DWEMBED\App'] = 'dokuwikiembed/lib/dokuwikiembed.php';
OC::$CLASSPATH['DWEMBED\L'] = 'dokuwikiembed/lib/util.php';
OC::$CLASSPATH['DWEMBED\Util'] = 'dokuwikiembed/lib/util.php';

OC::$CLASSPATH['OC_RoundCube_App'] = 'roundcube/lib/RoundCubeApp.php'; // <<<=== why

/* 3rd party classes */
OC::$CLASSPATH['phpMyEdit'] = 'cafevdb/3rdparty/phpMyEdit/phpMyEdit.class.php';
OC::$CLASSPATH['html2text'] = 'cafevdb/3rdparty/PHPMailer/extras/class.html2text.php';
OC::$CLASSPATH['IBAN'] = 'cafevdb/3rdparty/php-iban/oophp-iban.php';
OC::$CLASSPATH['malkusch\bav\BAV'] = 'cafevdb/3rdparty/bav/autoloader/autoloader.php';

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
 * ourselves, so the hook is not connected ATM. The point is that we
 * sometimes transfer the value of the headervisibility as cgi-arg,
 * but this is not available to the oc.js config script.
 */
//OCP\Util::connectHook('\OCP\Config', 'js', 'CAFEVDB\Config', 'jsLoadHook');

OCP\App::addNavigationEntry( array( 
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo-greyf-icon.svg' ),
	'name' => 'Camerata DB'
));
