<?php
	/* $Id: hook_deleteaccount.inc.php 19183 2005-09-20 10:11:38Z lkneschke $ */

	if((int)$GLOBALS['hook_values']['account_id'] > 0)
	{
		$bokb = CreateObject('phpbrain.bokb');

		if((int)$_POST['new_owner'] == 0)
		{
			$bokb->delete_owner_articles((int)$GLOBALS['hook_values']['account_id']);
		}
		else
		{
			$bokb->change_articles_owner((int)$GLOBALS['hook_values']['account_id'],(int)$_POST['new_owner']);
		}
	}
?>
