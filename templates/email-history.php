<?php
use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\EmailHistory;

$project = Util::cgiValue('Project');
$projectId = Util::cgiValue('ProjectId',-1);
$css_pfx = EmailHistory::CSS_PREFIX;

$nav = '';
if ($projectId >= 0) {
  $nav .= Navigation::button('projectlabel', $project, $projectId);
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('email', $project, $projectId);
  $nav .= Navigation::button('brief', $project, $projectId);
  $nav .= Navigation::button('projectinstruments', $project, $projectId);
  $nav .= Navigation::button('instruments', $project, $projectId); 
} else {
  $nav .= Navigation::button('projects');
  $nav .= Navigation::button('email');
  $nav .= Navigation::button('all');
  $nav .= Navigation::button('projectinstruments');
  $nav .= Navigation::button('instruments');
}

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => EmailHistory::headerText()));

// Issue the main part. The method will echo itself
EmailHistory::display();

// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>
