<?php
/* Orchestra member, musician and project management application.
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
OC::$CLASSPATH['CAFEVDB\Contacts'] = 'cafevdb/lib/contacts.php';
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
OC::$CLASSPATH['CAFEVDB\DetailedInstrumentation'] = 'cafevdb/lib/detailed-instrumentation.php';
OC::$CLASSPATH['CAFEVDB\ProjectInstruments'] = 'cafevdb/lib/project-instruments.php';
OC::$CLASSPATH['CAFEVDB\EmailRecipientsFilter'] = 'cafevdb/lib/emailrecipientsfilter.php';
OC::$CLASSPATH['CAFEVDB\EmailComposer'] = 'cafevdb/lib/emailcomposer.php';
OC::$CLASSPATH['CAFEVDB\InstrumentInsurance'] = 'cafevdb/lib/instrument-insurance.php';
OC::$CLASSPATH['CAFEVDB\InsuranceRates'] = 'cafevdb/lib/insurance-rates.php';
OC::$CLASSPATH['CAFEVDB\InsuranceBrokers'] = 'cafevdb/lib/insurance-brokers.php';
OC::$CLASSPATH['CAFEVDB\PHPExcel\ValueBinder'] = 'cafevdb/lib/php-excel-functions.php';
OC::$CLASSPATH['CAFEVDB\Finance'] = 'cafevdb/lib/finance.php';
OC::$CLASSPATH['CAFEVDB\FuzzyInput'] = 'cafevdb/lib/functions.php';
OC::$CLASSPATH['CAFEVDB\SepaDebitMandates'] = 'cafevdb/lib/sepa-debit-mandates.php';
OC::$CLASSPATH['CAFEVDB\ProgressStatus'] = 'cafevdb/lib/progress-status.php';
OC::$CLASSPATH['CAFEVDB\PageLoader'] = 'cafevdb/lib/page-loader.php';
OC::$CLASSPATH['CAFEVDB\Session'] = 'cafevdb/lib/session.php';
OC::$CLASSPATH['CAFEVDB\PDFLetter'] = 'cafevdb/lib/pdfletter.php';
OC::$CLASSPATH['CAFEVDB\PhoneNumbers'] = 'cafevdb/lib/phonenumbers.php';

OC::$CLASSPATH['DWEMBED\App'] = 'dokuwikiembed/lib/dokuwikiembed.php';
OC::$CLASSPATH['DWEMBED\L'] = 'dokuwikiembed/lib/util.php';
OC::$CLASSPATH['DWEMBED\Util'] = 'dokuwikiembed/lib/util.php';

OC::$CLASSPATH['OC_RoundCube_App'] = 'roundcube/lib/RoundCubeApp.php'; // <<<=== why

/* 3rd party classes */
OC::$CLASSPATH['phpMyEdit'] = 'cafevdb/3rdparty/phpMyEdit/phpMyEdit.class.php';
OC::$CLASSPATH['html2text'] = 'cafevdb/3rdparty/PHPMailer/extras/class.html2text.php';
OC::$CLASSPATH['IBAN'] = 'cafevdb/3rdparty/php-iban/oophp-iban.php';
OC::$CLASSPATH['malkusch\bav\BAV'] = 'cafevdb/3rdparty/bav/autoloader/autoloader.php';
OC::$CLASSPATH['libphonenumber\PhoneNumberUtil'] = 'cafevdb/3rdparty/libphonenumber/autoloader/autoloader.php';
require_once 'cafevdb/3rdparty/PHPMailer/PHPMailerAutoload.php';
OC::$CLASSPATH['PEAR'] = '3rdparty/PEAR.php';
OC::$CLASSPATH['Mail_RFC822'] = 'cafevdb/3rdparty/pear/php/Mail/RFC822.php';
OC::$CLASSPATH['Net_IMAP'] = 'cafevdb/3rdparty/pear/php/Net/IMAP.php';
OC::$CLASSPATH['TCPDF'] = 'cafevdb/3rdparty/tcpdf/tcpdf.php';

/* Script etc. used by everyone */
OC_App::registerAdmin( 'cafevdb', 'admin-settings' );
//OCP\App::registerPersonal( 'cafevdb', 'settings' );

// use exceptions for error reporting by default.
CAFEVDB\Error::exceptions(true);

OCP\Util::connectHook('OC_User','post_login','CAFEVDB\Config','loginListener');
OCP\Util::connectHook('OC_User','post_setPassword','CAFEVDB\Config','changePasswordListener');
OCP\Util::connectHook('OC_User', 'logout', 'CAFEVDB\Config', 'logoutListener');

OCP\Util::connectHook('OC_Calendar','addEvent','CAFEVDB\Events','newEventListener');
OCP\Util::connectHook('OC_Calendar','editEvent','CAFEVDB\Events','changeEventListener');
OCP\Util::connectHook('OC_Calendar','deleteEvent','CAFEVDB\Events','killEventListener');
OCP\Util::connectHook('OC_Calendar','moveEvent','CAFEVDB\Events','moveEventListener');

OCP\Util::connectHook('OC_Calendar', 'deleteCalendar', 'CAFEVDB\Events', 'killCalendarListener');
OCP\Util::connectHook('OC_Calendar', 'editCalendar', 'CAFEVDB\Events', 'editCalendarListener');

/* Hurray! There is a config hook! */
//OCP\Util::connectHook('\OCP\Config', 'js', 'CAFEVDB\Config', 'jsLoadHook');

OCP\App::addNavigationEntry( array(
	'id' => 'cafevdb',
	'order' => 74,
	'href' => OCP\Util::linkTo( 'cafevdb', 'index.php' ),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo-greyf-icon.svg' ),
	'name' => 'Camerata DB'
));
