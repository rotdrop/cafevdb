<?php
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

    $datestring = Events::briefEventDate($object, $zone, $locale);

    echo <<<__EOT__
    <tr class="$class">
      <td class="eventbuttons-$n">
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
      <td class="eventemail-$n">
        <label class="email-check" for="email-check-$evtId"  title="$title" >
        <input class="email-check" title="" id="email-check-$evtId" type="checkbox" name="EventSelect[]" value="$evtId" $checked />
        <div class="email-check" /></label>
      </td>
      <td class="eventdata-$n-brief" id="brief-$evtId">$brief</td>
      <td class="eventdata-$n-date" id="data-$evtId">$datestring</td>
    </tr>
__EOT__;
    $n = ($n + 1) & 1;
  }
}
?>
</table>
