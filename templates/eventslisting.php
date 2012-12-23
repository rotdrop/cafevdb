<?php use CAFEVDB\L; ?>
<table id="table" class="nostyle listing">
<?php
$prjId   = $_['ProjectId'];
$prjName = $_['ProjectName'];
$class   = $_['CSSClass'];
$evtButtons = array('Edit' => array('tag' => 'edit',
                                    'title' => CAFEVDB\Config::toolTips('projectevents-edit')),
                    'Delete' => array('tag' => 'delete',
                                      'title' =>CAFEVDB\Config::toolTips('projectevents-delete')),
                    'Detach' => array('tag' => 'detach',
                                      'title' =>CAFEVDB\Config::toolTips('projectevents-detach'))
);

$locale = $_['Locale'];
$n = 0;
foreach ($_['Events'] as $event) {
  $evtId  = $event['calEventId'];
  $object = $event['object'];
  $calId = $object['calendarid'];
  $brief = $object['summary'];

  $start = $object['startdate'];
  $startdate = CAFEVDB\Events::strftime("%x", $start->getTimestamp(), $locale);
  $starttime = CAFEVDB\Events::strftime("%X", $start->getTimestamp(), $locale);
  $end   = $object['enddate'];
  $enddate = CAFEVDB\Events::strftime("%x", $end->getTimestamp(), $locale);
  $endtime = CAFEVDB\Events::strftime("%X", $end->getTimestamp(), $locale);

  echo <<<__EOT__
    <tr class="$class">
      <td class="eventbuttons-$n">
      <input type="hidden" id="calendarid-$evtId" name="CalendarId[$evtId]" value="$calId"/>
__EOT__;
  foreach ($evtButtons as $btn => $values) {
    $tag   = $values['tag'];
    $title = L::t($values['title']);
    $name  = $tag."[$evtId]";
    echo <<<__EOT__
        <input class="$tag" id="$tag-$evtId" type="button" name="$tag" title="$title" value="$evtId" />
__EOT__;
  }
  $title = L::t(CAFEVDB\Config::toolTips('projectevents-selectevent'));
  $checked = isset($_['Selected'][$evtId]) ? 'checked' : '';
  echo <<<__EOT__
      </td>
      <td class="eventemail-$n">
        <label class="email-check" for="email-check-$evtId"  title="$title" >
        <input class="email-check" title="" id="email-check-$evtId" type="checkbox" name="email-check[]" value="$evtId" $checked />
        <div class="email-check" /></label>
      </td>
      <td class="eventdata-$n-brief" id="brief-$evtId">$brief</td>
      <td class="eventdata-$n-date" id="data-$evtId">$startdate - $enddate</td>
    </tr>
__EOT__;
  $n = ($n + 1) & 1;
}
?>
</table>
