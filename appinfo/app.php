<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

if ((@include_once __DIR__ . '/../vendor/autoload.php')===false) {
        throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

$dispatcher = \OC::$server->getEventDispatcher();

$dispatcher->addListener(
    OCP\AppFramework\Http\TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN,
    function() {
        OCA\CAFEVDB\Common\Util::addExternalScript("https://maps.google.com/maps/api/js?sensor=false");
    }
);

\OCP\Util::addStyle('cafevdb', 'settings');

if (false) {

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

//\OCP\Backgroundjob::addRegularTask('CAFEVDB\Cron', 'run');
OCP\Util::addScript('cafevdb', 'backgroundjobs');

// Hook our address-book backend into the contacts app. Does this work???
CAFEVDB\AddressbookBackend::register();

OCP\App::addNavigationEntry( array(
	'id' => 'cafevdb',
	'order' => 74,
        'href' => OCP\Util::linkToRoute('cafevdb_root'),
	'icon' => OCP\Util::imagePath( 'cafevdb', 'logo-greyf-icon.svg' ),
	'name' => 'Camerata DB'
));

} // if false
