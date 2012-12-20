<?php

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}
OCP\JSON::checkAppEnabled('cafevdb');

$tmpl = new OCP\Template('cafevdb', 'events');

return $tmpl->printPage();

?>
