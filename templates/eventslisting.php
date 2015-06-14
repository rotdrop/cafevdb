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

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
?>
<table id="table" class="nostyle listing">
<?php
$prjId   = $_['ProjectId'];
$prjName = $_['ProjectName'];
$class   = $_['CSSClass'];
$evtButtons = array('Edit' => array('tag' => 'edit',
                                    'title' => Config::toolTips('projectevents-edit')),
                    'Delete' => array('tag' => 'delete',
                                      'title' =>Config::toolTips('projectevents-delete')),
                    'Detach' => array('tag' => 'detach',
                                      'title' =>Config::toolTips('projectevents-detach'))
);

$locale = $_['locale'];
$zone   = $_['timezone'];
$n = 0;
foreach ($_['EventMatrix'] as $key => $eventGroup) {
  $dpyName = $eventGroup['name'];
  $events   = $eventGroup['events'];
  if (!empty($events)) {
    echo "<tr><th colspan=\"4\">$dpyName</th></tr>";
  } else if ($key >= 0) {
    $noEvents = empty($events) ? L::t('no events') : '';
    echo "<tr><th colspan=\"4\">$dpyName ($noEvents)</th></tr>";
  }
  foreach ($eventGroup['events'] as $event) {
    $evtId  = $event['EventId'];
    $calId  = $event['CalendarId'];
    $object = $event['object'];
    $brief  = htmlspecialchars(stripslashes($object['summary']));
    $description = htmlspecialchars(nl2br("\n".stripslashes(Events::getDescription($object))));

    $datestring = Events::briefEventDate($object, $zone, $locale);
    $longDate = Events::longEventDate($object, $zone, $locale);

    $description = $longDate.'<br/>'.$brief.$description;

    echo <<<__EOT__
    <tr class="$class step-$n">
      <td class="eventbuttons">
      <input type="hidden" id="calendarid-$evtId" name="CalendarId[$evtId]" value="$calId"/>
__EOT__;
    foreach ($evtButtons as $btn => $values) {
      $tag   = $values['tag'];
      $title = $values['title'];
      $name  = $tag."[$evtId]";
      echo <<<__EOT__
        <input class="$tag" id="$tag-$evtId" type="button" name="$tag" title="$title" value="$evtId" />
__EOT__;
    }
    $title = Config::toolTips('projectevents-selectevent');
    $checked = isset($_['Selected'][$evtId]) ? 'checked' : '';
    echo <<<__EOT__
      </td>
      <td class="eventemail">
        <label class="email-check" for="email-check-$evtId"  title="$title" >
        <input class="email-check" title="" id="email-check-$evtId" type="checkbox" name="EventSelect[]" value="$evtId" $checked />
        <div class="email-check" /></label>
      </td>
      <td class="eventdata brief tipsy-s tipsy-wide" id="brief-$evtId" title="$description">$brief</td>
      <td class="eventdata date tipsy-se tipsy-wide" id="data-$evtId" title="$description">$datestring</td>
    </tr>
__EOT__;
    $n = ($n + 1) & 1;
  }
}
?>
</table>
