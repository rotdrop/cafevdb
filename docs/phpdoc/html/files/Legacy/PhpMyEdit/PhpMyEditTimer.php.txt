<?php
/*
 * phpMyEdit - instant MySQL table editor and code generator
 *
 * phpMyEdit.class.php - main table editor class definition file
 * ____________________________________________________________
 *
 * Copyright (c) 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * C opyright (c) 1999-2002 John McCreesh <jpmcc@users.sourceforge.net>
 * C opyright (c) 2001-2002 Jim Kraai <jkraai@users.sourceforge.net>
 * Versions 5.0 and higher developed by Ondrej Jombik <nepto@php.net>
 * Copyright (c) 2002-2006 Platon Group, http://platon.sk/
 * All rights reserved.
 *
 * See README file for more information about this software.
 * See COPYING file for license information.
 *
 * Download the latest version from
 * http://platon.sk/projects/phpMyEdit/
 */

/* $Platon: phpMyEdit/phpMyEdit.class.php,v 1.215 2011-01-09 18:42:41 nepto Exp $ */

/*	This is a generic table editing program. The table and fields to be
	edited are defined in the calling program.

	This program works in three passes.
	* Pass 1 (the last part of the program) displas the selected SQL
	table in a scrolling table on the screen. Radio buttons are used to
	select a record for editing or deletion. If the user chooses Add,
	Change, Copy, View or Delete buttons.
	* Pass 2 starts, displaying the selected record. If the user chooses
	the Save button from this screen.
	* Pass 3 processes the update and the display returns to the
	original table view (Pass 1).
*/

namespace OCA\CAFEVDB\Legacy\PhpMyEdit;

class PhpMyEditTimer
{
	public $startTime;
	public $started;

	function __construct($start = true)
	{
		$this->started = false;
		if ($start) {
			$this->start();
		}
	}

	function start()
	{
		$this->startTime =  hrtime(true);
		$this->started	 = true;
	}

	function end($iterations = 1)
	{
		// get the time, check whether the timer was started later
		$endTime = hrtime(true);
		if ($this->started) {
			$dur = $endTime - $this->startTime;
			$avg = $dur / $iterations / 1e6;
			return $avg;
		} else {
			return 'phpMyEdit_timer ERROR: timer not started';
		}
	}
}
