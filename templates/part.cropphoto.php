<?php

/**Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * Copyright (c) 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Originally copied from:
 *
 * Copyright (c) 2012 Thomas Tanghus <thomas@tanghus.net>
 * Copyright (c) 2011, 2012 Bart Visscher <bartv@thisnet.nl>
 * Copyright (c) 2011 Jakob Sack mail@jakobsack.de
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

$recordId = $_['recordId'];
$tmpkey = $_['tmpkey'];
$requesttoken = $_['requesttoken'];
?>
<script type="text/javascript">
	jQuery(function($) {
		$('#cropbox').Jcrop({
			onChange:	showCoords,
			onSelect:	showCoords,
			onRelease:	clearCoords,
			maxSize:	[399, 399],
			bgColor:	'black',
			bgOpacity:	.4,
			boxWidth: 	400,
			boxHeight:	400,
			setSelect:	[ 100, 130, 50, 50 ]//,
			//aspectRatio: 0.8
		});
	});
	// Simple event handler, called from onChange and onSelect
	// event handlers, as per the Jcrop invocation above
	function showCoords(c) {
		$('#x1').val(c.x);
		$('#y1').val(c.y);
		$('#x2').val(c.x2);
		$('#y2').val(c.y2);
		$('#w').val(c.w);
		$('#h').val(c.h);
	};

	function clearCoords() {
		$('#coords input').val('');
	};
	/*
	$('#coords').submit(function() {
		alert('Handler for .submit() called.');
		return true;
	});*/
</script>
<?php if(OC_Cache::hasKey($tmpkey)) { ?>
<img id="cropbox" src="<?php echo OCP\Util::linkToAbsolute('cafevdb', 'tmpphoto.php'); ?>?tmpkey=<?php echo $tmpkey; ?>" />
<form id="cropform"
	class="coords"
	method="post"
	enctype="multipart/form-data"
	target="crop_target"
	action="<?php echo OCP\Util::linkToAbsolute('cafevdb', 'ajax/memberphoto/savecrop.php'); ?>">

	<input type="hidden" id="id" name="RecordId" value="<?php echo $recordId; ?>" />
	<input type="hidden" name="requesttoken" value="<?php echo $requesttoken; ?>">
	<input type="hidden" id="tmpkey" name="tmpkey" value="<?php echo $tmpkey; ?>" />
	<fieldset id="coords">
	<input type="hidden" id="x1" name="x1" value="" />
	<input type="hidden" id="y1" name="y1" value="" />
	<input type="hidden" id="x2" name="x2" value="" />
	<input type="hidden" id="y2" name="y2" value="" />
	<input type="hidden" id="w" name="w" value="" />
	<input type="hidden" id="h" name="h" value="" />
	</fieldset>
	<iframe name="crop_target" id='crop_target' src=""></iframe>
</form>
<?php
} else {
	echo $l->t('The temporary image has been removed from cache.');
}

?>
