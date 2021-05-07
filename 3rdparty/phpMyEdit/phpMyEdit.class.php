<?php

/*
 * phpMyEdit - instant MySQL table editor and code generator
 *
 * phpMyEdit.class.php - main table editor class definition file
 * ____________________________________________________________
 *
 * Copyright (c) 2011-2016, 2020-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class phpMyEdit_timer /* {{{ */
{
	var $startTime;
	var $started;

	function __construct($start = true)
	{
		$this->started = false;
		if ($start) {
			$this->start();
		}
	}

	function start()
	{
		$startMtime		 = explode(' ', microtime());
		$this->startTime = (double) $startMtime[0] + (double) $startMtime[1];
		$this->started	 = true;
	}

	function end($iterations = 1)
	{
		// get the time, check whether the timer was started later
		$endMtime = explode(' ', microtime());
		if ($this->started) {
			$endTime = (double)($endMtime[0])+(double)($endMtime[1]);
			$dur = $endTime - $this->startTime;
			$avg = 1000 * $dur / $iterations;
			$avg = round(1000 * $avg) / 1000;
			return $avg;
		} else {
			return 'phpMyEdit_timer ERROR: timer not started';
		}
	}
} /* }}} */

class phpMyEdit
{
	const COLUMN_ALIAS = 'PMEcolumn';
	const TABLE_ALIAS = 'PMEalias';
	const JOIN_ALIAS = 'PMEjoin';
	const MAIN_ALIAS = 'PMEtable0';
	const SQL_ENCODE = 'TO_BASE64';
	const SQL_DECODE = 'FROM_BASE64';

	const COOKED = 0x00;
	const OMIT_DESC = 0x01;
	const OMIT_SQL = 0x02;
	const VANILLA = self::OMIT_DESC|self::OMIT_SQL;

	const SQL_QUERY_SELECT = 'select';
	const SQL_QUERY_INSERT = 'insert';
	const SQL_QUERY_UPDATE = 'update';
	const SQL_QUERY_DELETE = 'delete';

	const QPARTS_TYPE = 'type';
	const QPARTS_PROCEDURE = 'procedure';
	const QPARTS_TABLE = 'table';
	const QPARTS_FROM = 'from';
	const QPARTS_SELECT = 'select';
	const QPARTS_WHERE = 'where';
	const QPARTS_HAVING = 'having';
	const QPARTS_GROUPBY = 'groupby';
	const QPARTS_ORDERBY = 'orderby';
	const QPARTS_LIMIT = 'limit';
	const QPARTS_FIELDS = 'fields';
	const QPARTS_VALUES = 'values';

	const SQL_SELECT = 'SELECT';

	const FDD_SELECT = 'select';
	const FDD_VALUES = 'values';

	const TRIGGER_BEFORE = 'before';
	const TRIGGER_AFTER = 'after';
	const TRIGGER_PRE = 'pre';
	const TRIGGER_CANCEL = 'cancel';

	const OPERATION_FILTER = 'filter';

	const TRIVIAL_ENCODE = '%s';
	const TRIVIAL_DESCRIPION = '$table.$column';

	// Class variables {{{

	// Database handling
	var $hn;		// hostname
	var $un;		// user name
	var $pw;		// password
	var $tb;		// table
	var $db;		// database
	var $dbp;		// database with point and delimiters
	var $dbh;		// database handle
	var $close_dbh;	// if database handle should be closed
	var $error_list;// list of latest sql errors

	// Record manipulation
	var $key;		// name of field which is the unique key
	var $key_num;	// array of field-numbers which are the unique keys
	var $key_type;	// type of key field (int/real/string/date etc.)
	var $key_delim;	// character used for key value quoting
	var $rec;		// number of record selected for editing
	var $groupby;   // array of fields for groupby clause, or empty
	var $groupby_num; // field numbers of groupby fields
	var $groupby_rec; // values of the group-by fields
	var $groupby_where; // whether to use groupby field in single record retrieval
	var $mrecs;     // array of custom-multi records selected
	var $inc;		// number of records to display
	var $fm;		// first record to display
	var $fl;		// is the filter row displayed (boolean)
	var $fds;		// $k => sql field names
	var $fdn;		// sql field names => $k
	var $num_fds;	// number of fields
	var $options;	// options for users: ACDFVPI
	var $fdd;		// field definitions
	var $qfn;		// value of all filters used during the last pass
	var $sfn;		// sort field number (- = descending sort order)
	var $dfltsfn;	// default sort field number
	var $cur_tab;	// current selected tab
	var $page_type;	// current page type

	// Operation
	var $navop;		// navigation buttons/operations
	var $sw;		// filter display/hide/clear button
	var $operation;	// operation to do: Add, Change, Delete
	var $saveadd;
	var $applyadd;
	var $moreadd;
	var $canceladd;
	var $savechange;
	var $morechange;
	var $cancelchange;
	var $reloadchange; // like cancelchange, but stay in change mode.
	var $savecopy;
	var $applycopy;
	var $cancelcopy;
	var $savedelete;
	var $canceldelete;
	var $cancelview;
	var $reloadview;

	// Additional features
	var $labels;		// multilingual labels
	var $tooltips;			// tooltips
	var $translations;		// file with button translations
	var $cgi;		// CGI variable features array
	var $js;		// JS configuration array
	var $dhtml;		// DHTML configuration array
	var $url;		// URL array
	var $message;		// informational message to print
	var $notify;		// change notification e-mail adresses
	var $logtable;		// name of optional logtable
	var $miscphp;		// callback function for multi-purpose custom misc button
	var $misccss;       // major css class name for misc buttons
	var $misccss2;      // minor css class name for misc buttons
	var $navigation;	// navigation style
	var $buttons;
	var $tabs;			// TAB names
	var $tabs_help;     // Tooltips, if any
	var $tabs_by_id;    // TAB indices by Id
	var $tabs_by_name;  // TAB indices by Id
	var $timer = null;	// phpMyEdit_timer object
	var $sd; var $ed;	// sql start and end delimiters '`' in case of MySQL

	// Predefined variables
	var $comp_ops  = array('<'=>'<','<='=>'<=','='=>'=','>='=>'>=','>'=>'>');
	var $sql_aggrs = array(
		'sum'	=> 'Total',
		'avg'	=> 'Average',
		'min'	=> 'Minimum',
		'max'	=> 'Maximum',
		'count' => 'Count');
	var $page_types = array(
		'L' => 'list',
		'F' => 'filter',
		'A' => 'add',
		'V' => 'view',
		'C' => 'change',
		'P' => 'copy',
		'D' => 'delete'
		);
	var $default_buttons = array(
		'L' => array('<<','<','add','view','change','copy','delete','>','>>',
					 'goto','rows_per_page','reload'),
		'F' => array('<<','<','add','view','change','copy','delete','>','>>',
					 'goto','rows_per_page','reload'),
		'A' => array('save','apply','more','cancel'),
		'C' => array('save','more','cancel','reload'),
		'P' => array('save','apply','cancel'),
		'D' => array('save','cancel'),
		'V' => array('change','copy','delete','cancel','reload')
		);
	var $default_multi_buttons = array(
		'L' => array('<<','<','misc','add','view','change','copy','delete','>','>>',
					 'goto','rows_per_page','reload'),
		'F' => array('<<','<','misc','add','view','change','copy','delete','>','>>',
					 'goto','rows_per_page','reload'),
		'A' => array('save','apply','more','cancel'),
		'C' => array('save','more','cancel','reload'),
		'P' => array('save','apply','cancel'),
		'D' => array('save','cancel'),
		'V' => array('change','copy','delete','cancel','reload')
		);
	var $default_buttons_no_B = array(
		'L' => array('<<','<','add','>','>>',
					 'goto','rows_per_page','reload'),
		'F' => array('<<','<','add','>','>>',
					 'goto','rows_per_page','reload'),
		'A' => array('save','apply','more','cancel'),
		'C' => array('save','more','cancel','reload'),
		'P' => array('save','apply','cancel'),
		'D' => array('save','cancel'),
		'V' => array('change','copy','delete','cancel','reload')
		);
	var $default_multi_buttons_no_B = array(
		'L' => array('<<','<','misc','add','>','>>',
					 'goto','rows_per_page','reload'),
		'F' => array('<<','<','misc','add','>','>>',
					 'goto','rows_per_page','reload'),
		'A' => array('save','apply','more','cancel'),
		'C' => array('save','more','cancel','reload'),
		'P' => array('save','apply','cancel'),
		'D' => array('save','cancel'),
		'V' => array('change','copy','delete','cancel','reload')
		);

	// }}}

	/*
	 * column specific functions
	 */

	function col_has_sql($k)	{ return isset($this->fdd[$k]['sql']); }
	function col_has_sqlw($k)	{ return isset($this->fdd[$k]['sqlw']) && !$this->virtual($k); }
	function col_needs_having($k) { return @$this->fdd[$k]['filter'] == 'having'; }
	function col_has_join($k) { return !empty($this->fdd[$k][self::FDD_VALUES]['join']); }
	function col_has_description($k) { return !empty($this->fdd[$k][self::FDD_VALUES]['description']); }
	function col_has_values($k) {
		return (isset($this->fdd[$k][self::FDD_VALUES]) && !empty($this->fdd[$k][self::FDD_VALUES]['description']))
			|| isset($this->fdd[$k]['values2']);
	}
	function col_has_php($k)	{ return isset($this->fdd[$k]['php']); }
	function col_has_URL($k)	{ return isset($this->fdd[$k]['URL'])
			|| isset($this->fdd[$k]['URLprefix']) || isset($this->fdd[$k]['URLpostfix']); }
	function col_has_multiple($k)
	{ return $this->col_has_multiple_select($k) || $this->col_has_checkboxes($k); }
	function col_has_multiple_select($k)
	{ return $this->fdd[$k][self::FDD_SELECT] == 'M' /*&& (! @$this->fdd[$k][self::FDD_VALUES]['table'] || @$this->fdd[$k][self::FDD_VALUES]['queryvalues'])*/; }
	function col_has_checkboxes($k)
	{ return $this->fdd[$k][self::FDD_SELECT] == 'C' /*&& (! @$this->fdd[$k][self::FDD_VALUES]['table'] || @$this->fdd[$k][self::FDD_VALUES]['queryvalues'])*/; }
	function col_has_radio_buttons($k)
	{ return $this->fdd[$k][self::FDD_SELECT] == 'O' /*&& (! @$this->fdd[$k][self::FDD_VALUES]['table'] || @$this->fdd[$k][self::FDD_VALUES]['queryvalues'])*/; }
	function col_has_datemask($k)
	{ return isset($this->fdd[$k]['datemask']) || isset($this->fdd[$k]['strftimemask']); }

	/*
	 * functions for indicating whether navigation style is enabled
	 */

	function nav_buttons()		 { return !empty($this->navigation) && stristr($this->navigation, 'B'); }
	function nav_text_links()	 { return !empty($this->navigation) && stristr($this->navigation, 'T'); }
	function nav_graphic_links() { return !empty($this->navigation) && stristr($this->navigation, 'G'); }
	function nav_custom_multi()	 { return !empty($this->navigation) && stristr($this->navigation, 'M') && $this->misc_enabled(); }
	function nav_up()			 { return !empty($this->navigation) && stristr($this->navigation, 'U') && (!isset($this->buttons[$this->page_type]['up']) || !($this->buttons[$this->page_type]['up'] === false)); }
	function nav_down()			 { return !empty($this->navigation) && stristr($this->navigation, 'D') && (!isset($this->buttons[$this->page_type]['down']) || !($this->buttons[$this->page_type]['down'] === false)); }

	/*
	 * Handle logging
	 */
	protected function logQuery($operation, $oldvals, $changed, $newvals)
	{
		if (empty($changed)) {
			return;
		}
		switch($operation) {
		case self::SQL_QUERY_INSERT:
			$query = sprintf('INSERT INTO %s'
							 .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
							 .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "", "", "%s")',
							 $this->logtable,
							 addslashes($this->get_server_var('REMOTE_USER')),
							 addslashes($this->get_server_var('REMOTE_ADDR')),
							 $operation,
							 addslashes($this->tb),
							 addslashes(implode(',', $this->rec)),
							 addslashes(serialize($newvals)));
			break;
		case self::SQL_QUERY_UPDATE:
			$changeSetOld = [];
			$changeSetNew = [];
			foreach ($changed as $key) {
				$changeSetOld[$key] = $oldvals[$key];
				$changeSetNew[$key] = $newvals[$key];
			}
			$query = sprintf('INSERT INTO %s'
							 .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
							 .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s")',
							 $this->logtable,
							 addslashes($this->get_server_var('REMOTE_USER')),
							 addslashes($this->get_server_var('REMOTE_ADDR')),
							 $operation,
							 addslashes($this->tb),
							 addslashes(implode(',',$this->rec)),
							 addslashes($key),
							 addslashes(serialize($changeSetOld)),
							 addslashes(serialize($changeSetNew)));
			break;
		case self::SQL_QUERY_DELETE:
			$query = sprintf('INSERT INTO %s'
							 .' (updated, user, host, operation, tab, rowkey, col, oldval, newval)'
							 .' VALUES (NOW(), "%s", "%s", "%s", "%s", "%s", "", "%s", "")',
							 $this->logtable,
							 addslashes($this->get_server_var('REMOTE_USER')),
							 addslashes($this->get_server_var('REMOTE_ADDR')),
							 $operation,
							 addslashes($this->tb),
							 addslashes(implode(',', $this->rec)),
							 addslashes(serialize($oldvals)));
			break;
		}
		if (!empty($query)) {
			$this->myquery($query, __LINE__);
		}
	}

	/*
	 * Handle multi-column record keys
	 */
	private function key_record($key_record = null)
	{
		if (empty($key_record)) {
			$key_record = $this->rec;
		}
		if (empty($key_record) || empty($this->key)) {
			return null;
		}
		if (count($this->key) == 1) {
			return array_values($key_record)[0];
		}
		return $key_record;
	}

	private function key_record_where()
	{
		if (!empty($this->groupby_rec && $this->checkOperationOption($this->groupby_where))) {
			$keyRecord = $this->groupby_rec;
		} else {
			$keyRecord = $this->rec;
		}
		$wparts = [];
		foreach ($keyRecord as $key => $rec) {
			$delim = isset($this->key_delim[$key]) ? $this->key_delim[$key] : "'";
			// no need for fqn. ?
			$wparts[] = $this->fqn($key, self::VANILLA).' = '.$delim.$rec.$delim;
		}
		return '('.implode(' AND ', $wparts).')';
	}

	private function key_record_query_data($key_rec, $force_array = false, $sysName = 'rec')
	{
		if (empty($key_record)) {
			$key_record = $this->rec;
		}
		$count = $force_array ? -1 : count($key_rec);
		switch (count($key_rec)) {
		case 0:
			$recordQueryData = $this->cgi['prefix']['sys'].$sysName.'=""';
			break;
		case 1:
			$recordQueryData = $this->cgi['prefix']['sys'].$sysName.'='.array_values($key_rec)[0];
			break;
		default:
			$data = [];
			foreach ($key_rec as $key => $value) {
				$data[] = $this->cgi['prefix']['sys'].$sysName.'['.$key.']'.'='.$value;
			}
			$recordQueryData = implode('&', $data);
			break;
		}
		return $recordQueryData;
	}

	private function emit_misc_recs()
	{
		echo $this->htmlHiddenSys('mtable', $this->tb);
		switch (count($this->key)) {
			case 0:
				echo $this->htmlHiddenSys('mkey', '');
				echo $this->htmlHiddenSys('mkeytype', '');
				break;
			case 1:
				foreach ($this->key as $key => $key_type) {
					echo $this->htmlHiddenSys('mkey', $key);
					echo $this->htmlHiddenSys('mkeytype', $key_type);
				}
				break;
			default:
				foreach ($this->key as $key => $key_type) {
					echo $this->htmlHiddenSys('mkey['.$key.']', $key_type);
				}
				break;
		}
		foreach ($this->mrecs as $mrec) {
			echo $this->htmlHiddenSys('mrecs[]', $mrec);
		}
	}

	/*
	 * helper functions.
	 */
	static function is_flat($array)
	{
		if (is_scalar($array)) {
			return true;
		}
		if (!is_array($array)) {
			return false;
		}
		foreach ($array as $key => $value) {
			if (!is_scalar($value)) {
				return false;
			}
		}
		return true;
	}

	function dbhValid()
	{
		return $this->dbh instanceof mysqli;
	}

	function resultValid(&$res)
	{
		return $res instanceof mysqli_result;
	}

	/*
	 * functions for indicating whether operations are enabled
	 */
	function label_cmp($a, $b) {
		return ($a == $b ||
				(isset($this->labels[$b]) && $a == $this->labels[$b]) ||
				(isset($this->labels[$a]) && $this->labels[$a] == $b));
	}

	function default_sort() { return !isset($this->sfn) || count($this->sfn) == 0; }

	function listall()		  { return $this->inc < 0 || $this->inc >= $this->total_recs; }
	function add_enabled()	  { return stristr($this->options, 'A'); }
	function change_enabled() { return stristr($this->options, 'C'); }
	function delete_enabled() { return stristr($this->options, 'D'); }
	function misc_enabled()	  { return (stristr($this->options, 'M') &&
										isset($this->miscphp) &&
										$this->miscphp != '') ; }
	function filter_enabled() { return stristr($this->options, 'F') && $this->list_operation(); }
	function view_enabled()	  { return stristr($this->options, 'V'); }
	function copy_enabled()	  { return stristr($this->options, 'P') && $this->add_enabled(); }
	function tabs_enabled()	  { return $this->display['tabs'] && count($this->tabs) > 0; }
	function hidden($k)		  { return stristr(@$this->fdd[$k]['input'],'H'); }
	function skipped($k)	  { return stristr(@$this->fdd[$k]['input'],'S') /*|| isset($this->fdd[$k][self::FDD_VALUES]['join']['reference'])*/; }
	function password($k)	  { return stristr(@$this->fdd[$k]['input'],'W'); }
	function readonly($k)	  { return stristr(@$this->fdd[$k]['input'],'R'); }
	function mandatory($k)	  { return stristr(@$this->fdd[$k]['input'],'M'); }
	function annotation($k)	  { return stristr(@$this->fdd[$k]['input'],'A'); }
	function disabled($k)	  { return stristr(@$this->fdd[$k]['input'],'D') || $this->virtual($k);		}
	function virtual($k)	  { return stristr(@$this->fdd[$k]['input'],'V') && $this->col_has_sql($k); }

	function add_operation()	{ return $this->label_cmp($this->operation, 'Add')	  && $this->add_enabled();	  }
	function change_operation() { return $this->label_cmp($this->operation, 'Change') && $this->change_enabled(); }
	function copy_operation()	{ return $this->label_cmp($this->operation, 'Copy')	  && $this->copy_enabled();	  }
	function delete_operation() { return $this->label_cmp($this->operation, 'Delete') && $this->delete_enabled(); }
	function misc_operation()	{ return $this->label_cmp($this->operation, 'Misc')	  && $this->misc_enabled();	 }
	function view_operation()	{ return $this->label_cmp($this->operation, 'View')	  && $this->view_enabled();	  }
	function filter_operation() { return $this->fl && $this->filter_enabled(); }
	function list_operation()	{ /* covers also filtering page */ return ! $this->change_operation()
			&& ! $this->add_operation()	   && ! $this->copy_operation()
			&& ! $this->delete_operation() && ! $this->view_operation(); }
	function next_operation()	{ return ($this->label_cmp($this->navop, 'Next')) || ($this->navop == '>'); }
	function prev_operation()	{ return ($this->label_cmp($this->navop, 'Prev')) || ($this->navop == '<'); }
	function first_operation()	{ return ($this->label_cmp($this->navop, 'First')) || ($this->navop == '<<'); }
	function last_operation()	{ return ($this->label_cmp($this->navop, 'Last')) || ($this->navop == '>>'); }
	function clear_operation()	{ return $this->label_cmp($this->sw, 'Clear');	}

	function add_canceled()	   { return $this->label_cmp($this->canceladd	, 'Cancel'); }
	function view_canceled()   { return $this->label_cmp($this->cancelview	, 'Cancel'); }
	function change_canceled() { return $this->label_cmp($this->cancelchange, 'Cancel'); }
	function change_reloaded() { return $this->label_cmp($this->reloadchange, 'Reload'); }
	function copy_canceled()   { return $this->label_cmp($this->cancelcopy	, 'Cancel'); }
	function delete_canceled() { return $this->label_cmp($this->canceldelete, 'Cancel'); }

	function view_nav_displayed() { return stristr($this->display['navigation'], 'V'); }
	function change_nav_displayed() { return stristr($this->display['navigation'], 'C'); }
	function copy_nav_displayed() { return stristr($this->display['navigation'], 'P'); }
	function delete_nav_displayed() { return stristr($this->display['navigation'], 'D'); }


	function view_reloaded()   { return $this->label_cmp($this->reloadview	, 'Reload'); }

	/**Return a normalized english name ... */
	public function operationName()
	{
		foreach(['add', 'change', 'copy', 'delete', 'misc', 'view', 'list'] as $op) {
			$method = $op.'_operation';
			if ($this->$method()) {
				return $op;
			}
		}
		return '';
	}

	/**Wrapper around core htmlspecialchars; avoid double encoding,
	 * standard options.
	 */
	public function enc($string, $double_encode = false)
	{
		return htmlspecialchars($string, ENT_COMPAT|ENT_HTML401, 'UTF-8', $double_encode);
	}

	function disabledTag($k)
	{
		if ($this->readonly($k)) {
			return $this->display['readonly'];
		} else if ($this->disabled($k)) {
			return $this->display['disabled'];
		} else {
			return false;
		}
	}

	function is_values2($k, $val = 'X') /* {{{ */
	{
		return $val === null ||
			(isset($this->fdd[$k]['values2']) && !isset($this->fdd[$k][self::FDD_VALUES]['table']));
	} /* }}} */

	function processed($k) /* {{{ */
	{
		if ($this->virtual($k)) {
			return false;
		}
		$options = @$this->fdd[$k]['options'];
		if (! isset($options)) {
			return true;
		}
		return
			($this->label_cmp($this->saveadd   , 'Save')  && stristr($options, 'A')) ||
			($this->label_cmp($this->moreadd   , 'More')  && stristr($options, 'A')) ||
			($this->label_cmp($this->applyadd  , 'Apply') && stristr($options, 'A')) ||
			($this->label_cmp($this->savechange, 'Save')  && stristr($options, 'C')) ||
			($this->label_cmp($this->morechange, 'Apply') && stristr($options, 'C')) ||
			($this->label_cmp($this->savecopy  , 'Save')  && stristr($options, 'P')) ||
			($this->label_cmp($this->applycopy , 'Apply') && stristr($options, 'P')) ||
			($this->label_cmp($this->savedelete, 'Save')  && stristr($options, 'D'));
	} /* }}} */

	function checkOperationOption($options)
	{
		return
			($this->add_operation()	   && stristr($options, 'A')) ||
			($this->view_operation()   && stristr($options, 'V')) ||
			($this->change_operation() && stristr($options, 'C')) ||
			($this->copy_operation()   && stristr($options, 'P')) ||
			($this->delete_operation() && stristr($options, 'D')) ||
			($this->misc_operation()   && stristr($options, 'M')) ||
			($this->filter_operation() && stristr($options, 'F')) ||
			($this->list_operation()   && stristr($options, 'L'));
	}

	function displayed($k) /* {{{ */
	{
		if (is_numeric($k)) {
			$k = $this->fds[$k];
		}
		$options = @$this->fdd[$k]['options'];
		if (! isset($options)) {
			return true;
		}
		return $this->checkOperationOption($options);
	} /* }}} */

	function filtered($k) /* {{{ */
	{
		if (!$this->filter_enabled()) {
			return false;
		}
		if (is_numeric($k)) {
			$k = $this->fds[$k];
		}
		$fdd = $this->fdd[$k];
		if (!empty($fdd['encryption'])
			&& !$this->col_has_values($k)
			&& stripos('MCOD', $fdd[self::FDD_SELECT]) === false) {
			return false;
		}
		$options = @$fdd['options'];
		if (! isset($options)) {
			return true;
		}
		return stristr($options, 'F') !== false;
	} /* }}} */

	function debug_var($name, $val) /* {{{ */
	{
		if (is_array($val) || is_object($val)) {
			echo "<pre>$name\n";
			ob_start();
			//print_r($val);
			var_dump($val);
			$content = ob_get_contents();
			ob_end_clean();
			echo htmlspecialchars($content);
			echo "</pre>\n";
		} else {
			echo 'debug_var()::<i>',htmlspecialchars($name),'</i>';
			echo '::<b>',htmlspecialchars($val),'</b>::',"<br />\n";
		}
	} /* }}} */

	/*
	 * sql functions
	 */
	function sql_connect() /* {{{ */
	{
		//echo htmlspecialchars($this->uh.$this->un.$this->pw);
		$persistent = @ini_get('mysqli.allow_persistent') ? 'p:' : '';
		$this->dbh = new mysqli($persistent.$this->hn, $this->un, $this->pw, $this->db);
		if ($this->dbh->connect_errno) {
			$this->error_list[] = array(
				'errno' => $this->dbh->connect_errno,
				'error' => $this->dbh->connect_error,
				'query' => 'connect '.$this->db.'@'.$persistent.$this->hn
				);
			$this->dbh = null;
		} else {
			// Gnah.
			$this->dbh->set_charset('utf8');
		}
	} /* }}} */


	function sql_disconnect() /* {{{ */
	{
		if ($this->dbhValid() && $this->close_dbh) {
			$this->dbh->close();
			$this->dbh = null;
		}
	} /* }}} */

	function sql_fetch(&$res, $type = 'a') /* {{{ */
	{
		if (!self::resultValid($res)) {
			return false;
		}
		$type = $type === 'n' ? MYSQLI_NUM : MYSQLI_ASSOC;
		return @$res->fetch_array($type);
	} /* }}} */

	function sql_free_result(&$res) /* {{{ */
	{
		if (!self::resultValid($res)) {
			return false;
		}
		return $res->free();
	} /* }}} */

	function sql_affected_rows() /* {{{ */
	{
		if (!$this->dbhValid()) {
			return 0;
		}
		return $this->dbh->affected_rows;
	} /* }}} */

	function sql_field_len(&$res, $field) /* {{{ */
	{
		if (!self::resultValid($res)) {
			return 0;
		}
		$meta = @$res->fetch_field_direct($field);
		return @$meta['length'];
	} /* }}} */

	function sql_insert_id() /* {{{ */
	{
		if (!$this->dbhValid()) {
			return 0;
		}
		return $this->dbh->insert_id;
	} /* }}} */

	function sql_limit($start, $more = null) /* {{{ */
	{
		if ($more < 0) return;
		if (!is_numeric($start)) return;
		$ret = ' LIMIT '.$start;
		if (is_numeric($more)) {
			$ret .= ','.$more;
		}
		$ret .= ' ';
		return $ret;
	} /* }}} */

	function sql_delimiter() /* {{{ */
	{
		$this->sd = '`'; $this->ed='`';
		return $this->sd;
	} /* }}} */


	function myquery($qry, $line = 0, $debug = 0) /* {{{ */
	{
		global $debug_query;
		if ($debug_query || $debug) {
			$line = intval($line);
			echo '<h4>MySQL query at line ',$line,'</h4>',htmlspecialchars($qry),'<hr size="1" />',"\n";
		}
		if (!$this->dbhValid()) {
			return false;
		}
		$ret = $this->dbh->query($qry, MYSQLI_STORE_RESULT); //  USE_RESULT needs free().
		if ($ret === false) {
			$this->error_list[] = array(
				'errno' => $this->dbh->errno,
				'error' => $this->dbh_error,
				'query' => $qry
				);
			echo '<h4>MySQL error ', $this->dbh->errno,'</h4>';
			echo htmlspecialchars($this->dbh->error),'<hr size="1" />',"\n";
			//error_log($qry);
		}
		return $ret;
	} /* }}} */

	/* end of sql functions */

	function make_language_labels($language) /* {{{ */
	{
		// Language might look like this:
		// de-de,en-us;q=0.8,de;q=0.5,en;q=0.3

		/* echo '<PRE>'; */
		/* print_r($language); */
		/* echo '</PRE>'; */
		$langtmpar = explode(',',$language);
		$langar = array();
		$haveprio = false;
		foreach ($langtmpar as $lang) {
			$lang = strtoupper(trim($lang));
			$lang = explode(';',$lang);
			// Convert de-de etc. into a single de.
			$langvariants = explode('-',$lang[0]);
			if (isset($langvariants[1]) &&
				$langvariants[0] == $langvariants[1]) {
				unset($langvariants[0]);
				$lang[0] = implode('-',$langvariants);
			}
			/* echo '<PRE>'; */
			/* print_r($lang); */
			/* echo '</PRE>'; */
			if (!isset($langar["$lang[0]"])) {
				if (isset($lang[1])) {
					$haveprio = true;
					$tmp = explode('=',$lang[1]);
					$langar["$lang[0]"] = $tmp[1];
				} else {
					$langar["$lang[0]"] = 1.0;
				}
			}
			/* echo '<PRE>'; */
			/* print_r($langar); */
			/* echo '</PRE>'; */
		}
		if ($haveprio) {
			arsort($langar, SORT_NUMERIC);
		}
		/* echo '<PRE>'; */
		/* print_r($langar); */
		/* echo '</PRE>'; */

		foreach ($langar as $lang => $qual) {
			// try the full language w/ variant, but prefer UTF8
			$language = strtoupper($lang).'-UTF8';

			$file = $this->dir['lang'].'PME.lang.'.$language.'.inc';
			//echo 'Lang-File: '.$file.'<BR/>';
			if (file_exists($file)) {
				break;
			}

			// Ok, then retry without UTF8 added
			$language = strtoupper($lang);

			$file = $this->dir['lang'].'PME.lang.'.$language.'.inc';
			//echo 'Lang-File: '.$file.'<BR/>';
			if (file_exists($file)) {
				break;
			}
		}
		if (!file_exists($file)) {
			// old algo, strip variants from the end of the language string
			foreach ($langar as $lang => $qual) {
				// try the full language w/ variant
				$language = strtoupper($lang);
				$file = $this->dir['lang'].'PME.lang.'.$language.'.inc';
				//echo 'Lang-File: '.$file.'<BR/>';
				if (!file_exists($file)) {
					while (($pos = strrpos($lang, '-')) !== false) {
						$lang = substr($lang, 0, $pos);
						$language = strtoupper($lang);
						$file = $this->dir['lang'].'PME.lang.'.$language.'.inc';
						if (file_exists($file)) {
							break;
						}
					}
				}
				if (file_exists($file)) {
					break;
				}
			}
		}
		if (!file_exists($file)) {
			$file = $this->dir['lang'].'PME.lang.EN.inc';
		}
		$ret = @include($file);
		if (! is_array($ret)) {
			return $ret;
		}
		$this->translations = $file;
		// $small = array(
		// 	'Search' => 'v',
		// 	'Hide'	 => '^',
		// 	'Clear'	 => 'X',
		// 	'Query'	 => htmlspecialchars('>'));
		// if ((!$this->nav_text_links() && !$this->nav_graphic_links())
		// 	|| !isset($ret['Search']) || !isset($ret['Query'])
		// 	|| !isset($ret['Hide'])	  || !isset($ret['Clear'])) {
		// 	foreach ($small as $key => $val) {
		// 		$ret[$key] = $val;
		// 	}
		// }
		return $ret;
	} /* }}} */

    /**
	 * Compute meta-data to translate data-base values etc. to display
	 * values, compute groups for select boxes, tooltips and the
	 * like. Remember the results in $this->fdd[$k]['set_values'] as
	 * the values that have been set (i.e. computed or feched from the
	 * db).
	 */
	function set_values($field_num, $strict = false) /* {{{ */
	{
		$fdd = &$this->fdd[$field_num]; // reference is important here
		if (!empty($fdd['setvalues'])) {
			return $fdd['setvalues']; // use cache.
		}

		// allow for unconditional override
		$groups = (array)$fdd['valueGroups'];
		$data = (array)$fdd['valueData'];
		$titles = (array)$fdd['valueTitles'];
		$values = (array)$fdd['values2'];

		$valuesDef = $this->values_with_defaults($field_num);

		if (!empty($valuesDef['table']) || $strict) {
			$value_group_data = $this->set_values_from_table($field_num, $strict);
			$groups += $value_group_data['groups'];
			$data += $value_group_data['data'];
			$titles += $value_group_data['titles'];
			$values += $value_group_data['values'];
		}

		$values = array_diff_key($values, [ null => true ]);

		$fdd['setvalues'] = [
			'values' => $values,
			'groups' => $groups,
			'titles' => $titles,
			'data' => $data
		];

		$fdd['values2'] = $fdd['setvalues']['values'];

		if ($this->fds[$field_num]  == 'updated') {
			throw new \Exception('blah');
		}

		// $this->logInfo('VALUES '.print_r($fdd['setvalues'], true));
		return $fdd['setvalues'];
	} /* }}} */

	function set_values_from_table($field_num, $strict = false) /* {{{ */
	{
		$fdd    = $this->fdd[$field_num];
		$db	    = $fdd[self::FDD_VALUES]['db'];
		$table  = $fdd[self::FDD_VALUES]['table'];
		if (empty($table)) {
			$table = $this->tb;
		}
		$valuesDef = $this->values_with_defaults($field_num);

		$column   = $valuesDef['column'];
		$desc     = $valuesDef['description'];
		$filters  = $valuesDef['filters'];
		$orderby  = $valuesDef['orderby'];
		$groups   = $valuesDef['groups'];
		$data     = $valuesDef['data'];
		$titles   = $valuesDef['titles'];
		$encode   = $valuesDef['encode'];
		$dbp      = isset($db) ? $this->sd.$db.$this->ed.'.' : $this->dbp;

		if ($encode == self::TRIVIAL_ENCODE) {
			unset($encode);
		}
		if ($desc == self::TRIVIAL_DESCRIPION) {
			unset($desc);
		}

		$qparts[self::QPARTS_TYPE] = self::QPARTS_SELECT;

		$subquery = stripos($table, self::SQL_SELECT) !== false;
		$table_name = self::TABLE_ALIAS.$field_num;
		if ($subquery) {
			$from_table = '('.$table.') '.$table_name;
		} else {
			$table      = $this->sd.$table.$this->ed;
			$from_table = $dbp.$table.' '.$table_name;
		}

		$subs = array(
			'main_table'  => $this->tb,
			'record_id'   => implode(',', $this->rec), // may be useful for change op.
			'table'		  => $table_name,
			'column'	  => $column,
			'description' => $desc);
		if (!empty($this->rec)) {
			foreach ($this->rec as $recKey => $recValue) {
				$subs['record_id['.$recKey.']'] = $recValue;
			}
		}

		$queryField = $table_name.'.'.$this->sd.$column.$this->ed;
		if (!empty($encode)) {
			$queryField = sprintf($encode, $queryField);
		}
		$qparts[self::QPARTS_SELECT] = 'DISTINCT '.$queryField;

		if (!empty($desc)) {

			// normalize $desc
			if (!is_array($desc)) {
				$desc = [ 'columns' => [ $desc, ] ];
			} else {
				if (empty($desc['columns'])) {
					$desc['columns'] = $desc;
				}
				if (!empty($desc['columns']) && !is_array($desc['columns'])) {
					$desc['columns'] = [ $desc['columns'], ];
				}
				if (!empty($desc['divs']) && !is_array($desc['divs'])) {
					$desc['divs'] = array_fill(0, max(0, count($desc['columns']) - 1), $desc['divs']);
				}
			}

			$qparts[self::QPARTS_SELECT] .= ',CONCAT('; // )
			$num_cols = sizeof($desc['columns']);
			if (!empty($desc['divs'][-1]) && is_array($desc['divs'])) {
				$qparts[self::QPARTS_SELECT] .= '"'.addslashes($desc['divs'][-1]).'",';
			}
			$selects = [];
			foreach ($desc['columns'] as $idx => $val) {
				if ($val) {
					if ($this->hasSubstitutions($val)) {
						$descSubs = [
							'table' => $table_name,
							'column' => $column,
						];
						$val = $this->substituteVars($val, $descSubs);
					} else {
						$val = $this->sd.$val.$this->ed;
					}
					$select = 'IFNULL(CAST('.$val.' AS CHAR),';
					$null = empty($desc['ifnull'][$idx]) ? '""' : $desc['ifnull'][$idx];
					$select .= $null.')';
					if (!empty($desc['divs'][$idx]) && is_array($desc['divs'])) {
						$select .= ',"'.addslashes($desc['divs'][$idx]).'"';
					}
					$selects[] = $select;
				}
			}
			$qparts[self::QPARTS_SELECT] .= implode(',', $selects).',';
			$qparts[self::QPARTS_SELECT][strlen($qparts[self::QPARTS_SELECT]) - 1] = ')';
			$qparts[self::QPARTS_SELECT] .= ' AS '.$this->sd.self::COLUMN_ALIAS.$field_num.$this->ed;
			$qparts[self::QPARTS_ORDERBY] = $this->sd.self::COLUMN_ALIAS.$field_num.$this->ed;
		} else if ($column) {
			$qparts[self::QPARTS_ORDERBY] = $this->sd.$column.$this->ed;
		}
		$qparts[self::QPARTS_FROM] = $from_table;
		if (!empty($filters)) {
			$qparts[self::QPARTS_WHERE] = $this->substituteVars($filters, $subs);
		}
		!empty($groups) && $groups = $this->substituteVars($groups, $subs);
		if (!empty($orderby)) {
			$qparts[self::QPARTS_ORDERBY] = $this->substituteVars($orderby, $subs);
		} else if (!empty($groups)) {
			$qparts[self::QPARTS_ORDERBY] = $groups.' ASC';
		}
		if (!empty($groups)) {
			$qparts[self::QPARTS_SELECT] .= ', '.$groups;
		}
		if (!empty($data)) {
			$data = $this->substituteVars($data, $subs);
			$qparts[self::QPARTS_SELECT] .= ', '.$data;
		}
		if (!empty($titles)) {
			$titles = $this->substituteVars($titles, $subs);
			$qparts[self::QPARTS_SELECT] .= ', '.$titles;
		}

		$res	= $this->myquery($this->get_SQL_query($qparts), __LINE__);
		$values = array();
		$grps   = array();
		$dt     = array();
		$ttls   = array();
		$idx    = $desc ? 1 : 0;
		if ($idx == 0 && is_callable($fdd['encryption']['decrypt'])) {
			$decrypt = $fdd['encryption']['decrypt'];
			$decode = function($value) use ($decrypt) {
				return call_user_func($decrypt, $value);
			};
		} else {
			$decode = function($value) { return $value; };
		}
		while ($row = $this->sql_fetch($res, 'n')) {
			$colIdx = $idx;
			$values[$row[0]] = $decode($row[$colIdx++]);
			if (!empty($groups)) {
				$grps[$row[0]] = $row[$colIdx++];
			}
			if (!empty($data)) {
				$dt[$row[0]] = $row[$colIdx++];
			}
			if (!empty($titles)) {
				$ttls[$row[0]] = $row[$colIdx++];
			}
		}

		$result = [
			'values' => $values,
			'groups' => $grps,
			'data' => $dt,
			'titles' => $ttls,
		];

		// $this->logInfo('FROM TABLE '.print_r($result, true));

		return $result;
	} /* }}} */

	protected function join_table_reference($fdd)
	{
		if (!isset($fdd[self::FDD_VALUES]['join']['reference'])) {
			return false;
		}
		$ref = $fdd[self::FDD_VALUES]['join']['reference'];
		if (preg_match('/^(PMEjoin)?([0-9]+)$/', $ref, $matches)) {
			return $matches[2];
		}
		return false;
	}

	/**
	 * Compute join table reference index
	 */
	protected function join_table_index(int $field)
	{
		$fdd = $this->fdd[$field];
		if (!isset($fdd[self::FDD_VALUES]['join'])) {
			return false;
		}
		if (!isset($fdd[self::FDD_VALUES]['join']['reference'])) {
			return $field;
		}
		return $this->join_table_reference($fdd);
	}

	/**
	 * Compute $join_table name (maybe main-table if no info)
	 */
	protected function join_table_alias(int $field)
	{
		$fdd = $this->fdd[$field];
		if (!isset($fdd[self::FDD_VALUES]['join'])) {
			return self::MAIN_ALIAS;
		}
		return self::JOIN_ALIAS.$this->join_table_index($field);
	}

	/**
	 * Add some defaults for missing 'values' fields.
	 */
	protected function values_with_defaults(int $field)
	{
		$values = $this->fdd[$field][self::FDD_VALUES]?:[];

		$encode = $values['encode']?:self::TRIVIAL_ENCODE;

		$join_table = $this->join_table_alias($field);
		if (!isset($values['column'])) {
			$join_column = $this->fds[$field];
		} else {
			$join_column = $values['column'];
		}

		if (!isset($values['description'])) {
			$join_desc = sprintf($encode, self::TRIVIAL_DESCRIPION);
		} else {
			$join_desc = $values['description'];
		}

		if (!isset($values['orderby'])) {
			$orderBy = sprintf($encode.' ASC', self::TRIVIAL_DESCRIPION);
		} else {
			$orderBy = $values['orderby'];
		}

		return array_merge(
			[
				'join_table' => $join_table,
				'column' => $join_column,
				'description' => $join_desc,
				'orderby' => $orderBy,
				'encode' => $encode,
				'grouped' => false,
			],
			array_filter($values, function ($value) { return $value !== null; })
		);
	}

	/**
	 * Substitute placeholders in sql field:
	 *
	 * - $table Either PMEtable0 or PMEjoinNR if join information is specified
	 *
	 * - $column $this->fds[$field] or column of join info if set
	 *
	 * @param int $field Field number.
	 *
	 * @param bool $vanilla Return just the expression for the field.
	 *
	 */
	protected function sql_field($field, $vanilla = false)
	{
		$values = $this->values_with_defaults($field);

		$main_table = $this->sd.self::MAIN_ALIAS.$this->ed;
		$join_table = $this->sd.$values['join_table'].$this->ed;
		$join_column = $values['column'];
		$join_col_fqn = $join_table.'.'.$this->sd.$join_column.$this->ed;

		if ($vanilla) {
			return $join_col_fqn;
		}

		$join_desc = $this->sd.$values['description'].$this->ed;
		$join_desc_fqn = $join_table.'.'.$join_desc;
		$order_by = $values['orderby'];

		$fdd = $this->fdd[$field];
		$sql = $fdd['sql']?
			 : ($values['grouped']
				 ? 'GROUP_CONCAT(DISTINCT $join_col_enc ORDER BY $order_by)'
				 : null);

		if (!empty($sql)) {
			return $this->substituteVars(
				$sql, array(
					'main_table' => $main_table,
					'field_name' => $this->fds[$field],
					'join_table' => $join_table,
					'join_column' => $join_column,
					'join_col_fqn' => $join_col_fqn,
					'join_col_enc' => sprintf($values['encode'], $join_col_fqn),
					'join_description' => $join_desc,
					'join_desc_fqn' => $join_desc_fqn,
					'table' => $join_table,
					'column' => $join_column,
					'description' => $join_desc,
					'order_by' => $order_by,
				));
		} else {
			return $join_col_fqn;
		}
	}

	/**
	 * Generate the query code for one field.
	 *
	 * @param int $field Field number or field-name
	 *
	 * @param int $flags Binary combination of self::OMIT_SQL and
	 * self::OMIT_DESC:
	 * - self::OMIT_DESC Omit the description fields defined by
	 *   'values' part of fdd
	 * - self::OMIT_SQL Omit sql part of fdd.
	 */
	function fqn($field, $flags = self::COOKED) /* {{{ */
	{
		$dont_desc = !!($flags & self::OMIT_DESC);
		is_numeric($field) || $field = array_search($field, $this->fds);
		// if read SQL expression exists use it
		if ($this->col_has_sql($field) && (!$this->col_has_description($field) || $dont_desc)) {
			return $this->sql_field($field, $flags & self::OMIT_SQL);
		}
		// on copy/change/add always use simple key retrieving, or given sql descriptor
		if ($this->add_operation()
			// || $this->view_operation()
			// || $this->delete_operation()
			|| $this->copy_operation()
			|| $this->change_operation()) {
			return $this->sql_field($field, $flags & self::OMIT_SQL);
		} else {
			$fdd = $this->fdd[$field];
			$values = $fdd[self::FDD_VALUES];
			if (isset($values['description']) && ! $dont_desc) {

				$join_table = $this->join_table_alias($field);

				$column = $values['column'];
				$desc = $values['description'];
				$grouped = $values['grouped'];
				$orderBy = $values['orderby'];
				$descSubs = [
					'table' => $join_table,
					'column' => $column,
				];

				// normalize $desc
				if (!is_array($desc)) {
					$desc = [ 'columns' => [ $desc, ] ];
				} else {
					if (empty($desc['columns'])) {
						$desc['columns'] = $desc;
					}
					if (!empty($desc['columns']) && !is_array($desc['columns'])) {
						$desc['columns'] = [ $desc['columns'], ];
					}
					if (!empty($desc['divs']) && !is_array($desc['divs'])) {
						$desc['divs'] = array_fill(0, max(0, count($desc['columns']) - 1), $desc['divs']);
					}
				}

				$ret = $grouped ? 'GROUP_CONCAT(DISTINCT ' : ''; // )
				$ret .= 'CONCAT('; // )
				$num_cols = sizeof($desc['columns']);
				if (!empty($desc['divs'][-1]) && is_array($desc['divs'])) {
					$ret .= '"'.addslashes($desc['divs'][-1]).'",';
				}
				$descFields = [];
				foreach ($desc['columns'] as $idx => $val) {
					if ($val) {
						if (!$this->hasSubstitutions($val)) {
							$val = '$table.'.$this->sd.$val.$this->ed;
						}
						$val = $this->substituteVars($val, $descSubs);
						$descField = 'IFNULL(CAST('.$val.' AS CHAR),';
						$null = empty($desc['ifnull'][$idx]) ? '""' : $desc['ifnull'][$idx];
						$descField .= $null.')';
						if (!empty($desc['divs'][$idx]) && is_array($desc['divs'])) {
							$descField .= ',"'.addslashes($desc['divs'][$idx]).'"';
						}
						$descFields[] = $descField;
					}
				}
				$ret .= implode(',', $descFields).',';
				$ret[strlen($ret) - 1] = ')';
				if ($grouped) {
					if (empty($orderBy)) {
						$orderBy = $join_table.'.'.$column.' ASC';
					} else {
						$orderBy = $this->substituteVars($orderBy, $descSubs);
					}
					$ret .= ' ORDER BY '.$orderBy.')';
				}
			} else {
				$ret = $this->sql_field($field, $flags & self::OMIT_SQL);
			}
		}
		return $ret;
	} /* }}} */

	function get_SQL_groupby_query_opts()
	{
		$fields = '';
		if (!empty($this->groupby)) {
			foreach($this->groupby as $field) {
				$fields .= $this->fqn($field, self::VANILLA).',';
			}
		}
		return trim($fields, ',');
	}

	function get_SQL_main_list_query_parts() /* {{{ */
	{
		/*
		 * Prepare the SQL Query from the data definition file
		 */
		$qparts[self::QPARTS_TYPE]	  = self::QPARTS_SELECT;
		$qparts[self::QPARTS_SELECT] = $this->get_SQL_column_list();
		// Even if the key field isn't displayed, we still need its value
		foreach (array_keys($this->key) as $key) {
			if (!in_array ($key, $this->fds)) {
				$qparts[self::QPARTS_SELECT] .= ','.$this->fqn($key);
			}
		}
		// Do the same for group-by fields if "mrecs" are requested.
		if ($this->misc_enabled()) {
			foreach ($this->groupby as $key) {
				if (!in_array ($key, $this->fds)) {
					$qparts[self::QPARTS_SELECT] .= ','.$this->fqn($key);
				}
			}
		}
		$qparts[self::QPARTS_FROM]	 = @$this->get_SQL_join_clause();
		$qparts[self::QPARTS_WHERE] = $this->get_SQL_where_from_query_opts();
		$qparts[self::QPARTS_GROUPBY] = $this->get_SQL_groupby_query_opts();
		$qparts[self::QPARTS_HAVING] = $this->get_SQL_having_query_opts();
		// build up the ORDER BY clause
		if (isset($this->sfn) || isset($this->dfltsfn)) {
			$sfn = array_merge($this->sfn, $this->dfltsfn);
			$sort_fields   = array();
			$sort_fields_w = array();
			foreach ($sfn as $field) {
				if ($field[0] == '-') {
					$field = substr($field, 1);
					$desc  = true;
				} else {
					$field = $field;
					$desc  = false;
				}
				$sort_field	  = $this->fqn($field);
				$sort_field_w = $this->fdd[$field]['name'];
				$this->col_has_sql($field) && $sort_field_w .= ' (sql)';
				if ($desc) {
					$sort_field	  .= ' DESC';
					$sort_field_w .= ' '.$this->labels['descending'];
				} else {
					$sort_field_w .= ' '.$this->labels['ascending'];
				}
				$sort_fields[]	 = $sort_field;
				$sort_fields_w[] = $sort_field_w;
			}
			if (count($sort_fields) > 0) {
				$qparts[self::QPARTS_ORDERBY] = join(',', $sort_fields);
			}
		}
		$qparts[self::QPARTS_LIMIT] = $this->listall() ? '' : $this->sql_limit($this->fm,$this->inc);
		$this->sort_fields_w = $sort_fields_w; // due to display sorting sequence
		return $qparts;
	} /* }}} */

	function get_SQL_main_list_query($qparts) /* {{{ */
	{
		return $this->get_SQL_query($qparts);
	} /* }}} */

	function get_SQL_query($parts) /* {{{ */
	{
		foreach ($parts as $k => $v) {
			$parts[$k] = trim($parts[$k]);
		}
		switch (strtoupper($parts[self::QPARTS_TYPE])) {
		case self::SQL_SELECT:
			$ret  = 'SELECT ';
			$ret .= $parts[self::QPARTS_SELECT];
			$ret .= ' FROM '.$parts[self::QPARTS_FROM];
			if (strlen(@$parts[self::QPARTS_WHERE]) > 0) {
				$ret .= ' WHERE '.$parts[self::QPARTS_WHERE];
			}
			if (strlen(@$parts[self::QPARTS_GROUPBY]) > 0) {
				$ret .= ' GROUP BY '.$parts[self::QPARTS_GROUPBY];
			}
			if (strlen(@$parts[self::QPARTS_HAVING]) > 0) {
				$ret .= ' HAVING '.$parts[self::QPARTS_HAVING];
			}
			if (strlen(@$parts[self::QPARTS_ORDERBY]) > 0) {
				$ret .= ' ORDER BY '.$parts[self::QPARTS_ORDERBY];
			}
			if (strlen(@$parts[self::QPARTS_LIMIT]) > 0) {
				$ret .= ' '.$parts[self::QPARTS_LIMIT];
			}
			if (strlen(@$parts[self::QPARTS_PROCEDURE]) > 0) {
				$ret .= ' PROCEDURE '.$parts[self::QPARTS_PROCEDURE];
			}
			break;
		case 'UPDATE':
			$ret  = 'UPDATE '.$parts[self::QPARTS_TABLE];
			$ret .= ' SET '.$parts[self::QPARTS_FIELDS];
			if ($parts[self::QPARTS_WHERE] != '')
				$ret .= ' WHERE '.$parts[self::QPARTS_WHERE];
			break;
		case 'INSERT':
			$ret  = 'INSERT INTO '.$parts[self::QPARTS_TABLE];
			$ret .= ' VALUES '.$parts[self::QPARTS_VALUES];
			break;
		case 'DELETE':
			$ret  = 'DELETE FROM '.$parts[self::QPARTS_TABLE];
			if ($parts[self::QPARTS_WHERE] != '')
				$ret .= ' WHERE '.$parts[self::QPARTS_WHERE];
			break;
		default:
			die('unknown query type');
			break;
		}
		return $ret;
	} /* }}} */

	function get_SQL_column_list() /* {{{ */
	{
		$fields = array();
		for ($k = 0; $k < $this->num_fds; $k++) {
			if (/*false*/ !$this->displayed[$k] && !in_array($k, $this->key_num) && !in_array($k, $this->groupby_num)) {
				continue;
			}
			$fields[] = $this->fqn($k).' AS '.$this->sd.'qf'.$k.$this->ed;
			if ($this->col_has_description($k)) {
				$fields[] = $this->fqn($k, self::OMIT_DESC).' AS '.$this->sd.'qf'.$k.'_idx'.$this->ed;
			}
			if ($this->col_has_datemask($k)) {
				// Date functions of mysql are a nightmare. Leave the
				// conversion to PHP further below.
				$fields[] = ($this->fqn($k)." AS ".$this->sd."qf".$k."_timestamp".$this->ed);
			}
		}
		return join(',', $fields);
	} /* }}} */

	function get_SQL_join_clause() /* {{{ */
	{
		$main_table	 = $this->sd.self::MAIN_ALIAS.$this->ed;
		$join_clause = $this->sd.$this->tb.$this->ed." AS $main_table";
		for ($k = 0, $numfds = sizeof($this->fds); $k < $numfds; $k++) {
			$main_column = $this->fds[$k];
			$join        = $this->fdd[$main_column][self::FDD_VALUES]['join'];
			if (is_array($join)) {
				if (isset($join['condition'])) {
					$join = $join['condition'];
				} else {
					$join = false;
				}
			}
			if ($join === false) {
				// use this just for values definitions
				continue;
			}
			if (isset($this->fdd[$main_column][self::FDD_VALUES]['db'])) {
				$dbp = $this->sd.$this->fdd[$main_column][self::FDD_VALUES]['db'].$this->ed.'.';
			} else {
				//$dbp = $this->dbp; not needed
			}

			$join_column = $this->sd.$this->fdd[$main_column][self::FDD_VALUES]['column'].$this->ed;
			$join_desc	 = $this->sd.$this->fdd[$main_column][self::FDD_VALUES]['description'].$this->ed;
			if ($join_desc == $this->sd.$this->ed) {
				$join_desc = $join_column;
			}
			if ($join_column != $this->sd.$this->ed) {

				$table = trim($this->fdd[$main_column][self::FDD_VALUES]['table']);
				$subquery = stripos($table, self::SQL_SELECT) !== false;
				if ($subquery) {
					$table = '('.$table.')';
				} else {
					$table = $dbp.$this->sd.$table.$this->ed;
				}
				$join_table = $this->sd.self::JOIN_ALIAS.$k.$this->ed;
				$ar = array(
					'data_base'        => $dbp,
					'main_table'	   => $main_table,
					'main_column'	   => $this->sd.$main_column.$this->ed,
					'main_col_fqn'     => $main_table.'.'.$this->sd.$main_column.$this->ed,
					'join_table'	   => $join_table,
					'join_column'	   => $join_column,
					'join_col_fqn'     => $join_table.'.'.$join_column,
					'join_description' => $join_desc,
					'join_desc_fqn'    => $join_table.'.'.$join_desc,
				);
				$join_clause .= " LEFT JOIN ".$table." AS $join_table ON (";
				$join_clause .= !empty($join)
					? $this->substituteVars($join, $ar)
					: "$join_table.$join_column = $main_table.".$this->sd.$main_column.$this->ed;
				$join_clause .= ')';
			}
		}
		return $join_clause;
	} /* }}} */

	function get_SQL_where_from_query_opts($qp = null, $text = 0) /* {{{ */
	{
		if ($qp == null) {
			$qp = $this->query_opts;
		}
		$where = array();
		foreach ($qp as $field => $ov) {
			if (is_numeric($field)) {
				$tmp_where = array();
				foreach ($ov as $field2 => $ov2) {
					$tmp_where[] = sprintf('%s %s %s', $field2, $ov2['oper'], $ov2['value']);
				}
				$where[] = '('.join(' OR ', $tmp_where).')';
			} else {
				if (is_array($ov['value'])) {
					$tmp_ov_val = '';
					$inner_null = false;
					foreach ($ov['value'] as $ov_val) {
						strlen($tmp_ov_val) > 0 && $tmp_ov_val .= ' OR ';
						// @todo should literal 0 match null?
						if ($ov_val == '') {
							// interprete this as empty or NULL
							$this_ov_val = sprintf("%s LIKE ''", $field);
						} else {
							$this_ov_val = sprintf('FIND_IN_SET("%s",%s)', $ov_val, $field);
						}
						if (empty($ov_val)) {
							$inner_null = true;
							$tmp_ov_val .= sprintf("(%s IS NULL OR %s)", $field, $this_ov_val);
						} else {
							$tmp_ov_val .= $this_ov_val;
						}
					}
					if (isset($ov['oper']) &&
						strtoupper($ov['oper']) == 'NOT' || $ov['oper'] == '!') {
						if ($inner_null) {
							$tmp_ov_val = sprintf('NOT (%s)', $tmp_ov_val);
						} else {
							$tmp_ov_val = sprintf('(%s IS NULL OR NOT (%s))',
												  $field, $tmp_ov_val);
						}
					}
					$where[] = "($tmp_ov_val)";
				} else {
					$where[] = sprintf('%s %s %s', $field, $ov['oper'], $ov['value']);
				}
			}
		}

		// allow some basic substitutions
		$subs = array(
			'main_table'  => $this->tb,
			'table' => self::MAIN_ALIAS,
			'record_id'   => implode(',', $this->rec),
		);
		if (!empty($this->rec)) {
			foreach ($this->rec as $recKey => $recValue) {
				$subs['record_id['.$recKey.']'] = $recValue;
			}
		}

		// Add any coder specified 'AND' filters
		if (!$text && ($filter = $this->filters['AND'])) {
			$where[] = $this->substituteVars($filter, $subs);
		}

		/* Join WHERE parts by AND */
		$where = join(' AND ', $where);

		if ($text) {
			return str_replace('%', '*', $where);
		}

		/* Add any coder specified 'OR' filters. If where is still
		 * empty at this point, then it is implicitly "true", hence
		 * adding further "OR" filters does not make sense.
		 */
		if ($where !== '' && ($filter = $this->filters['OR'])) {
			$where = '('.$where.') OR ('.$this->substituteVars($filter, $subs).')';
		}

		return $where;
	} /* }}} */

	function get_SQL_having_query_opts($qp = null, $text = 0) /* {{{ */
	{
		if ($qp == null) {
			$qp = $this->query_group_opts;
		}
		$having = array();
		foreach ($qp as $field => $ov) {
			if (is_numeric($field)) {
				$tmp_where = array();
				foreach ($ov as $field2 => $ov2) {
					$tmp_where[] = sprintf('%s %s %s', $field2, $ov2['oper'], $ov2['value']);
				}
				$having[] = '('.join(' OR ', $tmp_where).')';
			} else {
				if (is_array($ov['value'])) {
					$tmp_ov_val = '';
					foreach ($ov['value'] as $ov_val) {
						strlen($tmp_ov_val) > 0 && $tmp_ov_val .= ' OR ';
						if ($ov_val == '') {
							// interprete this as empty or NULL
							$tmp_ov_val .= sprintf("(%s IS NULL OR %s LIKE '')", $field, $field);
						} else {
							$tmp_ov_val .= sprintf('FIND_IN_SET("%s",%s)', $ov_val, $field);
						}
					}
					if (isset($ov['oper']) &&
						strtoupper($ov['oper']) == 'NOT' || $ov['oper'] == '!') {
						$tmp_ov_val = sprintf('(%s IS NULL OR NOT (%s))',
											  $field, $tmp_ov_val);
					}
					$having[] = "($tmp_ov_val)";
				} else {
					$having[] = sprintf('%s %s %s', $field, $ov['oper'], $ov['value']);
				}
			}
		}

		// Add any coder specified 'AND' filters
		if (!$text && ($filter = $this->having['AND'])) {
			$having[] = $filter;
		}

		$having = join(' AND ', $having);

		if ($text) {
			return str_replace('%', '*', $having);
		}

		/* Add any coder specified 'OR' filters. If where is still
		 * empty at this point, then it is implicitly "true", hence
		 * adding further "OR" filters does not make sense.
		 */
		if ($having !== '' && ($filter = $this->having['OR'])) {
			$having = '('.$having.') OR ('.$filter.')';
		}

		return $having;
	} /* }}} */

	function gather_query_opts() /* {{{ */
	{
		$this->query_opts = array();
		$this->query_group_opts = array();
		$this->prev_qfn	  = $this->qfn;
		$this->qfn		  = '';
		if ($this->clear_operation()) {
			return;
		}
		// gathers query options into an array, $this->query_opts or
		// $this->query_group_opts. The latter is needed if the field
		// is an aggregate.
		$query_opts = array();
		$query_group_opts = array();
		for ($k = 0; $k < $this->num_fds; $k++) {
			$l	  = 'qf'.$k;
			$lc	  = $l.'_comp';
			$li	  = $l.'_idx';
			$m	  = $this->get_sys_cgi_var($l);
			$mc	  = $this->get_sys_cgi_var($lc);
			$mi	  = $this->get_sys_cgi_var($li);
			if (! isset($m) && ! isset($mi)) {
				// retry with field-name to ease programmatic queries
				$fd   = $this->fds[$k];
				$l	  = 'qf'.$fd;
				$lc	  = $l.'_comp';
				$li	  = $l.'_idx';
				$m	  = $this->get_sys_cgi_var($l);
				$mc	  = $this->get_sys_cgi_var($lc);
				$mi	  = $this->get_sys_cgi_var($li);
				if (! isset($m) && ! isset($mi)) {
					continue;
				}
			}

			if ($this->col_needs_having($k)) {
				$qo = &$this->query_group_opts;
			} else {
				$qo = &$this->query_opts;
			}

			if (is_array($m) || is_array($mi)) {
				$fqn_flags = self::COOKED;
				if (is_array($mi)) {
					$fqn_flags = self::OMIT_DESC;
					$m = $mi;
					$l = $li;
				}
				if (in_array('*', $m)) {
					continue;
				}
				if (is_array($mc)) {
					foreach ($mc as $idx => $cmp) {
						$this->qfn .= '&'.$this->cgi['prefix']['sys'].$lc.'['.rawurlencode($idx).']='.rawurlencode($cmp);
					}
					$mc = implode(' ', $mc);
				}
				$not = ($mc == '!' || strtoupper($mc) == 'NOT') || strtoupper($mc) == 'NEGATE';
				if ($this->col_has_values($k) && $this->col_has_multiple($k)) {
					foreach (array_keys($m) as $key) {
						$m[$key] = addslashes($m[$key]);
					}
					$fqn = $this->fqn($k, $fqn_flags);
					$qo[$fqn] = array('value' => $m);
					if ($not) {
						$qo[$fqn]['oper'] = 'NOT';
					}
					//error_log(print_r($qo, true));
				} else {
					$qf_op = '';
					foreach (array_keys($m) as $key) {
						$val = '"'.addslashes($m[$key]).'"';
						if ($qf_op == '') {
							$qf_op	 = ($not ? 'NOT ' : '').'IN';
							$qf_val	 = $val;
						} else {
							$qf_val .= ','.$val;
						}
						$this->qfn .= '&'.$this->cgi['prefix']['sys'].$l.'['.rawurlencode($key).']='.rawurlencode($m[$key]);
					}
					$fqn_flags = isset($this->fdd[$k][self::FDD_VALUES]['description'])?self::OMIT_DESC:0;
					$qo[$this->fqn($k, $fqn_flags)] =
						array('oper'  => $qf_op, 'value' => "($qf_val)"); // )
				}
			} else if (isset($mi)) {
				if ($mi == '*') {
					continue;
				}
				if ($this->fdd[$k][self::FDD_SELECT] != 'C' &&
					$this->fdd[$k][self::FDD_SELECT] != 'M' &&
					$this->fdd[$k][self::FDD_SELECT] != 'D' && $mi == '') {
					continue;
				}
				$afilter = addslashes($mi);
				$qo[$this->fqn($k, true, true)] = array('oper'	=> '=', 'value' => "'$afilter'");
				$this->qfn .= '&'.$this->cgi['prefix']['sys'].$li.'='.rawurlencode($mi);
			} else if (isset($m)) {
				if ($m == '*') {
					continue;
				}
				if ($this->fdd[$k][self::FDD_SELECT] != 'C' &&
					$this->fdd[$k][self::FDD_SELECT] != 'M' &&
					$this->fdd[$k][self::FDD_SELECT] != 'D' && $m == '') {
					continue;
				}
				if ($this->fdd[$k][self::FDD_SELECT] == 'N') {
					$afilter = addslashes($m);
					$mc = in_array($mc, $this->comp_ops) ? $mc : '=';
					$qo[$this->fqn($k)] = array('oper' => $mc, 'value' => "'$afilter'");
					$this->qfn .= '&'.$this->cgi['prefix']['sys'].$l .'='.rawurlencode($m);
					$this->qfn .= '&'.$this->cgi['prefix']['sys'].$lc.'='.rawurlencode($mc);
				} else {

					/**
					 * The old behaviour was simply too unflexible:
					 * just adding wildcars around the search
					 * string. The following hack introduces the
					 * rules:
					 *
					 * '' or "" match itself or NULL
					 *
					 * 'something' or "something" match something, no
					 * wildcard encapsulation, i.e. LIKE 'something',
					 * where something of course may contain "user
					 * contributed" wildcards.
					 *
					 * !=something is a negation, i.e. NOT LIKE 'something'. We treat
					 * !='something' (or with double quotes) like !=something
					 *
					 * Otherwise the filter value is augmented with
					 * surrounding wildcards as before.
					 */

					$m    = trim($m); // trim accidentally injected
									  // space from free-form
									  // search-fields
					$ids  = array();
					$ar	  = array();
					$matches = array();
					$compare = 'contains';
					if (preg_match("/^'(.*)'$/", $m, $matches) ||
						preg_match('/^"(.*)"$/', $m, $matches) ||
						preg_match("/^==?'(.*)'$/", $m, $matches) ||
						preg_match('/^==?"(.*)"$/', $m, $matches) ||
						preg_match("/^==?(.*)$/", $m, $matches)) {
						// A quoted string, if empty, matches the
						// empty string or NULL, if not empty, matches
						// itself. Un-quoted strings are trimmed to
						// cope with accidentally injected
						// white-space. Quoted strings are not trimmed
						// in order to be able to search for
						// whitespace.
						$afilter = trim($matches[1]);
						$compare = 'equal';
					} else if (preg_match("/^!=?'(.*)'$/", $m, $matches) ||
							   preg_match('/^!=?"(.*)"$/', $m, $matches) ||
							   preg_match("/^!=?(.*)$/", $m, $matches)) {
						// negated match
						$afilter = trim($matches[1]);
						$compare = 'notequal';
					}

					$sqlKey = $this->fqn($k);

					switch ($compare) {
					case 'equal':
						if ($afilter == '') {
							$ar["IFNULL(".$sqlKey.",'')"] = array('oper' => 'LIKE', 'value' => "''");
						} else {
							// Some match, but exclude also the empty string
							$afilter = addslashes($matches[1]);
							$afilter = str_replace('*', '%', $afilter);
							$ar["IF(".$sqlKey." LIKE '',NULL,".$sqlKey.")"] =
								array('oper' => 'LIKE', 'value' => "'$afilter'");
						}
						break;
					case 'notequal':
						if ($afilter == '') {
							// not NULL and not '    '
							$ar[$sqlKey] = array('oper' => '>', 'value' => "''");
						} else if ($afilter == '%' || $afilter == '*') {
							// !='%' means: not something, so include
							// !NULL and '' into the results. So this
							// !is equivalent to =''
							$ar["IFNULL(".$sqlKey.",'')"] = array('oper' => 'LIKE', 'value' => "''");
						} else {
							$afilter = addslashes($matches[1]);
							$afilter = str_replace('*', '%', $afilter);
							$ar["IF(".$sqlKey." LIKE '',NULL,".$sqlKey.")"] =
								array('oper' => 'NOT LIKE', 'value' => "'$afilter'");
						}
						break;
					case 'contains':
					default:
						$afilter = addslashes($m);
						$afilter = '%'.str_replace('*', '%', $afilter).'%';
						$ar[$sqlKey] = array('oper' => 'LIKE', 'value' => "'$afilter'");
						break;

					}

					if (is_array($this->fdd[$k]['values2'])) {
						$sqlKey = $this->fqn($k, true, true);
						switch ($compare) {
						case 'equal':
							if ($afilter == '') {
								$ar["IFNULL(".$sqlKey.",'')"] = array('oper' => 'LIKE',
																	  'value' => "''");
							} else {
								$afilter = addslashes($matches[1]);
								$afilter = str_replace('*', '.*', $afilter);
								$afilter = str_replace('%', '.*', $afilter);
								foreach ($this->fdd[$k]['values2'] as $key => $val) {
									if (strlen($val) > 0 && preg_match('/'.$afilter.'/', $val)) {
										$ids[] = '"'.addslashes($key).'"';
									}
								}
								if (count($ids) > 0) {
									$ar[$sqlKey] = array('oper'	=> 'IN', 'value' => '('.join(',', $ids).')');
								}
							}
							break;
						case 'notequal':
							if ($afilter == '') {
								// not NULL and not '    '
								$ar[$sqlKey] = array('oper' => '>', 'value' => "''");
							} else if ($afilter == '%' || $afilter == '*') {
								$ar["IFNULL(".$sqlKey.",'')"] = array('oper' => 'LIKE',
																	  'value' => "''");
							} else {
								$afilter = addslashes($matches[1]);
								$afilter = str_replace('*', '.*', $afilter);
								$afilter = str_replace('%', '.*', $afilter);
								foreach ($this->fdd[$k]['values2'] as $key => $val) {
									if (preg_match('/'.$afilter.'/', $val) === false) {
										$ids[] = '"'.addslashes($key).'"';
									}
								}
								if (count($ids) > 0) {
									$ar[$sqlKey] = array('oper'	=> 'IN', 'value' => '('.join(',', $ids).')');
								}
							}
							break;
						case 'contains':
						default:
							foreach ($this->fdd[$k]['values2'] as $key => $val) {
								// stristr() performs like %M%, so we
								// implement the same augmented logic here, as described above.
								if (strlen($m) > 0 && stristr($val, $m)) {
									$ids[] = '"'.addslashes($key).'"';
								}
							}
							if (count($ids) > 0) {
								$ar[$sqlKey] = array('oper'	=> 'IN', 'value' => '('.join(',', $ids).')');
							}
							break;
						}
					}
					$qo[] = $ar;
					$this->qfn .= '&'.$this->cgi['prefix']['sys'].$l.'='.rawurlencode($m);
				}
			}
		}
		//$this->query_opts = $query_opts;
		//$this->query_group_opts  = $query_group_opts;
	} /* }}} */

	/*
	 * Create JavaScripts
	 */

	function form_begin($css_class = null) /* {{{ */
	{
		if (!$this->display['form']) {
			return;
		}

		$page_name = htmlspecialchars($this->page_name);
		if ($this->display['tabs']) {
			if (is_array($this->display['tabs'])) {
				// tab-names explicitly given.
				$this->tabs = array();
				$this->tabs_help    = array();
				$this->tabs_by_id   = array('tab-all' => 'all');
				$this->tabs_by_name = array('tab-all' => 'all');
				$this->cur_tab = 0; // unless overridden
				foreach($this->display['tabs'] as $idx => $tabdef) {
					if ($tabdef['id'] == 'tab-all') {
						$idx = 'all';
					}
					$this->tabs[$idx] = $tabdef['name'];
					$this->tabs_by_id[$tabdef['id']]= $idx;
					$this->tabs_by_name[$tabdef['name']] = $idx;
					if (isset($tabdef['default']) && $tabdef['default']) {
						$this->cur_tab = $idx;
					}
					if (isset($tabdef['tooltip'])) {
						$this->tabs_help[$idx] = $tabdef['tooltip'];
					}
				}
			} else {
				// tab definitions only in fdd
				for ($tab = $k = $this->cur_tab = 0; $k < $this->num_fds; $k++) {
					if (isset($this->fdd[$k]['tab'])) {
						if ($tab == 0 && $k > 0) {
							$this->tabs[0] = 'PMEtab0';
							$this->cur_tab = 1;
							$tab++;
						}
						if (is_array($this->fdd[$k]['tab'])) {
							$this->tabs[$tab] = @$this->fdd[$k]['tab']['name'];
							$this->fdd[$k]['tab']['default'] && $this->cur_tab = $tab;
						} else {
							$this->tabs[$tab] = @$this->fdd[$k]['tab'];
						}
						$this->tabs_by_id[$this->tabs[$tab]] = $tab;
						$this->tabs_by_name[$this->tabs[$tab]] = $tab;
						$tab++;
					}
				}
			}
			$this->cur_tab = $this->get_sys_cgi_var('cur_tab', $this->cur_tab);

			//error_log(print_r($this->tabs, true));

			// Transfer tab definitions to the CSS which will be
			// emitted automatically. Columns without tab definitions
			// will get the last mentioned tab. The first columns
			// without tab definitions will go to the default tab.
			$tab_idx = $this->tabs_by_name[$this->tabs[$this->cur_tab]];
			$tab_postfix = ' tab-'.$tab_idx;
			for ($k = 0; $k < $this->num_fds; $k++) {
				if (isset($this->fdd[$k]['tab'])) {
					$tab_def = $this->fdd[$k]['tab'];
					if (!is_array($tab_def)) {
						if (isset($this->tabs_by_id[$tab_def])) {
							$tab_idx = $this->tabs_by_id[$tab_def];
						} else if (isset($this->tabs_by_name[$tab_def])) {
							$tab_idx = $this->tabs_by_name[$tab_def];
						} // else give a damn
						$tab_postfix = ' tab-'.$tab_idx;
					} else {
						if (isset($tab_def['id'])) {
							$idList = $tab_def['id'];
							if (!is_array($idList)) {
								$idList = array($idList);
							}
							$tab_postfix = '';
							foreach($idList as $id) {
								$tab_idx = $this->tabs_by_id[$id];
								$tab_postfix .= ' tab-'.$tab_idx;
							}
						} else if (isset($tab_def['name'])) {
							$tab_idx = $this->tabs_by_name[$tab_def];
							$tab_postfix = ' tab-'.$idx;
						}
					}
				} // else just use the most recent tab-postfix

				// make sure we have css-postfix set. array_merge()
				// keeps the key of the later array
				$this->fdd[$k] = array_merge(array('css' => array('postfix' => '')), $this->fdd[$k]);
				$this->fdd[$k]['css']['postfix'] .= $tab_postfix; // append it
			}
		}

		if ($this->display['form']) {
			echo '<form class="'.$this->getCSSclass('form').' '.$css_class.'" method="post"';
			echo ' action="',$page_name,'" name="'.$this->cgi['prefix']['sys'].'form">',"\n";
			echo '  <input type="hidden" autofocus="autofocus" />'; // jquery hack.
		}
		return true;
	} /* }}} */

	function form_end() /* {{{ */
	{
		if ($this->display['form']) {
			echo '</form>',"\n";
		};
	} /* }}} */

	function display_tab_labels($position) /* {{{ */
	{
		if ($position != 'up' || !$this->tabs_enabled()) {
			return false;
		}
		echo '<table summary="labels" class="',$this->getCSSclass('tab', $position),'">',"\n";
		echo '<tr class="'.$this->getCSSclass('navigation', $position).' table-tabs">'."\n";
		echo '<td colspan="2" class="table-tabs">'."\n";
		echo '<div class="'.$this->getCSSclass('navigation', $position).' table-tabs pme-container">'."\n";
		echo '<ul class="'.$this->getCSSclass('navigation', $position).' table-tabs tab-menu">'."\n";
		foreach($this->tabs as $idx => $name) {
			$selected = strval($idx) == strval($this->cur_tab) ? ' selected' : '';
			if (isset($this->tabs_help[$idx])) {
				$title = ' title="'.$this->tabs_help[$idx].'"';
			} else {
				$title = '';
			}
			$class = $this->getCSSclass('navigation', $position).' table-tabs tab-menu'.$selected;
			echo '<li class="'.$class.'"'.$title.'>'."\n";
			echo '<a href="#tab-'.$idx.'">'.$name.'</a>'."\n";
			echo '</li>'."\n";
		}
		echo '</ul>'."\n";
		echo '</div>'."\n";
		echo '</td>'."\n";
		echo '</tr>'."\n";
		echo '</table>',"\n";
	} /* }}} */

	/*
	 * Display functions
	 */

	function display_add_record() /* {{{ */
	{
		for ($k = 0; $k < $this->num_fds; $k++) {
			if (! $this->displayed[$k]) {
				continue;
			}
			$helptip = NULL;
			if (isset($this->fdd[$k]['tooltip']) && $this->fdd[$k]['tooltip'] != '') {
				$helptip = $this->fdd[$k]['tooltip'];
			}
			$escape			= isset($this->fdd[$k]['escape']) ? $this->fdd[$k]['escape'] : true;
			$css_postfix	= @$this->fdd[$k]['css']['postfix'];
			$css_class_name = $this->getCSSclass('input', null, 'next', $css_postfix);
			$value          = $this->get_data_cgi_var($this->fds[$k]);
			$defaulted = $value === null;
			if ($defaulted) {
				$value  = @$this->fdd[$k]['default'];
				$escape && $value = htmlspecialchars($value);
				//error_log('default: '.$this->fdd[$k]['name'].': '.$value);
			}
			if ($this->hidden($k)) {
				$css_class_name = $this->getCSSclass('input', null, false, $css_postfix);
				echo $this->htmlHiddenData($this->fds[$k], $value, $css_class_name);
				continue;
			}
			echo '<tr class="',$this->getCSSclass('row', null, true, $css_postfix),'">',"\n";
			echo '<td class="',$this->getCSSclass('key', null, true, $css_postfix),'">';
			echo $this->fdd[$k]['name'],'</td>',"\n";
			echo '<td class="',$this->getCSSclass('value', null, true, $css_postfix),'"';
			echo $this->getColAttributes($k),">\n";
			if (isset($this->fdd[$k]['display']['prefix'])) {
				$prefix = $this->fdd[$k]['display']['prefix'];
				if (is_callable($prefix)) {
					echo call_user_func($prefix, 'add', 'prefix', $row, $k, $this);
				} else {
					echo $this->fdd[$k]['display']['prefix'];
				}
			}
			if ($this->col_has_php($k)) {
				$php = $this->fdd[$k]['php'];
				if (is_callable($php)) {
					echo call_user_func($php, false, 'add', $k, false, -1, $this);
				} else if (is_array($php)) {
					$opts = isset($php['parameters']) ? $php['parameters'] : '';
					echo call_user_func($php['function'], false, $opts, 'add', $k, false, -1, $this);
				} else if (file_exists($php)) {
					echo include($php);
				}
			} elseif ($this->col_has_values($k)) {
				$valgrp     = $this->set_values($k);
				$vals		= $valgrp['values'];
				$groups     = $valgrp['groups'];
				$data       = $valgrp['data'];
				$titles     = $valgrp['titles'];
				$selected	= $value;
				$multiple	= $this->col_has_multiple($k);
				$readonly	= $this->disabledTag($k);
				$mandatory  = $this->mandatory($k);
				$strip_tags = true;
				//$escape	    = true;
				if ($this->col_has_checkboxes($k) || $this->col_has_radio_buttons($k)) {
					echo $this->htmlRadioCheck($this->cgi['prefix']['data'].$this->fds[$k],
											   $css_class_name, $vals, $groups, $titles, $data,
											   $selected,
											   $multiple, $readonly, $mandatory,
											   $strip_tags, $escape, NULL, $helptip);
				} else {
					echo $this->htmlSelect($this->cgi['prefix']['data'].$this->fds[$k],
										   $css_class_name, $vals, $groups, $titles, $data,
										   $selected, $multiple, $readonly, $mandatory,
										   $strip_tags, $escape, NULL, $helptip);
				}
			} elseif (isset ($this->fdd[$k]['textarea'])) {
				echo $this->htmlTextarea($this->cgi['prefix']['data'].$this->fds[$k],
										 $css_class_name,
										 $k, $value, $escape, $helptip);
			} else {
				// Simple edit box required
				$len_props = '';
				$maxlen = intval($this->fdd[$k]['maxlen']);
				$size	= isset($this->fdd[$k]['size']) ? $this->fdd[$k]['size'] : min($maxlen, 40);

				if ($size > 0) {
					$len_props .= ' size="'.$size.'"';
				}
				if ($maxlen > 0) {
					$len_props .= ' maxlength="'.$maxlen.'"';
				}
				echo '<input class="',$css_class_name,'" ';
				if ($helptip) {
					echo 'title="'.$this->enc($helptip).'" ';
				}

				$type = $this->fdd[$k][self::FDD_SELECT] == 'N' ? 'number' : 'text';
				echo ($this->password($k) ? 'type="password"' : 'type="'.$type.'"');
				echo ($this->mandatory($k) ? ' required' : '');

				$readonly = $this->disabledTag($k);
				if ($readonly === false && $this->col_has_values($k)) {
					// force read-only if single value.
					$readonly = $this->display['readonly'];
				}

				if (isset($this->fdd[$k]['display']['attributes'])) {
					$attributes = $this->fdd[$k]['display']['attributes'];
					if (is_callable($attributes)) {
						$attributes = call_user_func($attributes, 'change', $row, $k, $this);
					}
					if (!is_array($attributes)) {
						$attributes= [ $attributes ];
					}
					foreach ($attributes as $attributeKey => $attributeValue) {
						switch ($attributeKey) {
						case 'readonly':
							if ($attributeValue === true) {
								$readonly = $this->display['readonly'];
							} else if ($attributeValue == false) {
								$readonly = false;
							}
							break;
						case 'disabled':
							if ($attributeValue === true) {
								echo ' '.$this->display['disabled'];
							}
							break;
						default:
							if (!is_string($attributeValue)) {
								echo ' '.$attributeKey.'='."'".json_encode($attributeValue)."'";
							} else {
								echo ' '.$attributeKey.'="'.$attributeValue.'"';
							}
							break;
						}
					}
				}

				echo ($readonly !== false ? ' '.$readonly : '');
				echo ' name="',$this->cgi['prefix']['data'].$this->fds[$k],'"';
				echo $len_props,' value="';
				echo $value;
				echo '" />';
			}
			if (isset($this->fdd[$k]['display']['postfix'])) {
				$postfix = $this->fdd[$k]['display']['postfix'];
				if (is_callable($postfix)) {
					echo call_user_func($postfix, 'add', 'postfix', $row, $k, $this);
				} else {
					echo $this->fdd[$k]['display']['postfix'];
				}
			}
			echo '</td>',"\n";
			if ($this->guidance) {
				$css_class_name = $this->getCSSclass('help', null, true, $css_postfix);
				$cell_value		= $this->fdd[$k]['help'] ? $this->fdd[$k]['help'] : '&nbsp;';
				echo '<td class="',$css_class_name,'">',$cell_value,'</td>',"\n";
			}
			echo '</tr>',"\n";
		}
	} /* }}} */

	// Actually: copy, change, delete AND view
	function display_copy_change_delete_record($row) /* {{{ */
	{
		/*
		 * For delete or change: SQL SELECT to retrieve the selected record
		 */

		if (true) {
			if (empty($row)) {
				return;
			}
		} else {
			$qparts[self::QPARTS_TYPE]	  = self::QPARTS_SELECT;
			$qparts[self::QPARTS_SELECT] = @$this->get_SQL_column_list();
			$qparts[self::QPARTS_FROM]	  = @$this->get_SQL_join_clause();
			$qparts[self::QPARTS_WHERE] = $this->key_record_where();
			//echo htmlspecialchars($this->rec.' '.$this->key);
			$res = $this->myquery($this->get_SQL_query($qparts),__LINE__);
			if (! ($row = $this->sql_fetch($res))) {
				return false;
			}
		}
		for ($k = 0; $k < $this->num_fds; $k++) {
			if (! $this->displayed[$k]) {
				continue;
			}
			$helptip = $this->fetchCellPopup($k, $row);
			if (!empty($this->fdd[$k]['encryption'])) {
				if (!isset($row["qf${k}_encrypted"])) {
					$row["qf${k}_encrypted"] = $row["qf$k"];
				}
				$row["qf$k"] = call_user_func($this->fdd[$k]['encryption']['decrypt'], $row["qf${k}_encrypted"]);
			}
			if ($this->copy_operation() || $this->change_operation()) {
				if ($this->hidden($k)) {
					if (!in_array($k, $this->key_num) || $this->change_operation()) {
						$css_postfix	= @$this->fdd[$k]['css']['postfix'];
						$css_class_name = $this->getCSSclass('input', null, false, $css_postfix);
						if ($this->col_has_multiple($k)) {
							$hiddenValues = empty($row["qf$k"]) ? [] : explode(',', $row["qf$k"]);
							$idx = 0;
							foreach ($hiddenValues as $value) {
								echo $this->htmlHiddenData($this->fds[$k].'['.($idx++).']', $value, $css_class_name);
							}
						} else {
							echo $this->htmlHiddenData($this->fds[$k], $row["qf$k"], $css_class_name);
						}
					}
					continue;
				}
				$css_postfix = @$this->fdd[$k]['css']['postfix'];
				echo '<tr class="',$this->getCSSclass('row', null, 'next', $css_postfix),'">',"\n";
				echo '<td class="',$this->getCSSclass('key', null, true, $css_postfix),'">';
				echo $this->fdd[$k]['name'],'</td>',"\n";
				/* There are two possibilities of readonly fields handling:
				   1. Display plain text for readonly timestamps, dates and URLs.
				   2. Display disabled input field
				   In all cases particular readonly field will NOT be saved. */
				if ($this->disabled($k) && ($this->col_has_datemask($k) || $this->col_has_URL($k))) {
					echo $this->display_delete_field($row, $k, $helptip);
				} elseif ($this->password($k)) {
					echo $this->display_password_field($row, $k, $helptip);
				} else {
					echo $this->display_change_field($row, $k, $helptip);
				}
				if ($this->guidance) {
					$css_class_name = $this->getCSSclass('help', null, true, $css_postfix);
					$cell_value		= $this->fdd[$k]['help'] ? $this->fdd[$k]['help'] : '&nbsp;';
					echo '<td class="',$css_class_name,'">',$cell_value,'</td>',"\n";
				}
				echo '</tr>',"\n";
			} elseif ($this->delete_operation() || $this->view_operation()) {
				if ($this->hidden($k)) {
					continue;
				}
				$css_postfix = @$this->fdd[$k]['css']['postfix'];
				echo '<tr class="',$this->getCSSclass('row', null, 'next', $css_postfix),'">',"\n";
				echo '<td class="',$this->getCSSclass('key', null, true, $css_postfix),'">';
				echo $this->fdd[$k]['name'],'</td>',"\n";
				if ($this->password($k)) {
					echo '<td class="',$this->getCSSclass('value', null, true, $css_postfix),'"';
					echo $this->getColAttributes($k),'>',$this->labels['hidden'],'</td>',"\n";
				} else {
					$this->display_delete_field($row, $k, $helptip);
				}
				if ($this->guidance) {
					$css_class_name = $this->getCSSclass('help', null, true, $css_postfix);
					$cell_value		= $this->fdd[$k]['help'] ? $this->fdd[$k]['help'] : '&nbsp;';
					echo '<td class="',$css_class_name,'">',$cell_value,'</td>',"\n";
				}
				echo '</tr>',"\n";
			}
		}
	} /* }}} */

	function display_change_field($row, $k, $help = NULL) /* {{{ */
	{
		$css_postfix	= @$this->fdd[$k]['css']['postfix'];
		$css_class_name = $this->getCSSclass('input', null, true, $css_postfix);
		$escape			= isset($this->fdd[$k]['escape']) ? $this->fdd[$k]['escape'] : true;
		echo '<td class="',$this->getCSSclass('value', null, true, $css_postfix),'"';
		echo $this->getColAttributes($k),">\n";
		if (isset($this->fdd[$k]['display']['prefix'])) {
			$prefix = $this->fdd[$k]['display']['prefix'];
			if (is_callable($prefix)) {
				echo call_user_func($prefix, 'change', 'prefix', $row, $k, $this);
			} else {
				echo $this->fdd[$k]['display']['prefix'];
			}
		}

		/* If $vals only contains one "multiple" value, then the
		 * multi-select stuff is at least confusing. Also, if there is
		 * only one possible value, then this value must not be changed.
		 */
		$select = $this->fdd[$k][self::FDD_SELECT]?:null;
		$multiValues = false;
		if (empty($select) || stristr("MCOD", $select) !== false) {
			$vals        = false;
			$groups      = false;
			$data        = false;
			$titles      = false;
			$valgrp      = false;
			if ($this->col_has_values($k)) {
				$valgrp = $this->set_values($k);
				$vals   = $valgrp['values'];
				$groups = $valgrp['groups'];
				$titles = $valgrp['titles'];
				$data   = $valgrp['data'];
				$multiValues = count($vals) > 1;
			}
		}

		/* If multi is not requested and the value-array has only one
		 * element, then do not emit multi-controls, because this has
		 * not been requested.
		 */

		if ($this->col_has_php($k)) {
			// ok, this stuff is just left completely to the caller
			if (!empty($vals)) {
				if ($this->col_has_multiple($k)) {
					$value = array();
					foreach(explode(',', $row["qf$k"]) as $key) {
						$value[] = $vals[$key];
					}
					$value = implode(',', $value);
				} else {
					$value = $vals[$row["qf$k"]];
				}
			} else {
				$value = $row["qf$k"];
			}
			$php = $this->fdd[$k]['php'];
			$rec = $this->key_record($this->rec);
			if (is_callable($php)) {
				echo call_user_func($php, $value, 'change',  $k, $row, $rec, $this);
			} else if (is_array($php)) {
				$opts = isset($php['parameters']) ? $php['parameters'] : '';
				echo call_user_func($php['function'], $value, $opts, 'change', $k, $row, $rec, $this);
			} else if (file_exists($php)) {
				echo include($php);
			}
		} elseif ($vals !== false && (stristr("MCOD", $select) !== false || $multiValues)) {
			$multiple = $this->col_has_multiple($k);
			$readonly = $this->disabledTag($k) || count($vals) == 0;
			$mandatory = $this->mandatory($k);
			$selected = @$row["qf$k"];
			if ($selected === null) {
				$selected = @$this->fdd[$k]['default'];
			}
			$strip_tags = true;
			// "readonly" is not possible for selects, radio stuff and
			// check-boxes. Display is "disbled", if read-only is
			// requested we emit a hidden input (or hidden inputs, if
			// multiple) with all the selected values.
			if ($this->readonly($k)) {
				$hiddenValues = trim($selected);
				if (!is_array($hiddenValues)) {
					$hiddenValues = empty($hiddenValues) ? [] : explode(',', $hiddenValues);
				}
				$array = $multiple ? '[]' : '';
				if (empty($hiddenValues)) {
					$hiddenValues[] = '';
				}
				foreach($hiddenValues as $hidden) {
					// @TODO now emitted twice?
					echo $this->htmlHiddenData($this->fds[$k].$array, $hidden, $css_class_name);
				}
			}
			if ($this->col_has_checkboxes($k) || $this->col_has_radio_buttons($k)) {
				echo $this->htmlRadioCheck($this->cgi['prefix']['data'].$this->fds[$k],
										   $css_class_name, $vals, $groups, $titles, $data,
										   $selected, $multiple, $readonly, $mandatory,
										   $strip_tags, $escape, NULL, $help);
			} else {
				echo $this->htmlSelect($this->cgi['prefix']['data'].$this->fds[$k],
									   $css_class_name, $vals, $groups, $titles, $data,
									   $selected, $multiple, $readonly, $mandatory,
									   $strip_tags, $escape, NULL, $help);
			}
		} elseif (!$vals && isset($this->fdd[$k]['textarea'])) {
			echo $this->htmlTextarea($this->cgi['prefix']['data'].$this->fds[$k],
									 $css_class_name,
									 $k, $row["qf$k"], $escape, $help);
		} else {
			$value    = $vals ? $vals[$row["qf$k"]] : $row["qf$k"];
			$readonly = $this->disabledTag($k);
			if ($readonly === false && $vals) {
				// force read-only if single value.
				$readonly = $this->display['readonly'];
			}
			$len_props = '';
			$maxlen = intval($this->fdd[$k]['maxlen']);
			$size	= isset($this->fdd[$k]['size']) ? $this->fdd[$k]['size'] : min($maxlen, 60);
			$type = $select == 'N' ? 'number' : 'text';
			if ($size > 0) {
				$len_props .= ' size="'.$size.'"';
			}
			if ($maxlen > 0) {
				$len_props .= ' maxlength="'.$maxlen.'"';
			}
			echo '<input class="',$css_class_name,'" type="',$type,'"';
			if ($help) {
				echo ' title="'.$this->enc($help).'" ';
			}
			echo ($this->mandatory($k) ? ' required' : '');

			if (isset($this->fdd[$k]['display']['attributes'])) {
				$attributes = $this->fdd[$k]['display']['attributes'];
				if (is_callable($attributes)) {
					$attributes = call_user_func($attributes, 'change', $row, $k, $this);
				}
				if (!is_array($attributes)) {
					$attributes= [ $attributes ];
				}
				foreach ($attributes as $attributeKey => $attributeValue) {
					switch ($attributeKey) {
					case 'readonly':
						if ($attributeValue === true) {
							$readonly = $this->display['readonly'];
						} else if ($attributeValue == false) {
							$readonly = false;
						}
						break;
					case 'disabled':
						if ($attributeValue === true) {
							echo ' '.$this->display['disabled'];
						}
						break;
					default:
						if (!is_string($attributeValue)) {
							echo ' '.$attributeKey.'='."'".json_encode($attributeValue)."'";
						} else {
							echo ' '.$attributeKey.'="'.$attributeValue.'"';
						}
						break;
					}
				}
			}

			echo ($readonly !== false ? ' '.$readonly : '');
			echo ' name="',$this->cgi['prefix']['data'].$this->fds[$k],'" value="';
			if ($this->col_has_datemask($k)) {
				echo $this->makeTimeString($k, $row);
			} else if ($escape) {
				echo htmlspecialchars($value);
			} else {
				echo $value;
			}
			echo '"',$len_props,' />',"\n";
		}
		if (isset($this->fdd[$k]['display']['postfix'])) {
			$postfix = $this->fdd[$k]['display']['postfix'];
			if (is_callable($postfix)) {
				echo call_user_func($postfix, 'change', 'postfix', $row, $k, $this);
			} else {
				echo $this->fdd[$k]['display']['postfix'];
			}
		}
		echo '</td>',"\n";
	} /* }}} */

	function display_password_field($row, $k, $help = NULL) /* {{{ */
	{
		$css_postfix = @$this->fdd[$k]['css']['postfix'];
		echo '<td class="',$this->getCSSclass('value', null, true, $css_postfix),'"';
		echo $this->getColAttributes($k),">\n";
		if (isset($this->fdd[$k]['display']['prefix'])) {
			$prefix = $this->fdd[$k]['display']['prefix'];
			if (is_callable($prefix)) {
				echo call_user_func($prefix, 'password', 'prefix', $row, $k, $this);
			} else {
				echo $this->fdd[$k]['display']['prefix'];
			}
		}
		$len_props = '';
		$maxlen = intval($this->fdd[$k]['maxlen']);
		$size	= isset($this->fdd[$k]['size']) ? $this->fdd[$k]['size'] : min($maxlen, 60);
		if ($size > 0) {
			$len_props .= ' size="'.$size.'"';
		}
		if ($maxlen > 0) {
			$len_props .= ' maxlength="'.$maxlen.'"';
		}
		echo '<input class="',$this->getCSSclass('value', null, true, $css_postfix),'" type="password"';
		if ($help) {
			echo ' title="'.$this->enc($help).'"';
		}
		echo ($this->disabled($k) ? ' disabled' : '');
		echo ' name="',$this->cgi['prefix']['data'].$this->fds[$k],'" value="';
		echo htmlspecialchars($row["qf$k"]),'"',$len_props,' />',"\n";
		if (isset($this->fdd[$k]['display']['postfix'])) {
			$postfix = $this->fdd[$k]['display']['postfix'];
			if (is_callable($postfix)) {
				echo call_user_func($postfix, 'password', 'postfix', $row, $k, $this);
			} else {
				echo $this->fdd[$k]['display']['postfix'];
			}
		}
		echo '</td>',"\n";
	} /* }}} */

	function display_delete_field($row, $k, $helptip) /* {{{ */
	{
		$css_postfix	= @$this->fdd[$k]['css']['postfix'];
		$css_class_name = $this->getCSSclass('value', null, true, $css_postfix);
		$title          = !empty($helptip) ? ' title="'.$this->enc($helptip).'"' : '';
		echo '<td class="',$css_class_name,'"',$this->getColAttributes($k),$title,">\n";
		if (isset($this->fdd[$k]['display']['prefix'])) {
			$prefix = $this->fdd[$k]['display']['prefix'];
			if (is_callable($prefix)) {
				echo call_user_func($prefix, 'display', 'prefix', $row, $k, $this);
			} else {
				echo $this->fdd[$k]['display']['prefix'];
			}
		}
		echo $this->cellDisplay($k, $row, $css_class_name);
		if (isset($this->fdd[$k]['display']['postfix'])) {
			$postfix = $this->fdd[$k]['display']['postfix'];
			if (is_callable($postfix)) {
				echo call_user_func($postfix, 'display', 'postfix', $row, $k, $this);
			} else {
				echo $this->fdd[$k]['display']['postfix'];
			}
		}
		echo '</td>',"\n";
	} /* }}} */

	/**
	 * Returns CSS class name
	 */
	function getCSSclass($name, $position  = null, $divider = null, $postfix = null, $postfix_data = null) /* {{{ */
	{
		static $div_idx = -1;
		$pfx = '';
		if ($this->css['separator'] === ' ') {
			$pfx = $this->css['prefix'].'-';
			$elements = array($name);
		} else {
			$elements = array($this->css['prefix'], $name);
		}
		if ($this->page_type && $this->css['page_type']) {
			if ($this->page_type != 'L' && $this->page_type != 'F') {
				$elements[] = $this->page_types[$this->page_type];
			}
		}
		if ($position && $this->css['position']) {
			$elements[] = $position;
		}
		if ($divider && $this->css['divider']) {
			if ($divider === 'next') {
				$div_idx++;
				if ($this->css['divider'] > 0 && $div_idx >= $this->css['divider']) {
					$div_idx = 0;
				}
			}
			$elements[] = $div_idx;
		}
		if (!empty($postfix) && is_string($postfix) && $postfix[0] != ' ') {
			$elements[] = $postfix;
			$postfix = null;
		}
		$css = $pfx.join($this->css['separator'].$pfx, $elements);

		if (is_callable($postfix)) {
			$postfix = call_user_func($postfix, $name, $position, $divider, $postfix_data, $this);
		}
		if (!empty($postfix)) {
			$css .= ' '.implode(' ', array_unique(array_map(trim, is_array($postfix) ? $postfix : [ $postfix ])));
		}
		return $css;
	} /* }}} */

	/**
	 * Returns field cell HTML attributes
	 */
	function getColAttributes($k) /* {{{ */
	{
		$colattrs = '';
		if (isset($this->fdd[$k]['colattrs'])) {
			if (is_array($this->fdd[$k]['colattrs'])) {
				foreach($this->fdd[$k]['colattrs'] as $name => $value) {
					$colattrs .= ' ';
					$colattrs .= $name.'="'.htmlspecialchars($value).'"';
				}
			} else {
				$colattrs .= ' ';
				$colattrs .= trim($this->fdd[$k]['colattrs']);
			}
		}
		if (isset($this->fdd[$k]['nowrap'])) {
			$colattrs .= ' nowrap';
		}
		return $colattrs;
	} /* }}} */

	/**
	 * Returns field cell align
	 */
	function getColAlign($k) /* {{{ */
	{
		if (isset($this->fdd[$k]['align'])) {
			/*return 'align="'.$this->fdd[$k]['align'].'"'; */
			return 'style="text-align:'.$this->fdd[$k]['align'].'"';
		} else {
			return '';
		}
	} /* }}} */

	/*
	 * Check whether $str contains substitutions.
	 */
	function hasSubstitutions($str)
	{
		return strpos($str, '$') !== false;
	}

	/**
	 * Substitutes variables in string
	 * (this is very simple but secure eval() replacement)
	 */
	function substituteVars($str, $subst_ar, $max_depth = 2) /* {{{ */
	{
		if ($max_depth <= 0) {
			return $str;
		}
		$array = preg_split('/(\\$[_a-zA-Z0-9[\\]]+)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
		$count = count($array);
		for ($i = 1; $i < $count; $i += 2) {
			$key = substr($array[$i], 1);
			if (isset($subst_ar[$key])) {
				$array[$i] = $this->substituteVars($subst_ar[$key], $subst_ar, $max_depth - 1);
			}
		}
		return $str = join('', $array);
	} /* }}} */

	/**
	 * Print URL
	 */
	function urlDisplay($k, $link_val, $disp_val, $css, $key) /* {{{ */
	{
		$escape = isset($this->fdd[$k]['escape']) ? $this->fdd[$k]['escape'] : true;
		if ($css == 'noescape') {
			$escape = false;
		}
		$ret  = '';
		$name = $this->fds[$k];
		$page = $this->page_name;
		$url  = $this->cgi['prefix']['sys'].'rec'.'='.$key.'&'.
			$this->cgi['prefix']['sys'].'fm'.'='.$this->fm.'&'.
			$this->cgi['prefix']['sys'].'np'.'='.$this->inc.'&'.
			$this->cgi['prefix']['sys'].'fl'.'='.$this->fl;
		$url .= '&'.$this->cgi['prefix']['sys'].'qfn'.'='.rawurlencode($this->qfn).$this->qfn;
		$url .= '&'.$this->get_sfn_cgi_vars().$this->cgi['persist'];
		$ar	  = array(
			'key'	=> $this->key_record_query_data($key),
			'name'	=> $name,
			'link'	=> $link_val,
			'value' => $disp_val,
			'css'	=> $css,
			'page'	=> $page,
			'url'	=> $url
			);
		$urllink = isset($this->fdd[$k]['URL'])
			?  $this->substituteVars($this->fdd[$k]['URL'], $ar)
			: $link_val;
		$urldisp = isset($this->fdd[$k]['URLdisp'])
			?  $this->substituteVars($this->fdd[$k]['URLdisp'], $ar)
			: $disp_val;
		$target = isset($this->fdd[$k]['URLtarget'])
			? 'target="'.htmlspecialchars($this->fdd[$k]['URLtarget']).'" '
			: '';
		$prefix_found  = false;
		$postfix_found = false;
		$prefix_ar	   = @$this->fdd[$k]['URLprefix'];
		$postfix_ar	   = @$this->fdd[$k]['URLpostfix'];
		is_array($prefix_ar)  || $prefix_ar	 = array($prefix_ar);
		is_array($postfix_ar) || $postfix_ar = array($postfix_ar);
		foreach ($prefix_ar as $prefix) {
			if (! strncmp($prefix, $urllink, strlen($prefix))) {
				$prefix_found = true;
				break;
			}
		}
		foreach ($postfix_ar as $postfix) {
			if (! strncmp($postfix, $urllink, strlen($postfix))) {
				$postfix_found = true;
				break;
			}
		}
		$prefix_found  || $urllink = array_shift($prefix_ar).$urllink;
		$postfix_found || $urllink = $urllink.array_shift($postfix_ar);
		if (strlen($urllink) <= 0 || strlen($urldisp) <= 0) {
			$ret = $escape ? '&nbsp;' : '';
		} else {
			if ($escape) {
				$urldisp = htmlspecialchars($urldisp);
			}
			$urllink = htmlspecialchars($urllink);
			$ret = '<a '.$target.'class="'.$css.'" href="'.$urllink.'">'.$urldisp.'</a>';
		}
		return $ret;
	} /* }}} */

	function makeTimeString($k, $row)
	{
		$value = '';
		$stamp = $row["qf$k"."_timestamp"];
		switch ($stamp) {
		case '':
		case 0:
		case '0000-00-00':
			// Invalid date and time
			break;
		default:
			$olddata = $data = $row["qf$k".'_timestamp'];
			if (!is_numeric($data)) {
				// Convert whatever is contained in the timestamp field to
				// seconds since the epoch.
				$data = strtotime($data);
			}
			if (@$this->fdd[$k]['datemask']) {
				$value = intval($data);
				$value = @date($this->fdd[$k]['datemask'], $value);
			} else if (@$this->fdd[$k]['strftimemask']) {
				$value = intval($data);
				$value = @strftime($this->fdd[$k]['strftimemask'], $value);
			}
		}
		return $value;
	}

	function formatValue($value, $k, $css, $key_rec)
	{
		$original_value = $value;
		if ($num_ar = @$this->fdd[$k]['number_format']) {
			if (! is_array($num_ar)) {
				$num_ar = array($num_ar);
			}
			if (count($num_ar) == 1) {
				list($nbDec) = $num_ar;
				$value = number_format($value, $nbDec);
			} else if (count($num_ar) == 3) {
				list($nbDec, $decPoint, $thSep) = $num_ar;
				$value = number_format($value, $nbDec, $decPoint, $thSep);
			}
		}
		if (@$this->fdd[$k]['mask']) {
			$value = sprintf($this->fdd[$k]['mask'], $value);
		}
		if ($this->col_has_URL($k)) {
			$value = $this->urlDisplay($k, $original_value, $value, $css, $key_rec);
		}
		return $value;
	}

	function cellDisplay($k, $row, $css = null) /* {{{ */
	{
		if ($this->password($k)) {
			return $this->labels['hidden'];
		}
		$escape	 = !empty($this->fdd[$k]['escape']) && !$this->col_has_URL($k);
		if (empty($css)) {
			$css_postfix = @$this->fdd[$k]['css']['postfix'];
			$css = $this->getCSSclass('cell', null, true, $css_postfix);
		}
		if ($css == 'noescape') {
			$escape = false;
		}
		if (!empty($this->fdd[$k]['encryption'])) {
			if (!isset($row["qf${k}_encrypted"])) {
				$row["qf${k}_encrypted"] = $row["qf$k"];
			}
			$row["qf$k"] = call_user_func($this->fdd[$k]['encryption']['decrypt'], $row["qf${k}_encrypted"]);
		}
		foreach ($this->key_num as $key => $key_num) {
			if ($this->col_has_description($key_num)) {
				$key_rec[$key] = $row['qf'.$key_num.'_idx'];
			} else {
				$key_rec[$key] = $row['qf'.$key_num];
			}
		}
		$key_rec = $this->key_record($key_rec);

		$this->col_has_values($k) && $this->set_values($k);
		if ($this->col_has_datemask($k)) {
			$value = $this->makeTimeString($k, $row);
		} else if (isset($this->fdd[$k]['values2'])) {
			if (isset($row['qf'.$k.'_idx'])) {
				$value = $row['qf'.$k.'_idx'];
			} else if (isset($row["qf${k}_encrypted"])) {
				$value = $row["qf${k}_encrypted"];
			} else {
				$value = $row["qf$k"];
			}
			if ($this->col_has_multiple($k)) {
				$value_ar  = explode(',', $value);
				$value_ar2 = array();
				foreach ($value_ar as $value_key) {
					if (isset($this->fdd[$k]['values2'][$value_key])) {
						$value_ar2[$value_key] = $this->formatValue($this->fdd[$k]['values2'][$value_key], $k, $css, $key_rec);
						$escape = false;
					}
				}
				if (!empty($this->fdd[$k]['values2glue'])) {
					$glue = $this->fdd[$k]['values2glue'];
				} else {
					$glue = ', ';
				}
				$value = join($glue, $value_ar2);
			} else {
				if (isset($this->fdd[$k]['values2'][$value])) {
					$value	= $this->formatValue($this->fdd[$k]['values2'][$value], $k, $css, $key_rec);
					$escape = false;
				}
			}
		} else {
			$value = $this->formatValue($row["qf$k"], $k, $css, $key_rec);
		}
		if (@$this->fdd[$k]['strip_tags']) {
			$value = strip_tags($value);
		}
		if (intval(@$this->fdd[$k]['trimlen']) > 0 && strlen($value) > $this->fdd[$k]['trimlen']) {
			$value = preg_replace("/[\r\n\t ]+/",' ',$value);
			$value = substr($value, 0, $this->fdd[$k]['trimlen'] - 3).'...';
		}
		if (@$this->fdd[$k]['phpview']) {
			$value = include($this->fdd[$k]['phpview']);
		}
		if ($this->col_has_php($k)) {
			$php = $this->fdd[$k]['php'];
			if (is_callable($php)) {
				return call_user_func($php, $value, 'display', $k, $row, $key_rec, $this);
			} else if (is_array($php)) {
				$opts = isset($php['parameters']) ? $php['parameters'] : '';
				return call_user_func($php['function'], $value, $opts, 'display', $k, $row, $key_rec, $this);
			} else if (file_exists($php)) {
				return include($php);
			}
		}
		if (strlen($value) <= 0) {
			return $escape ? '&nbsp;' : ''; // ??? why
		}
		if ($escape) {
			$value = htmlspecialchars($value);
		}
		return $escape ? nl2br($value) : $value;
	} /* }}} */

	function fetchCellPopup($k, $row, $cell_data = null)
	{
		$helptip = null;
		if (isset($this->fdd[$k]['display']['popup'])) {
			$popup = $this->fdd[$k]['display']['popup'];
			if (is_callable($popup)) {
				if (empty($cell_data)) {
					$cell_data = $this->cellDisplay($k, $row);
				}
				$helptip = call_user_func($popup, $cell_data);
			} else if (is_string($popup)) {
				switch ($popup) {
				case 'data'.substr($popup, strlen('data')):
					$cell = explode(':', $popup)[1];
					if (empty($cell) || $cell == $k || $cell == 'this' || $cell == 'self') {
						if (empty($cell_data)) {
							$cell_data = $this->cellDisplay($k, $row);
						}
						$helptip = $cell_data;
					} else {
						if ($cell == 'previous') {
							$cell = $k - 1;
						} else if ($cell == 'next') {
							$cell = $k + 1;
						}
						if (!is_numeric($cell)) {
							$cell = $this->fdn[$cell];
						}
						if (!empty($this->fdd[$cell])) {
							$helptip = $this->cellDisplay($cell, $row);
						}
					}
					break;
				case 'tooltip':
					if (isset($this->fdd[$k]['tooltip']) && $this->fdd[$k]['tooltip'] != '') {
						$helptip = $this->fdd[$k]['tooltip'];
					}
					break;
				default:
					break;
				}
			}
		} else if (isset($this->fdd[$k]['tooltip']) && $this->fdd[$k]['tooltip'] != '') {
			$helptip = $this->fdd[$k]['tooltip'];
		}
		return $helptip;
	}

	function fetchToolTip($css_class_name, $name, $label = false)
	{
		$title = $this->doFetchToolTip($css_class_name, $name, $label);
		if ($title == '') {
			// Don't emit tooltips
			return '';
		} else {
			return ' title="'.$this->enc($title).'" ';
		}
	}


	function doFetchToolTip($css_class_name, $name, $label = false)
	{
		// First clean the CSS-class, it may consist of more than one
		// class.
		$css_classes = preg_split('/\s+/', $css_class_name);
		foreach ($css_classes as $css_class_name) {
			// If we have an array for the class, use it.
			if (isset($this->tooltips[$css_class_name])
				&& is_array($this->tooltips[$css_class_name])) {
				$tips = $this->tooltips[$css_class_name];
				if (isset($tips[$name])) {
					return $tips[$name];
				} else if (isset($tips['default'])) {
					return $tips['default'];
				} else {
					return 'Tooltip-lookup failed for '.
						$css_class_name.', '.$name.($label ? ', '.$label : '');
				}
			}
		}

		// otherwise use name, label, css in that order
		if(isset($this->tooltips[$name])) {
			return $this->tooltips[$name];
		} elseif($label && isset($this->tooltips[$label])) {
			return $this->tooltips[$label];
		}
		foreach ([ ':'.$name, '' ] as $suffix) {
			foreach ($css_classes as $css_class_name) {
				if (isset($this->tooltips[$css_class_name.$suffix])) {
					return $this->tooltips[$css_class_name.$suffix];
				}
				// Then start stripping "components" from the end of the
				// class, i.e. if we have pme-filter-blah, then also try pme-filter
				$sfxpos = strrpos($css_class_name, '-');
				if ($sfxpos !== false) {
					$css_class_name = substr($css_class_name, 0, $sfxpos);
					if (isset($this->tooltips[$css_class_name.$suffix])) {
						return $this->tooltips[$css_class_name.$suffix];
					}
				}
			}
		}

		// We got really nothing. So what.
		return '';
	}

	/**
	 * Creates HTML submit input element
	 *
	 * @param	name			element name
	 * @param	label			key in the language hash used as label
	 * @param	css_class_name	CSS class name
	 * @param	disabled		if mark the button as disabled
	 * @param   style           inline style
	 */
	function htmlSubmit($name, $label, $css_class_name,
						$disabled = false, $style = NULL) /* {{{ */
	{
		// Note that <input disabled> isn't valid HTML, but most browsers support it
		if($disabled === -1) return;
		$markdisabled = $disabled ? ' disabled' : '';
		$ret = '<input'.$markdisabled.' type="submit" class="'.$css_class_name
			.'" name="'.$this->cgi['prefix']['sys'].ltrim($markdisabled).$name
			.'" value="'.(isset($this->labels[$label]) ? $this->labels[$label] : $label);
		$ret .='"';
		if(isset($style)) $ret .= ' style="'.$style.'"';
		$ret .= $this->fetchToolTip($css_class_name, $name, $label);
		$ret .= ' />';
		return $ret;
	} /* }}} */

	/**
	 * Creates HTML hidden input element
	 *
	 * @param	name	element name
	 * @param	value	value
	 */

	function htmlHiddenSys($name, $value, $css = null) /* {{{ */
	{
		return $this->htmlHidden($this->cgi['prefix']['sys'].$name, $value, $css);
	} /* }}} */

	function htmlHiddenData($name, $value, $css = null) /* {{{ */
	{
		return $this->htmlHidden($this->cgi['prefix']['data'].$name, $value, $css);
	} /* }}} */

	function htmlHidden($name, $value, $css = null) /* {{{ */
	{
		return '<input type="hidden" '
			.'name="'.htmlspecialchars($name).'" '
			.(!empty($css) ? 'class="'.htmlspecialchars($css).'" ' : '')
			.'value="'.htmlspecialchars($value).'" />'."\n";
	} /* }}} */

	/**
	 * Creates HTML select element (tag)
	 *
	 * @param	name		element name
	 * @param	css			CSS class name
	 * @param	kv_array	key => value array
	 * @param	kg_array	key => group array
	 * @param	kt_array	key => title array for title attributes
	 * @param	kd_array	key => data array for data attributes
	 * @param	selected	selected key (it can be single string, array of
	 *						keys or multiple values separated by comma)
	 * @param	multiple	bool for multiple selection
	 * @param	readonly	boolean or 'readonly' or 'disabled'
	 * @param	required	bool for required attribute
	 * @param	strip_tags	bool for stripping tags from values
	 * @param	escape		bool for HTML escaping values
	 * @param	js		string to be in the <select >, ususally onchange='..';
	 */
	function htmlSelect($name, $css,
						$kv_array,
						$kg_array = null,
						$kt_array = null,
						$kd_array = null,
						$selected = null,
						/* booleans: */
						$multiple = false,
						$readonly = false,
						$required = false,
						$strip_tags = false,
						$escape = true,
						$js = NULL, $help = NULL)
	{
		$ret = '<select class="'.htmlspecialchars($css).'" name="'.htmlspecialchars($name);
		if ($multiple) {
			$ret  .= '[]" multiple size="'.$this->multiple.'"';
			if (!is_array($selected)) {
				$selected = empty($selected) ? [] : explode(',', $selected);
			}
			$ret .= " data-initial-values='".json_encode($selected, JSON_NUMERIC_CHECK)."'";
		} else {
			$ret .= '"';
		}
		if ($help) {
			$ret .= ' title="'.$this->enc($help).'"';
		} else {
			$ret .= ' '.$this->fetchToolTip($css, $name, $css.'select');
		}
		if ($readonly !== false) {
			$ret .= ' disabled="disabled"'; // readonly does not make sense
		}
		if ($required !== false) {
			$ret .= ' required';
		}

		$ret .= ' '.$js.">\n";
		if (! is_array($selected)) {
			$selected = $selected === null ? array() : array((string)$selected);
		} else {
			$selected2 = [];
			foreach($selected as $val) $selected2[]=(string)$val;
			$selected = $selected2;
		}
		is_array($kd_array) || $kd_array = array();
		$found = false;
		$dfltGroup = empty($kg_array[-1]) ? null : $kg_array[-1];
		$dfltData = empty($kd_array[-1]) ? null : htmlspecialchars($kd_array[-1]);
		$lastGroup = null;
		$groupId = 0;
		$ret .= $multiple ? '' : '<option value=""></option>'."\n";
		//$this->logInfo('KV '.print_r($kv_array, true));
		//$this->logInfo('KG '.print_r($kg_array, true));
		foreach ($kv_array as $key => $value) {
			$group = !empty($kg_array[$key]) ? $kg_array[$key] : $dfltGroup;
			if (!empty($group) && $group != $lastGroup) {
				if (isset($lastGroup)) {
					$ret .= "</optgroup>\n";
					$groupId++;
				}
				$lastGroup = $group;
				$groupData = [
					'id' => $groupId,
					'default' => $lastGroup === $dfltGroup,
				];
				$groupData = "data-group-info='".json_encode($groupData)."'";
				$ret .= '<optgroup '.$groupData.' data-group-id="'.$groupId.'" label="'.$lastGroup.'">'."\n";
			}
			$encodedKey = htmlspecialchars($key);
			$ret .= '<option value="'.$encodedKey.'"';
			if ((! $found || $multiple) && in_array((string)$key, $selected, 1)) {
				//|| (count($selected) == 0 && ! $found && ! $multiple)) {
				$ret  .= ' selected="selected"';
				$found = true;
			}
			if (!empty($kt_array[$key])) {
				$title = htmlspecialchars($kt_array[$key]);
				$ret .= ' title="'.$title.'"';
			}
			if (!empty($kd_array[$key])) {
				$data = htmlspecialchars($kd_array[$key]);
				$ret .= " data-data='".$data."'";
			} else if (!empty($dfltData)) {
				$ret .= " data-data='".$dfltData."'";
			}
			if ($lastGroup) {
				$ret .= ' data-group-id="'.$groupId.'"';
			}
			$strip_tags && $value = strip_tags($value);
			$escape		&& $value = htmlspecialchars($value);
			$ret .= '>'.$value.'</option>'."\n";
		}
		if (isset($lastGroup)) {
			$ret .= "</optgroup>\n";
		}
		$ret .= '</select>';
		if ($readonly == 'readonly') {
			// selects can only be disabled, but not made readonly.
			foreach ($selected as $value) {
				$name = htmlspecialchars($name).($multiple ? '[]' : '');
				$ret .= $this->htmlHidden($name, $value, $css);
				if (!$multiple) {
					break;
				}
			}
		}
		return $ret;
	} /* }}} */

	/**
	 * Creates HTML checkboxes or radio buttons
	 *
	 * @param	name		element name
	 * @param	css			CSS class name
	 * @param	kv_array	key => value array
	 * @param	kg_array	key => group array, unused
	 * @param   kt_array    key => titles
	 * @param	kd_array	key => data array
	 * @param	selected	selected key (it can be single string, array of
	 *						keys or multiple values separated by comma)
	 * @param	multiple	bool for multiple selection (checkboxes)
	 * @param	readonly	boolean or 'readonly' or 'disabled'
	 * @param	required	bool for required attribute
	 * @param	strip_tags	bool for stripping tags from values
	 * @param	escape		bool for HTML escaping values
	 * @param	js		string to be in the <select >, ususally onchange='..';
	 */
	function htmlRadioCheck($name, $css,
							$kv_array,
							$kg_array = null,
							$kt_array = null,
							$kd_array = null,
							$selected = null, /* ...) {{{ */
							/* booleans: */
							$multiple = false,
							$readonly = false,
							$required = false,
							$strip_tags = false,
							$escape = true,
							$js = NULL, $help = NULL)
	{
		$ret = '';
		if ($multiple) {
			if (! is_array($selected) && $selected !== null) {
				$selected = explode(',', $selected);
			}
		}
		if (! is_array($selected)) {
			$selected = $selected === null ? array() : array($selected);
		}

		// special hack for 2 value radio with empty value/description as checkbox
		if  (count($kv_array) == 2 && !$multiple) {
			foreach ($kv_array as $key => $value) {
				if (empty($key)
					&& empty($value)
					&& !isset($kt_array[$key])
					&& !isset($kd_array[$key])) {
					unset($kv_array[$key]);
					if ($sel = array_search($key, $selected)) {
						unset($selected[$sel]);
					}
				}
			}
		}

		if (count($kv_array) == 1 || $multiple) {
			$type = 'checkbox';
		} else {
			$type = 'radio';
		}
		$br = count($kv_array) == 1 ? '' : '<br>';
		$found = false;
		foreach ($kv_array as $key => $value) {
			$tip = empty($kt_array[$key]) ? $help : $kt_array[$key];
			$labelhelp = !empty($tip)
				? ' title="'.$this->enc($tip).'" '
				: $this->fetchToolTip($css, $name, $css.'radiolabel');
			$inputhelp = !empty($tip)
				? ' title="'.$this->enc($tip).'" '
				: $this->fetchToolTip($css, $name, $css.'radio');
			$ret .= '<label'.$labelhelp.' class="'.htmlspecialchars($css).'-label">';
			$ret .= '<input type="'.$type.'"'
				.' name="'.htmlspecialchars($name).'[]"'
				.' value="'.htmlspecialchars($key).'"'
				.' class="'.htmlspecialchars($css).'"';
			if (!empty($kd_array[$key])) {
				$data = htmlspecialchars($kd_array[$key]);
				$ret .= " data-data='".$data."'";
			}
			// $ret .= $inputhelp;
			if ((! $found || $multiple) && in_array((string) $key, $selected, 1)
				|| (count($selected) == 0 && ! $found && ! $multiple)) {
				$ret  .= ' checked';
				$found = true;
			}
			if (!empty($readonly)) {
				$ret .= ' disabled'; // readonly attribute not supported
			}
			if (!empty($required)) {
				$ret .= ' required';
			}
			$strip_tags && $value = strip_tags($value);
			$escape		&& $value = htmlspecialchars($value);
			$ret .= '><span class="pme-label">'.$value.'</span></label>'.$br."\n";
		}
		return $ret;
	} /* }}} */

	function htmlTextarea($name, $css, $k, $value = null, $escape = true, $help = NULL) /* {{{ */
	{
		// mce mod start
		if (isset($this->fdd[$k]['textarea']['css'])) {
			$css_tag = $this->css['textarea'];
			if (is_string($this->fdd[$k]['textarea']['css'])) {
				$css_tag = $this->fdd[$k]['textarea']['css'];
			};
			if ($css_tag != '') {
				$css .= ' '.$css_tag;
			}
		};
		// mce mod end
		$ret = '<textarea class="'.$css.'" name="'.$name.'"';
		$ret .= $this->disabled($k) ? ' disabled' : '';
		$ret .= $this->mandatory($k) ? ' required' : '';
		if (intval($this->fdd[$k]['textarea']['rows']) > 0) {
			$ret .= ' rows="'.$this->fdd[$k]['textarea']['rows'].'"';
		}
		if (intval($this->fdd[$k]['textarea']['cols']) > 0) {
			$ret .= ' cols="'.$this->fdd[$k]['textarea']['cols'].'"';
		}
		if (intval($this->fdd[$k]['maxlen']) > 0) {
			$ret .= ' maxlength="'.$this->fdd[$k]['maxlen'].'"';
		}
		if (isset($this->fdd[$k]['textarea']['wrap'])) {
			$ret .= ' wrap="'.$this->fdd[$k]['textarea']['wrap'].'"';
		} else {
			$ret .= ' wrap="virtual"';
		}
		if ($help) {
			$ret .= ' title="'.$this->enc($help).'"';
		}
		$ret .= '>';
		if ($escape) {
			$ret .= htmlspecialchars($value);
		} else {
			$ret .= $value;
		}
		$ret .= '</textarea>'."\n";
		return $ret;
	} /* }}} */

	/**
	 * Returns original variables HTML code for use in forms or links.
	 *
	 * @param	mixed	$origvars		string or array of original varaibles
	 * @param	string	$method			type of method ("POST" or "GET")
	 * @param	mixed	$default_value	default value of variables
	 *									if null, empty values will be skipped
	 * @return							get HTML code of original varaibles
	 */
	function get_origvars_html($origvars, $method = 'post', $default_value = '') /* {{{ */
	{
		$ret	= '';
		$method = strtoupper($method);
		if ($method == 'POST') {
			if (! is_array($origvars)) {
				$new_origvars = array();
				foreach (explode('&', $origvars) as $param) {
					$parts = explode('=', $param, 2);
					if (! isset($parts[1])) {
						$parts[1] = $default_value;
					}
					if (strlen($parts[0]) <= 0) {
						continue;
					}
					if (strrchr($parts[0], '[]') == '[]') {
						$parts[0] = substr($parts[0], 0, -2);
						if (!isset($new_origvars[$parts[0]]) || !is_array($new_origvars[$parts[0]])) {
							$new_origvars[$parts[0]] = array();
						}
						$new_origvars[$parts[0]][] = $parts[1];
					} else {
						$new_origvars[$parts[0]] = $parts[1];
					}
				}
				$origvars =& $new_origvars;
			}
			foreach ($origvars as $key => $val) {
				if (is_array($val)) {
					$key = rawurldecode($key);
					foreach($val as $subkey => $subval) {
						$subval = rawurldecode($subval);
						$ret .= $this->htmlHidden($key.'['.$subkey.']', $subval);
					}
				} else {
					if (strlen($val) <= 0 && $default_value === null) {
						continue;
					}
					$key = rawurldecode($key);
					$val = rawurldecode($val);
					$ret .= $this->htmlHidden($key, $val);
				}
			}
		} else if (! strncmp('GET', $method, 3)) {
			if (! is_array($origvars)) {
				$ret .= $origvars;
			} else {
				foreach ($origvars as $key => $val) {
					if (strlen($val) <= 0 && $default_value === null) {
						continue;
					}
					$ret == '' || $ret .= '&amp;';
					$ret .= htmlspecialchars(rawurlencode($key));
					$ret .= '=';
					$ret .= htmlspecialchars(rawurlencode($val));
				}
			}
			if ($method[strlen($method) - 1] == '+') {
				$ret = "?$ret";
			}
		} else {
			trigger_error('Unsupported Platon::get_origvars_html() method: '
						  .$method, E_USER_ERROR);
		}
		return $ret;
	} /* }}} */

	function get_sfn_cgi_vars($alternative_sfn = null) /* {{{ */
	{
		/* echo '<PRE>'; */
		/* echo "arg: "."\n"; */
		/* print_r($alternative_sfn); */
		/* echo "this. "."\n"; */
		/* print_r($this->sfn); */
		/* echo '</PRE>'; */

		if ($alternative_sfn === null) { // FAST! (cached return value)
			static $ret = null;
			$ret == null && $ret = $this->get_sfn_cgi_vars($this->sfn);
			/* echo '<PRE>'; */
			/* echo "arg: "."\n"; */
			/* print_r($alternative_sfn); */
			/* echo "ret: "; */
			/* print_r($ret); */
			/* echo "\n"; */
			/* echo '</PRE>'; */
			return $ret;
		}
		$ret = '';
		$i	 = 0;
		foreach ($alternative_sfn as $val) {
			$ret != '' && $ret .= '&';
			$ret .= rawurlencode($this->cgi['prefix']['sys'].'sfn')."[$i]=".rawurlencode($val);
			$i++;
		}
		return $ret;
	} /* }}} */

	function get_default_cgi_prefix($type) /* {{{ */
	{
		switch ($type) {
		case 'operation':	return 'PME_op_';
		case 'sys':			return 'PME_sys_';
		case 'data':		return 'PME_data_';
		}
		return '';
	} /* }}} */

	function get_sys_cgi_var($name, $default_value = null) /* {{{ */
	{
		if (isset($this)) {
			return $this->get_cgi_var($this->cgi['prefix']['sys'].$name, $default_value);
		}
		return phpMyEdit::get_cgi_var(phpMyEdit::get_default_cgi_prefix('sys').$name, $default_value);
	} /* }}} */

	function get_data_cgi_var($name, $default_value = null) /* {{{ */
	{
		if (isset($this)) {
			return $this->get_cgi_var($this->cgi['prefix']['data'].$name, $default_value);
		}
		return phpMyEdit::get_cgi_var(phpMyEdit::get_default_cgi_prefix('data').$name, $default_value);
	} /* }}} */

	function get_cgi_var($name, $default_value = null) /* {{{ */
	{
		if (isset($this) && isset($this->cgi['overwrite'][$name])) {
			return $this->cgi['overwrite'][$name];
		}

		if (isset($_GET[$name])) {
			$var = $_GET[$name];
		} else if (isset($_POST[$name])) {
			$var = $_POST[$name];
		}
		if (! isset($var)) {
			/* Due to compatiblity "crap" inside PHP spaces, dots, open
			 * square brackets and everything in between ASCII(128) and
			 * ASCII(159) is converted to underscores internally. Cope
			 * with this crappy shit. The code below ignores ASCII >=
			 * 128. Give a damn on it.
			 */

			$cookedName = preg_replace('/[[:space:].[\x80-\x9f]/', '_', $name);
			if (isset($_GET[$cookedName])) {
				$var = $_GET[$cookedName];
			} else if (isset($_POST[$cookedName])) {
				$var = $_POST[$cookedName];
			}
		}

		if (!isset($var)) {
			$var = $default_value;
		}

		if (isset($this) && $var === null && isset($this->cgi['append'][$name])) {
			return $this->cgi['append'][$name];
		}
		return $var;
	} /* }}} */

	function get_server_var($name) /* {{{ */
	{
		if (isset($_SERVER[$name])) {
			return $_SERVER[$name];
		}
		global $HTTP_SERVER_VARS;
		if (isset($HTTP_SERVER_VARS[$name])) {
			return $HTTP_SERVER_VARS[$name];
		}
		global $$name;
		if (isset($$name)) {
			return $$name;
		}
		return null;
	} /* }}} */

	/*
	 * Debug functions
	 */

	function print_get_vars ($miss = 'No GET variables found') // debug only /* {{{ */
	{
		// we parse form GET variables
		if (is_array($_GET)) {
			echo "<p> Variables per GET ";
			foreach ($_GET as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $akey => $aval) {
						// $_GET[$k][$akey] = strip_tags($aval);
						// $$k[$akey] = strip_tags($aval);
						echo "$k\[$akey\]=$aval	  ";
					}
				} else {
					// $_GET[$k] = strip_tags($val);
					// $$k = strip_tags($val);
					echo "$k=$v	  ";
				}
			}
			echo '</p>';
		} else {
			echo '<p>';
			echo $miss;
			echo '</p>';
		}
	} /* }}} */

	function print_post_vars($miss = 'No POST variables found')	 // debug only /* {{{ */
	{
		global $_POST;
		// we parse form POST variables
		if (is_array($_POST)) {
			echo "<p>Variables per POST ";
			foreach ($_POST as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $akey => $aval) {
						// $_POST[$k][$akey] = strip_tags($aval);
						// $$k[$akey] = strip_tags($aval);
						echo "$k\[$akey\]=$aval	  ";
					}
				} else {
					// $_POST[$k] = strip_tags($val);
					// $$k = strip_tags($val);
					echo "$k=$v	  ";
				}
			}
			echo '</p>';
		} else {
			echo '<p>';
			echo $miss;
			echo '</p>';
		}
	} /* }}} */

	function print_vars ($miss = 'Current instance variables')	// debug only /* {{{ */
	{
		echo "$miss	  ";
		echo 'page_name=',$this->page_name,'   ';
		echo 'hn=',$this->hn,'	 ';
		echo 'un=',$this->un,'	 ';
		echo 'pw=',$this->pw,'	 ';
		echo 'db=',$this->db,'	 ';
		echo 'dbp=',$this->dbp,'   ';
		echo 'dbh=',$this->dbh,'   ';
		echo 'tb=',$this->tb,'	 ';
		echo 'key=',$this->key,'   ';
		echo 'inc=',$this->inc,'   ';
		echo 'options=',$this->options,'   ';
		echo 'fdd=',$this->fdd,'   ';
		echo 'fl=',$this->fl,'	 ';
		echo 'fm=',$this->fm,'	 ';
		echo 'sfn=',htmlspecialchars($this->get_sfn_cgi_vars()),'	';
		echo 'qfn=',$this->qfn,'   ';
		echo 'sw=',$this->sw,'	 ';
		echo 'rec=',implode(',', $this->rec),'   ';
		echo 'navop=',$this->navop,'   ';
		echo 'saveadd=',$this->saveadd,'   ';
		echo 'moreadd=',$this->moreadd,'   ';
		echo 'applyadd=',$this->applyadd,'   ';
		echo 'canceladd=',$this->canceladd,'   ';
		echo 'savechange=',$this->savechange,'	 ';
		echo 'morechange=',$this->morechange,'	 ';
		echo 'cancelchange=',$this->cancelchange,'	 ';
		echo 'reloadchange=',$this->reloadchange,'	 ';
		echo 'savecopy=',$this->savecopy,'	 ';
		echo 'applycopy=',$this->applycopy,'	 ';
		echo 'cancelcopy=',$this->cancelcopy,'	 ';
		echo 'savedelete=',$this->savedelete,'	 ';
		echo 'canceldelete=',$this->canceldelete,'	 ';
		echo 'cancelview=',$this->cancelview,'	 ';
		echo 'reloadview=',$this->reloadview,'	 ';
		echo 'operation=',$this->operation,'   ';
		echo "\n";
	} /* }}} */

	/*
	 * Display buttons at top and bottom of page
	 */
	function display_list_table_buttons($position) /* {{{ */
	{
		if (($but_str = $this->display_buttons($position)) === null)
			return;
		if($position == 'down') echo '<hr size="1" class="'.$this->getCSSclass('hr', 'down').'" />'."\n";
		$num_nav_cols = 0;
		echo '<table summary="navigation" class="',$this->getCSSclass('navigation', $position),'">',"\n";
		echo '<tr class="',$this->getCSSclass('navigation', $position),'">',"\n";
		echo '<td class="',$this->getCSSclass('buttons', $position),'">',"\n";
		++$num_nav_cols;
		echo $but_str,'</td>',"\n";
		// Message is now written here
		if (strlen(@$this->message) > 0) {
			echo '<td class="',$this->getCSSclass('message', $position),'">',$this->message,'</td>',"\n";
			++$num_nav_cols;
		}
		if($this->display['num_pages'] || $this->display['num_records']) {
			echo '<td class="',$this->getCSSclass('stats', $position),'">',"\n";
			++$num_nav_cols;
		}
		if($this->display['num_pages']) {
			if ($this->listall()) {
				echo $this->labels['Page'],':&nbsp;1&nbsp;',$this->labels['of'],'&nbsp;1';
			} else {
				$current_page = intval($this->fm / $this->inc) + 1;
				$total_pages  = max(1, ceil($this->total_recs / abs($this->inc)));
				echo $this->labels['Page'],':&nbsp;',$current_page;
				echo '&nbsp;',$this->labels['of'],'&nbsp;',$total_pages;
			}
		}
		if($this->display['num_records'])
			echo '&nbsp; ',$this->labels['Records'],':&nbsp;',$this->total_recs;
		if($this->display['num_pages'] || $this->display['num_records']) echo '</td>';
		echo '</tr>',"\n";
		if ($position == 'up' && $this->tabs_enabled()) {
			echo '<tr class="'.$this->getCSSclass('navigation', $position).' table-tabs">'."\n";
			echo '<td colspan="'.$num_nav_cols.'" class="table-tabs">'."\n";
			echo '<div class="'.$this->getCSSclass('navigation', $position).' table-tabs pme-container">'."\n";
			echo '<ul class="'.$this->getCSSclass('navigation', $position).' table-tabs tab-menu">'."\n";
			foreach($this->tabs as $idx => $name) {
				$selected = strval($idx) == strval($this->cur_tab) ? ' selected' : '';
				if (isset($this->tabs_help[$idx])) {
					$title = ' title="'.$this->tabs_help[$idx].'"';
				} else {
					$title = '';
				}
				$class = $this->getCSSclass('navigation', $position).' table-tabs tab-menu'.$selected;
				echo '<li class="'.$class.'"'.$title.'>'."\n";
				echo '<a href="#tab-'.$idx.'">'.$name.'</a>'."\n";
				echo '</li>'."\n";
			}
			echo '</ul>'."\n";
			echo '</div>'."\n";
			echo '</td>'."\n";
			echo '</tr>'."\n";
		}
		echo '</table>',"\n";
		if($position == 'up') echo '<hr size="1" class="'.$this->getCSSclass('hr', 'up').'" />'."\n";
	} /* }}} */

	/*
	 * Display buttons at top and bottom of page
	 */
	function display_record_buttons($position) /* {{{ */
	{
		if (($but_str = $this->display_buttons($position)) === null)
			return;
		if ($position == 'down') {
			if ($this->tabs_enabled()) $this->display_tab_labels('down');
			echo '<hr size="1" class="',$this->getCSSclass('hr', 'down'),'" />',"\n";
		}
		echo '<table summary="navigation" class="',$this->getCSSclass('navigation', $position),'">',"\n";
		echo '<tr class="',$this->getCSSclass('navigation', $position),'">',"\n";
		echo '<td class="',$this->getCSSclass('buttons', $position),'">',"\n";
		echo $but_str,'</td>',"\n";
		// Message is now written here
		//echo '</td>',"\n";
		if (strlen(@$this->message) > 0) {
			echo '<td class="',$this->getCSSclass('message', $position),'">',$this->message,'</td>',"\n";
		}
		echo '</tr></table>',"\n";
		if ($position == 'up') {
			if ($this->tabs_enabled()) $this->display_tab_labels('up');
			echo '<hr size="1" class="',$this->getCSSclass('hr', 'up'),'" />',"\n";
		}
	} /* }}} */

	function display_buttons($position) /* {{{ */
	{
		$ret = '';
		$nav_fnc = 'nav_'.$position;
		if(! $this->$nav_fnc()) {
			return;
		}
		if ($this->nav_buttons()) {
			$buttons = (isset($this->buttons[$this->page_type][$position]) &&
						is_array($this->buttons[$this->page_type][$position]))
				? $this->buttons[$this->page_type][$position]
				: ($this->nav_custom_multi()
				   ? $this->default_multi_buttons[$this->page_type]
				   : $this->default_buttons[$this->page_type]);
		} else {
			$buttons = (isset($this->buttons[$this->page_type][$position])
						&& is_array($this->buttons[$this->page_type][$position]))
				? $this->buttons[$this->page_type][$position]
				: ($this->nav_custom_multi()
				   ? $this->default_multi_buttons_no_B[$this->page_type]
				   : $this->default_buttons_no_B[$this->page_type]);
		}
		foreach ($buttons as $name) {
			$ret .= $this->display_button($name, $position)."\n";
		}
		return $ret;
	} /* }}} */

	function display_button($name, $position = 'up') /* {{{ */
	{
		if (is_array($name)) {
			if (isset($name['code'])) return $name['code'];
			$proto = array('name' => null,
						   'value' => null,
						   'css' => null,
						   'disabled' => false,
						   'style' => null);
			$name = array_merge($proto, $name);
			return $this->htmlSubmit($name['name'],
									 $name['value'],
									 $name['css'],
									 $name['disabled'],
									 $name['style']);
		}
		$disabled = 1; // show disabled by default
		$listAllClass = $this->listall() ? ' listall' : '';
		if ($name[0] == '+') { $name = substr($name, 1); $disabled =  0; } // always show disabled as enabled
		if ($name[0] == '-') { $name = substr($name, 1); $disabled = -1; } // don't show disabled
		if ($name == 'cancel') {
			return $this->htmlSubmit('cancel'.$this->page_types[$this->page_type], 'Cancel',
									 $this->getCSSclass('cancel', $position));
		}
		if (in_array($name, array('add','view','change','copy','delete'))) {
			$enabled_fnc = $name.'_enabled';
			$enabled	 = $this->$enabled_fnc();
			if ($name != 'add' && ! $this->total_recs && strstr('LF', $this->page_type))
				$enabled = false;
			return $this->htmlSubmit('operation', ucfirst($name),
									 $this->getCSSclass($name, $position), $enabled ? 0 : $disabled);
		}
		if ($name == 'misc') {
			$enabled	 = $this->misc_enabled();
			$cssname     = $this->misccss;
			$nav = '<span class="'.$this->getCSSclass($cssname, $position, null, $this->misccss2).'">';
			$nav .= $this->htmlSubmit(
				'operation', ucfirst($name),
				$this->getCSSclass($cssname, $position, null, $this->misccss2), $enabled ? 0 : $disabled);
			// One button to select the result of the current query
			$nav .= $this->htmlSubmit(
				'operation', '+',
				$this->getCSSclass($cssname.'+', $position, null, $this->misccss2), $enabled ? 0 : $disabled);
			// One button to deselect the result of the current query
			$nav .= $this->htmlSubmit(
				'operation', '-',
				$this->getCSSclass($cssname.'-', $position, null, $this->misccss2), $enabled ? 0 : $disabled);
			$nav .= '</span>';
			return $nav;
		}
		if ($name == 'savedelete') {
			$enabled	 = $this->delete_enabled();
			return $this->htmlSubmit('savedelete', 'Delete',
									 $this->getCSSclass('save', $position), $enabled ? 0 : $disabled);
		}
		if (in_array($name, array('save','apply','more','reload'))) {
			if	   ($this->page_type == 'D' && $name == 'save' ) { $value = 'Delete'; }
			elseif ($this->page_type == 'C' && $name == 'more' ) { $value = 'Apply'; }
			elseif ($name == 'reload') { $value = ucfirst($name); }
			else $value = ucfirst($name);
			return $this->htmlSubmit($name.$this->page_types[$this->page_type], $value,
									 $this->getCSSclass($name, $position));
		}
		if ($this->listall()) {
			$disabledprev = true;
			$disablednext = true;
			$total_pages  = 1;
			$current_page = 1;
		} else {
			$disabledprev = $this->fm <= 0;
			$disablednext =	 $this->fm + $this->inc >= $this->total_recs;
			$total_pages  = max(1, ceil($this->total_recs / abs($this->inc)));
			$current_page = ceil($this->fm / abs($this->inc)); // must + 1
		}
		$disabledfirst = $disabledprev;
		$disabledlast  = $disablednext;
		// some statistics first
		if ($name == 'total_pages') return $total_pages;
		if ($name == 'current_page') return ($current_page+1);
		if ($name == 'total_recs') return ($this->total_recs);
		// now some goto buttons/dropdowns/inputs...
		if ($name == 'goto_text') {
			$ret = '<span class="'.$this->getCSSclass('goto', $position).'">';
			$ret .= '<input type="text" class="'.$this->getCSSclass('gotopn', $position).$listAllClass.'"';
			$ret .= ' name="'.$this->cgi['prefix']['sys'].'navpn'.$position.'" value="'.($current_page+1).'"';
			$ret .= ' size="'.(strlen($total_pages)+1).'" maxlength="'.(strlen($total_pages)+1).'"';
			$ret .= $this->display_button('goto_combo',$position);
			$ret .= '</span>';
			return $ret;
		}
		if ($name == 'goto_combo') {
			$disabledgoto = ($this->listall() || ($disablednext && $disabledprev));
			if ($disabledgoto && $disabled < 0) return;
			$kv_array = array();
			for ($i = 0; $i < $total_pages; $i++) {
				$kv_array[$this->inc * $i] = $i + 1;
			}
			return $this->htmlSelect($this->cgi['prefix']['sys'].ltrim($disabledgoto).'navfm'.$position,
									 $this->getCSSclass('goto', $position).$listAllClass,
									 $kv_array, null, null, null,
									 (string)$this->fm, false, $disabledgoto, false,
									 false, true);
		}
		if ($name == 'goto') {
			$ret = '<span class="'.$this->getCSSclass('goto', $position).$listAllClass.'">';
			$ret .= $this->htmlSubmit('navop', 'Go to',
									  $this->getCSSclass('goto', $position),
									  ($this->listall() || ($disablednext && $disabledprev)) ? $disabled : 0);
			$ret .= $this->display_button('goto_combo',$position);
			$ret .= '</span>';
			return $ret;
		}
		if ($name == 'rows_per_page_combo') {
			$kv_array = array();
			$nummax = min($this->total_recs, 100);
			$kv_array[-1] = '&infin;';
			for ($i = 1; $i <= min(5,$nummax); ++$i) {
				$kv_array[$i] = $i;
				if ($this->inc == $i) {
					$selected = (string)$i;
				}
			}
			for ($i = min(10, $nummax); $i <= $nummax; $i += 5) {
				$kv_array[$i] = $i;
				if ($this->inc == $i) {
					$selected = (string)$i;
				}
			}
			if (!isset($selected)) {
				if ($this->inc > 0 /* && $nummax < $this->total_recs */) {
					$kv_array[$this->inc] = $this->inc;
					$selected = (string)$this->inc;
				} else /* if ($this->inc < 0) */ {
					$selected = '-1';
				}
			}
			$disabled = $this->total_recs <= 1;
			return $this->htmlSelect($this->cgi['prefix']['sys'].'navnp'.$position,
									 $this->getCSSclass('pagerows', $position),
									 $kv_array, null, null, null,
									 $selected, false, $disabled, false,
									 false, false);
		}
		if ($name == 'rows_per_page') {
			$disabled = $this->total_recs <= 1;
			$ret = '<span class="'.$this->getCSSclass('pagerows', $position).'">';
			$ret .= $this->htmlSubmit('navop', 'Rows/Page',
									  $this->getCSSclass('pagerows', $position),
									  $disabled);
			$ret .= $this->display_button('rows_per_page_combo',$position);
			$ret .= '</span>';
			return $ret;
		}
		if (in_array($name, array('first','prev','next','last','<<','<','>','>>'))) {
			$disabled_var = 'disabled'.$name;
			$name2 = $name;
			if (strlen($name) <= 2) {
				$nav_values = array('<<' => 'first', '<' => 'prev', '>' => 'next', '>>' => 'last');
				$disabled_var = 'disabled'.$nav_values[$name];
				$name2 = $nav_values[$name];
			}
			return $this->htmlSubmit('navop', ucfirst($name),
									 $this->getCSSclass($name2, $position).$listAllClass,
									 $$disabled_var ? $disabled : 0);
		}
		if(isset($this->labels[$name])) return $this->labels[$name];
		return $name;
	} /* }}} */

	function number_of_recs() /* {{{ */
	{
		$groupBy = @$this->get_SQL_groupby_query_opts();
		$count_parts = array(
			self::QPARTS_TYPE	 => self::SQL_SELECT,
			self::QPARTS_SELECT => 'COUNT(*)',
			self::QPARTS_FROM	 => @$this->get_SQL_join_clause(),
			self::QPARTS_GROUPBY => $groupBy,
			self::QPARTS_WHERE	 => @$this->get_SQL_where_from_query_opts(),
			self::QPARTS_HAVING => $this->get_SQL_having_query_opts()
			);
		$query = $this->get_SQL_main_list_query($count_parts);
		if (!empty($groupBy)) {
			$query = "SELECT COUNT(*) FROM (".$query.") PMEcount0";
		}
		$res = $this->myquery($query, __LINE__);
		$row = $this->sql_fetch($res, 'n');
		$this->total_recs = $row[0];
	} /* }}} */

	function filter_heading() /* {{{ */
	{
		// in case the filters need some setup, but ignore the return
		// value.
		$this->exec_triggers_simple(self::OPERATION_FILTER, self::TRIGGER_PRE);

		/* FILTER {{{
		 *
		 * Draw the filter and fill it with any data typed in last pass and stored
		 * in the array parameter keyword 'filter'. Prepare the SQL WHERE clause.
		 */

		// Filter row retrieval
		/*$fields	  = false;
		  $filter_row = array();//$row;
		  if (! is_array($filter_row)) { echo 'tttt';
		  unset($qparts[self::QPARTS_WHERE]);
		  $query = $this->get_SQL_query($qparts);
		  $res   = $this->myquery($query, __LINE__);
		  if ($res == false) {
		  $this->error('invalid SQL query', $query);
		  return false;
		  }
		  $filter_row = $this->sql_fetch($res);
		  }*/
		/* TODO - $fields is completely useless (michal)
		   Variable $fields is used to get index of particular field in
		   result. That index can be passed in example to $this->sql_field_len()
		   function. Use field names as indexes to $fields array. */
		// if (is_array($filter_row)) {
		// 	$fields = array_flip(array_keys($filter_row));
		// }
		//if ($fields != false) {
		$css_class_name = $this->getCSSclass('filter');
		$css_sys = $this->getCSSclass('sys');
		$hidden = $this->filter_operation() ? '' : ' '.$this->getCSSclass('hidden');
		echo '<tr class="',$css_class_name,$hidden,'">',"\n";
		echo '<td class="',$css_class_name,' ',$css_sys,'" colspan="',$this->sys_cols,'">';
		echo $this->htmlSubmit('filter', 'Query', $this->getCSSclass('query'));
		echo '</td>', "\n";
		for ($k = 0; $k < $this->num_fds; $k++) {
			if (! $this->displayed[$k] || $this->hidden($k)) {
				continue;
			}
			$css_postfix	  = @$this->fdd[$k]['css']['postfix'];
			$css_class_name	  = $this->getCSSclass('filter', null, null, $css_postfix);
			$this->field_name = $this->fds[$k];
			$fd				  = $this->field_name;
			$this->field	  = $this->fdd[$fd];
			$l	= 'qf'.$k;
			$lc = 'qf'.$k.'_comp';
			$li = 'qf'.$k.'_idx';
			if ($this->clear_operation()) {
				$m	= null;
				$mc = null;
				$mi = [];
			} else {
				$m	= $this->get_sys_cgi_var($l);
				$mc = $this->get_sys_cgi_var($lc);
				$mi = $this->get_sys_cgi_var($li)?:[];
				if (empty($m) && empty($mi)) {
					// retry with field-name to ease programmatic queries
					$fd   = $this->fds[$k];
					$l	  = 'qf'.$fd;
					$lc	  = $l.'_comp';
					$li	  = $l.'_idx';
					$m	  = $this->get_sys_cgi_var($l);
					$mc	  = $this->get_sys_cgi_var($lc);
					$mi	  = $this->get_sys_cgi_var($li)?:[];
				}
			}
			echo '<td class="',$css_class_name,'">';
			if ($this->password($k) || !$this->filtered($k)) {
				echo '&nbsp;';
			} else if ($this->fdd[$fd][self::FDD_SELECT] == 'D' ||
					   $this->fdd[$fd][self::FDD_SELECT] == 'M' ||
					   $this->fdd[$fd][self::FDD_SELECT] == 'O' ||
					   $this->fdd[$fd][self::FDD_SELECT] == 'C') {
				// Multiple fields processing
				// Default size is 2 and array required for values.
				$from_table = ! $this->col_has_values($k) || isset($this->fdd[$k][self::FDD_VALUES]['table']);
				$valgrp		= $this->set_values($k, $from_table);
				$vals		= array('*' => '*') + $valgrp['values'];
				$groups     = $valgrp['groups'];
				$titles     = $valgrp['titles'];
				$data       = $valgrp['data'];
				$selected	= $mi;
				$negate     = count($selected) == 0 ? null : $mc; // reset if none selected
				$negate_css_class_name = $this->getCSSclass('filter-negate', null, null, $css_postfix);
				$multiple	= $this->col_has_multiple_select($k);
				$multiple  |= $this->fdd[$fd][self::FDD_SELECT] == 'M' || $this->fdd[$fd][self::FDD_SELECT] == 'C';
				$readonly	= false;
				$strip_tags = true;
				//$escape	  = true;
				$escape	  = false;
				echo '<div class="'.$negate_css_class_name.'">';
				echo $this->htmlRadioCheck($this->cgi['prefix']['sys'].$l.'_comp',
										   $negate_css_class_name,
										   array('not' => $this->labels['Not']), null, null, null,
										   $negate,
										   true /* checkbox */);
				echo '</div><div class="'.$css_class_name.'">';
				echo $this->htmlSelect($this->cgi['prefix']['sys'].$l.'_idx', $css_class_name,
									   $vals, $groups, $titles, $data,
									   $selected, $multiple || true, $readonly, false, $strip_tags, $escape);
				echo '</div>';
			} elseif (($this->fdd[$fd][self::FDD_SELECT] == 'N' ||
					   $this->fdd[$fd][self::FDD_SELECT] == 'T')) {
				$len_props = '';
				$maxlen = !empty($this->fdd[$k]['maxlen']) ? intval($this->fdd[$k]['maxlen']) : 0;
				//$maxlen > 0 || $maxlen = intval($this->sql_field_len($res, $fields["qf$k"]));
				$maxlen > 0 || $maxlen = 500;
				$size = isset($this->fdd[$k]['size']) ? $this->fdd[$k]['size']
					: ($maxlen < 30 ? min($maxlen, 8) : 12);
				$len_props .= ' size="'.$size.'"';
				$len_props .= ' maxlength="'.$maxlen.'"';
				if ($this->fdd[$fd][self::FDD_SELECT] == 'N') {
					$css_comp_class_name = $this->getCSSclass('comp-filter', null, null, $css_postfix);

					$mc = in_array($mc, $this->comp_ops) ? $mc : '=';
					echo $this->htmlSelect($this->cgi['prefix']['sys'].$l.'_comp',
										   $css_comp_class_name,
										   $this->comp_ops, null, null, null,
										   $mc);
				}
				$name = $this->cgi['prefix']['sys'].'qf'.$k;
				echo '<input class="',$css_class_name,'" value="',htmlspecialchars(@$m);
				echo '" type="text" name="'.$name.'"',$len_props;
				echo ' '.$this->fetchToolTip($css_class_name, $name, $css_class_name.'text');
				echo ' />';
			} else {
				echo '&nbsp;';
			}
			echo '</td>',"\n";
		}
		echo '</tr>',"\n";
		//}
	} /* }}} */

	function display_sorting_sequence() /* {{{ */
	{
		/*
		 * Display sorting sequence
		 */
		$css_class_name = $this->getCSSclass('sortinfo');
		$disabled = false;
		if ($this->default_sort()) {
			$disabled = true;
			$css_class_name .= ' '.$this->getCSSclass('default');
		}
		$css_sys = $this->getCSSclass('sys');
		echo '<tr class="',$css_class_name,'">',"\n";
		echo '<td class="',$css_class_name,' ',$css_sys,'" colspan="',$this->sys_cols,'">';
		echo $this->htmlSubmit('sfn', 'Clear', $this->getCSSclass('clear'), $disabled);
		echo "</td>\n";
		echo '<td class="',$css_class_name,'" colspan="',$this->num_fields_displayed,'">';
		echo $this->labels['Sorted By'],': ',join(', ', $this->sort_fields_w),'</td></tr>',"\n";
	} /* }}} */

	function display_current_query() /* {{{ */
	{
		/*
		 * Display the current query
		 */
		$queries = array();
		if ($query = $this->get_SQL_where_from_query_opts(null, true)) {
			$queries[] = $query;
		}
		if ($query = $this->get_SQL_having_query_opts(null, true)) {
			$queries[] = $query;
		}
		$text_query = implode(' AND ', $queries);
		if ($text_query != '' || $this->display['query'] === 'always') {
			$css_class_name = $this->getCSSclass('queryinfo');
			$css_sys = $this->getCSSclass('sys');
			$disabled = false;
			if ($text_query == '') {
				$css_class_name .= ' '.$this->getCSSclass('empty');
				$disabled = true;
			}
			echo '<tr class="',$css_class_name,'">',"\n";
			echo '<td class="',$css_class_name,' ',$css_sys,'" colspan="',$this->sys_cols,'">';
			echo $this->htmlSubmit('sw', 'Clear', $this->getCSSclass('clear'), $disabled);
			echo "</td>\n";
			$htmlQuery = htmlspecialchars($text_query);
			//title="'.$htmlQuery.'"
			echo '<td class="',$css_class_name,'" colspan="',$this->num_fields_displayed,'">';
			echo '<span class="',$css_class_name,' label">',$this->labels['Current Query'],': </span>';
			echo '<span class="',$css_class_name,' info" title="',$htmlQuery,'">',$htmlQuery,'</span>';
			echo '</td></tr>',"\n";
		}
	} /* }}} */

	/* Quick and dirty general export. On each cell a call-back
	 * function is invoked with the html-output of that cell.
	 *
	 * This is just like list_table(), i.e. only the chosen range of
	 * data is displayed and in html-display order.
	 *
	 * @param $cellFilter $line[] = Callback($i, $j, $celldata)
	 *
	 * @param $lineCallback($i, $line)
	 *
	 * @param $css CSS-class to pass to cellDisplay().
	 */
	function export($cellFilter = false, $lineCallback = false, $css = 'noescape')
	{
		$error_reporting = error_reporting(E_ALL & ~E_NOTICE);

		// Header line
		$i = 1;
		$j = 1;
		$line = array();
		for ($k = 0; $k < $this->num_fds; $k++) {
			if (!$this->displayed[$k] || $this->hidden($k)) {
				continue;
			}
			$fd = $this->fds[$k];
			$fdn = $this->fdd[$fd]['name'];

			if ($cellFilter !== false) {
				$fdn = call_user_func($cellFilter, $i, $j++, $fdn);
			}
			if ($lineCallback !== false) {
				$line[] = $fdn;
			}
		}
		if ($lineCallback) {
			call_user_func($lineCallback, $i, $line);
		}

		/*
		 * Main list_table() query
		 *
		 * Each row of the HTML table is one record from the SQL query. We must
		 * perform this query before filter printing, because we want to use
		 * $this->sql_field_len() function. We will also fetch the first row to get
		 * the field names.
		 */
		$qparts = $this->get_SQL_main_list_query_parts();
		$query = $this->get_SQL_main_list_query($qparts);
		$res   = $this->myquery($query, __LINE__);
		if ($res == false) {
			$this->error('invalid SQL query', $query);
			return false;
		}
		while ($row = $this->sql_fetch($res)) {
			++$i;
			$j = 1;
			$line = array();
			for ($k = 0; $k < $this->num_fds; $k++) {
				$fd = $this->fds[$k];
				if (!$this->displayed[$k] || $this->hidden($k)) {
					continue;
				}
				$cell = '';
				if (isset($this->fdd[$k]['display']['prefix'])) {
					$prefix = $this->fdd[$k]['display']['prefix'];
					if (is_callable($prefix)) {
						$cell .= call_user_func($prefix, 'display', 'prefix', $row, $k, $this);
					} else {
						$cell .= $this->fdd[$k]['display']['prefix'];
					}
				}
				$cell .= $this->cellDisplay($k, $row, $css);
				if (isset($this->fdd[$k]['display']['postfix'])) {
					$postfix = $this->fdd[$k]['display']['postfix'];
					if (is_callable($postfix)) {
						$cell .= call_user_func($postfix, 'display', 'postfix', $row, $k, $this);
					} else {
						$cell .= $this->fdd[$k]['display']['postfix'];
					}
				}
				if ($cellFilter !== false) {
					$cell = call_user_func($cellFilter, $i, $j, $cell);
				}
				if ($lineCallback !== false) {
					$line[] = $cell;
				}
			}
			if ($lineCallback !== false) {
				call_user_func($lineCallback, $i, $line);
			}
		}

		error_reporting($error_reporting);
	}

	/* Quick and dirty CSV-export. Blobs will probably fail. But so
	 * what.
	 *
	 * This is just like list_table(), i.e. only the chosen range of
	 * data is displayed and in html-display order.
	 */
	function csvExport(&$handle, $delim = ',', $enclosure = '"', $filter = false)
	{
		$this->export(
			function ($i, $j, $cellData) use ($filter) {
				return call_user_func($filter, $cellData);
			},
			function ($i, $lineData) use ($handle, $delim, $enclosure) {
				fputcsv($handle, $lineData, $delim, $enclosure);
			});
	}

	/*
	 * Table Page Listing @@@@
	 */
	function list_table() /* {{{ */
	{
		if ($this->fm == '') {
			$this->fm = 0;
		}
		$this->fm = $this->navfm;
		if ($this->prev_operation()) {
			$this->fm = $this->fm - $this->inc;
			if ($this->fm < 0) {
				$this->fm = 0;
			}
		}
		if ($this->first_operation()) {
			$this->fm = 0;
		} // last operation must be performed below, after retrieving total_recs
		if ($this->next_operation()) {
			$this->fm += $this->inc;
		}
		$this->number_of_recs();
		if ($this->last_operation() || $this->fm > $this->total_recs) { // if goto_text is badly set
			$this->fm = (int)(($this->total_recs - 1)/$this->inc)*$this->inc;
		}
		// If sort sequence has changed, restart listing
		$this->qfn != $this->prev_qfn && $this->fm = 0;
		if (0) { // DEBUG
			echo 'qfn vs. prev_qfn comparsion ';
			echo '[<b>',htmlspecialchars($this->qfn),'</b>]';
			echo '[<b>',htmlspecialchars($this->prev_qfn),'</b>]<br />';
			echo 'comparsion <u>',($this->qfn == $this->prev_qfn ? 'proved' : 'failed'),'</u>';
			echo '<hr size="1" />';
		}
		/*
		 * If user is allowed to Change/Delete records, we need an extra column
		 * to allow users to select a record
		 */
		$select_recs = !empty($this->key) &&
			($this->change_enabled() || $this->delete_enabled() || $this->view_enabled());

		/*
		 * Display the SQL table in an HTML table
		 */
		$formCssClass = $this->getCSSclass('list', null, null, $this->css['postfix']);
		$this->form_begin($formCssClass);
		if ($this->display['form']) {
			//echo $this->get_origvars_html($this->get_sfn_cgi_vars());
			// Display buttons at top and/or bottom of page.
			$this->display_list_table_buttons('up');
			if ($this->cgi['persist'] != '') {
				echo $this->get_origvars_html($this->cgi['persist']);
			}
			if (! $this->filter_operation()) {
				echo $this->get_origvars_html($this->qfn);
			}
			if (false) {
				// Nope, transferred via check-box values
				foreach ($this->sfn as $key => $val) {
					echo $this->htmlHiddenSys('sfn['.$key.']', $val);
				}
			}
			echo $this->htmlHiddenSys('fl', $this->fl);
			echo $this->htmlHiddenSys('qfn', $this->qfn);
			echo $this->htmlHiddenSys('fm', $this->fm);
			echo $this->htmlHiddenSys('np', $this->inc);
			echo $this->htmlHiddenSys('translations', $this->translations);
			echo $this->htmlHiddenSys('cur_tab', $this->cur_tab);
		}

		if ($this->tabs_enabled()) {
			$tab_class = $this->cur_tab < 0 ? ' tab-all' : ' tab-'.$this->cur_tab;
		} else {
			$tab_class = '';
		}

		echo '<table class="',$this->getCSSclass('main'),$tab_class,'" summary="',$this->tb,'">',"\n";
		echo '<thead><tr class="',$this->getCSSclass('header'),'">',"\n";
		/*
		 * System (navigation, selection) columns counting
		 */
		$this->sys_cols	 = 0;
		$this->sys_cols += intval($this->filter_enabled() || $select_recs);
		if ($this->sys_cols > 0) {
			$this->sys_cols += intval($this->nav_buttons()
									  && ($this->nav_text_links() || $this->nav_graphic_links()));
			$this->sys_cols += intval($this->nav_custom_multi() !== false);
		}
		/*
		 * We need an initial column(s) (sys columns)
		 * if we have filters, Changes or Deletes enabled
		 */
		if ($this->sys_cols) {
			echo '<th class="',$this->getCSSclass('header'),' ',$this->getCSSclass('sys').'" colspan="',$this->sys_cols,'">';
			if ($this->filter_enabled()) {
				if ($this->display['query'] === 'always') {
					// use Javascript/CSS driven hide/show logic
					$hiddenCSS = ' '.$this->getCSSclass('hidden');
					$hideCSS = $this->getCSSclass('hide');
					$searchCSS = $this->getCSSclass('search');
					$clearCSS = $this->getCSSclass('clear');
					if ($this->filter_operation()) {
						$searchCSS .= $hiddenCSS;
					} else {
						$hideCSS .= $hiddenCSS;
						$clearCSS .= $hiddenCSS;
					}
					echo $this->htmlSubmit('sw', 'Search', $searchCSS);
					echo $this->htmlSubmit('sw', 'Hide', $hideCSS);
					//echo $this->htmlSubmit('sw', 'Clear', $clearCSS);
				} else {
					if ($this->filter_operation()) {
						echo $this->htmlSubmit('sw', 'Hide', $this->getCSSclass('hide'));
						echo '<br/>'."\n";
						echo $this->htmlSubmit('sw', 'Clear', $this->getCSSclass('clear'));
					} else {
						echo $this->htmlSubmit('sw', 'Search', $this->getCSSclass('search'));
					}
				}
			} else {
				echo '&nbsp;';
			}
			echo '</th>',"\n";
		}
		for ($k = 0; $k < $this->num_fds; $k++) {
			$fd = $this->fds[$k];
			$sfnidx = 0;
			$forward  = in_array("$k", $this->sfn, true);
			$backward = in_array("-$k", $this->sfn, true);
			$sorted	  = $forward || $backward;
			if ($sorted) {
				// Then we need also our index in the sorting hirarchy
				$search_key = $forward ? "$k" : "-$k";
				$sfnidx = array_search($search_key, $this->sfn, true);
				if ($sfnidx === false) {
					die("Everything is a foo-bar. Contact your Guru.");
				}
			} else {
				/* Even worse: we just cannot give
				 * some of the check-boxes explicit
				 * indices and the others
				 * not. Non-checked boxes need just
				 * one free index. As we have "oncheck
				 * -> submit" we can choose just one
				 * more than the number of current
				 * search fields.
				 */
				$sfnidx = count($this->sfn);
			}
			if (! $this->displayed[$k] || $this->hidden($k)) {
				continue;
			}
			$css_postfix	= @$this->fdd[$k]['css']['postfix'];
			$css_class_name = $this->getCSSclass('header', null, null, $css_postfix);
			$css_sort_class = $this->getCSSclass('sortfield');
			$css_nosort_class = $this->getCSSclass('nosort');
			$fdn = $this->fdd[$fd]['name'];
			if (/* !empty($this->fdd[$k]['encryption']) || */
				empty($this->fdd[$k]['sort']) ||
				$this->password($k)) {
				echo '<th class="',$css_class_name,' ',$css_nosort_class,'"';
				if (!empty($this->fdd[$k]['tooltip'])) {
					echo ' ','title="'.$this->fdd[$k]['tooltip'].'"',' ';
				}
				echo '>',$fdn,'</th>',"\n";
			} else {
				// Clicking on the current sort field reverses the sort order
				// Generate a button and a check
				// box. The check box is activated if the field is selected for sorting

				echo '<th class="'.$css_class_name.' '.$css_sort_class.'"';
				if (!empty($this->fdd[$k]['tooltip'])) {
					echo 'title="'.$this->fdd[$k]['tooltip'].'"';
				}
				echo '>';
				if (!$sorted) {
					echo "\n  ".$this->htmlSubmit("sort[$k]", $fdn, $this->getCSSclass('sort'));
				} else {
					echo "\n  ".$this->htmlSubmit("rvrt[$k]", $fdn, $this->getCSSclass('sort-rvrt'));
				}
				echo '<BR/>'."\n";
				echo '	<label class="'.$this->getCSSclass('sort')
					.'" for="'.$this->cgi['prefix']['sys'].'srt-'.$k.'"'
					.$this->fetchToolTip($this->getCSSclass('sort'), $this->labels['Sort Field'])
					.'>'."\n	  ".'<input class="'.$this->getCSSclass('sort')
					.'" id="'.$this->cgi['prefix']['sys'].'srt-'.$k
					.'" type="checkbox"  name="'.$this->cgi['prefix']['sys'].'sfn['.$sfnidx.']"'
					.$this->fetchToolTip($this->getCSSclass('sort'), $this->cgi['prefix']['sys'].'sfn[]')
					.' value="'.($backward ? "-$k" : $k).'"';
				if ($forward || $backward) {
					echo ' checked';
				}
				echo ' />'."\n	  ".'<div class="'.$this->getCSSclass('sort').'"'
					/*.$this->fetchToolTip($this->getCSSclass('sort'), $this->labels['Sort Field'])*/
					.'>'
					.$this->labels['Sort Field'].'</div>'."\n	 ".'</label>';
				echo '</th>'."\n";
			}
		}
		echo '</tr></thead><tbody>',"\n";

		/*
		 * Main list_table() query
		 *
		 * Each row of the HTML table is one record from the SQL query. We must
		 * perform this query before filter printing, because we want to use
		 * $this->sql_field_len() function. We will also fetch the first row to get
		 * the field names.
		 */
		$qparts = $this->get_SQL_main_list_query_parts();
		$query = $this->get_SQL_main_list_query($qparts);

		// filter
		if ($this->filter_operation() || $this->display['query'] === 'always') $this->filter_heading();
		// Display sorting sequence
		if ($qparts[self::QPARTS_ORDERBY] && $this->display['sort']) $this->display_sorting_sequence();
		// Display the current query
		if ($this->display['query']) $this->display_current_query();

		if ($this->nav_text_links() || $this->nav_graphic_links()) {
			$qstrparts = array();
			strlen($this->fl)			  > 0 && $qstrparts[] = $this->cgi['prefix']['sys'].'fl'.'='.$this->fl;
			strlen($this->fm)			  > 0 && $qstrparts[] = $this->cgi['prefix']['sys'].'fm'.'='.$this->fm;
			$qstrparts[] = $this->cgi['prefix']['sys'].'np'.'='.$this->inc;
			count($this->sfn)			  > 0 && $qstrparts[] = $this->get_sfn_cgi_vars();
			strlen($this->cgi['persist']) > 0 && $qstrparts[] = $this->cgi['persist'];
			foreach ($this->mrecs as $key => $value) {
				$qstrparts[] = $this->cgi['prefix']['sys'].'mrecs['.$key.']='.$value;
			}
			$qpview		 = $qstrparts;
			$qpcopy		 = $qstrparts;
			$qpchange	 = $qstrparts;
			$qpdelete	 = $qstrparts;
			$qp_prefix	 = $this->cgi['prefix']['sys'].'operation'.'='.$this->cgi['prefix']['operation'];
			$qpview[]	 = $qp_prefix.'View';
			$qpcopy[]	 = $qp_prefix.'Copy';
			$qpchange[]	 = $qp_prefix.'Change';
			$qpdelete[]	 = $qp_prefix.'Delete';
			$qpviewStr	 = htmlspecialchars($this->page_name.'?'.join('&',$qpview).$this->qfn);
			$qpcopyStr	 = htmlspecialchars($this->page_name.'?'.join('&',$qpcopy).$this->qfn);
			$qpchangeStr = htmlspecialchars($this->page_name.'?'.join('&',$qpchange).$this->qfn);
			$qpdeleteStr = htmlspecialchars($this->page_name.'?'.join('&',$qpdelete).$this->qfn);
		}

		/* Execute query constructed above */
		$res   = $this->myquery($query, __LINE__);
		if ($res == false) {
			$this->error('invalid SQL query', $query);
			return false;
		}
		$row = $this->sql_fetch($res);

		$fetched  = true;
		$first	  = true;
		$rowCount = 0;
		while ((!$fetched && ($row = $this->sql_fetch($res)) != false)
			   || ($fetched && $row != false)) {
			$fetched = false;
			$key_rec = [];
			foreach ($this->key_num as $key => $key_num) {
				if ($this->col_has_description($key_num)) {
					$key_rec[$key] = $row['qf'.$key_num.'_idx'];
				} else {
					$key_rec[$key] = $row['qf'.$key_num];
				}
			}
			$this->exec_data_triggers(self::SQL_QUERY_SELECT, $row);

			switch (count($key_rec)) {
				case 0:
					$recordData = '';
					break;
				case 1:
					$recordData = array_values($key_rec)[0];
					break;
				default:
					$recordData = json_encode($key_rec);
					break;
			}
			$recordQueryData = $this->key_record_query_data($key_rec);

			if (!empty($this->groupby)) {
				$groupby_rec = [];
				foreach ($this->groupby_num as $key => $key_num) {
					if ($this->col_has_description($key_num)) {
						$groupby_rec[$key] = $row['qf'.$key_num.'_idx'];
					} else {
						$groupby_rec[$key] = $row['qf'.$key_num];
					}
				}
				$mrecRecordData = json_encode($groupby_rec);
			} else {
				$groupby_rec = $key_rec;
				$mrecRecordData = $recordData;
			}
			$mrecRecordQueryData = $this->key_record_query_data($groupby_rec, true, 'groupby_rec');

			$operationCss = [];
			if ($this->view_enabled()) {
				$operationCss[] = 'view';
			}
			if ($this->change_enabled()) {
				$operationCss[] = 'change';
			}
			if ($this->copy_enabled()) {
				$operationCss[] = 'copy';
			}
			if ($this->delete_enabled()) {
				$operationCss[] = 'delete';
			}
			$operationCss = implode(
				' ',
				array_map(
					function($value) { return $this->css['prefix'].'-'.$value.'-enabled'; },
					$operationCss)
			);

			echo
				'<tr class="'.$this->getCSSclass('row', null, 'next', $this->css['row'], $row).' '.$operationCss.'"'."\n".
				'    data-'.$this->css['prefix'].'-options="'.$this->options.'"'."\n".
				'    data-'.$this->cgi['prefix']['sys']."rec='".$recordData."'"."\n".
				'    data-'.$this->cgi['prefix']['sys']."groupby_rec='".$mrecRecordData."'"."\n".
				'>';
			if ($this->sys_cols) { /* {{{ */
				$css_class_name = $this->getCSSclass('navigation', null, true);
				if ($select_recs) {
					if (! $this->nav_buttons() || $this->sys_cols > 1) {
						echo '<td class="',$css_class_name,'">';
					}
					if ($this->nav_text_links() || $this->nav_graphic_links()) {
						$queryAppend = '&' .
									 htmlspecialchars($recordQueryData) . '&' .
									 htmlspecialchars($mrecRecordQueryData);
						$viewQuery	 = $qpviewStr	. $queryAppend;
						$copyQuery	 = $qpcopyStr	. $queryAppend;
						$changeQuery = $qpchangeStr . $queryAppend;
						$deleteQuery = $qpdeleteStr . $queryAppend;
						$viewTitle	 = htmlspecialchars($this->labels['View']);
						$changeTitle = htmlspecialchars($this->labels['Change']);
						$copyTitle	 = htmlspecialchars($this->labels['Copy']);
						$deleteTitle = htmlspecialchars($this->labels['Delete']);
					}
					if ($this->nav_graphic_links()) {
						$imgstyle =
							'background:url('.$this->url['images'].'%s) no-repeat;'.
 							'width:16px;height:15px;border:0;font-size:0';
						/* We need the information about the current record, we append it to
						 * the translated operation. Ugly, but still much cleaner than
						 * textlinks.
						 */
						echo '<div class="'.$css_class_name.' graphic-links">';
						$navButtons = array();
						if ($this->view_nav_displayed()) {
							$navButtons[] = $this->htmlSubmit(
								'operation',
								$viewTitle.'?'.$recordQueryData.'&'.$mrecRecordQueryData,
								$this->getCSSclass('view-navigation'),
								$this->view_enabled() == false,
								sprintf($imgstyle, 'pme-view.png'));
						}
						if ($this->change_nav_displayed()) {
							$navButtons[] = $this->htmlSubmit(
								'operation',
								$changeTitle.'?'.$recordQueryData.'&'.$mrecRecordQueryData,
								$this->getCSSclass('change-navigation'),
								$this->change_enabled() == false,
								sprintf($imgstyle, 'pme-change.png'));
						}
						if ($this->copy_nav_displayed()) {
							$navButtons[] = $this->htmlSubmit(
								'operation',
								$copyTitle.'?'.$recordQueryData.'&'.$mrecRecordQueryData,
								$this->getCSSclass('copy-navigation'),
								$this->copy_enabled() == false,
								sprintf($imgstyle, 'pme-copy.png'));
						}
						if ($this->delete_nav_displayed()) {
							$navButtons[] =$this->htmlSubmit(
								'operation',
								$deleteTitle.'?'.$recordQueryData.'&'.$mrecRecordQueryData,
								$this->getCSSclass('delete-navigation'),
								$this->delete_enabled() == false,
								sprintf($imgstyle, 'pme-delete.png'));
						}
						echo implode('&nbsp;', $navButtons);
						$printed = true;
						echo '</div>';
					}
					if ($this->nav_text_links()) {
						if ($this->nav_graphic_links()) {
							echo '<br class="',$css_class_name,'">';
						}
						$printed_out = false;
						if ($this->view_enabled()) {
							$printed_out = true;
							echo '<a href="',$viewQuery,'" title="',$viewTitle,'" class="',$css_class_name,'">V</a>';
						}
						if ($this->change_enabled()) {
							$printed_out && print('&nbsp;');
							$printed_out = true;
							echo '<a href="',$changeQuery,'" title="',$changeTitle,'" class="',$css_class_name,'">C</a>';
						}
						if ($this->copy_enabled()) {
							$printed_out && print('&nbsp;');
							$printed_out = true;
							echo '<a href="',$copyQuery,'" title="',$copyTitle,'" class="',$css_class_name,'">P</a>';
						}
						if ($this->delete_enabled()) {
							$printed_out && print('&nbsp;');
							$printed_out = true;
							echo '<a href="',$deleteQuery,'" title="',$deleteTitle,'" class="',$css_class_name,'">D</a>';
						}
					}
					if (! $this->nav_buttons() || $this->sys_cols > 1) {
						echo '</td>',"\n";
					}
					if ($this->nav_buttons()) {
						echo '<td class="',$css_class_name,'"><input class="',$css_class_name;
						echo '" type="radio" name="'.$this->cgi['prefix']['sys'].'rec';
						echo '" value="',htmlspecialchars($recordData),'"';
						if ((empty($this->rec) && $first) || ($this->rec == $key_rec)) {
							echo ' checked';
							$first = false;
						}
						echo ' /></td>',"\n";
					}
					if ($this->nav_custom_multi()) {
						$css	  = $this->getCSSclass($this->misccss.'-check', null, null, $this->misccss2);
						$misccss  = $this->getCSSclass('misc');
						$namebase = $this->cgi['prefix']['sys'].'mrecs';
						$name	  = $namebase.'[]';
						$ttip	  = $this->fetchToolTip($css, $name);

						echo '<td class="'.$css_class_name.' '.$misccss.'">'
							.'<label class="'.$css
							.'" for="'.$namebase.'-'.htmlspecialchars($mrecRecordData)
							.'" '.$ttip.'>'
							.'<input class="'.$css
							.'" '.$ttip
							.'id="'.$namebase.'-'.htmlspecialchars($mrecRecordData)
							.'" type="checkbox" name="'.$namebase.'[]'
							.'" value="',htmlspecialchars($mrecRecordData),'"';
						// Set all members of $this->mrecs as checked, or add the current file
						// result
						$mrecs_key = array_search($mrecRecordData, $this->mrecs);
						if (($this->operation != '-' && $mrecs_key !== false)
							||
							($this->operation == '+')) {
							echo ' checked';
						}
						// if ($this->operation == '-') {
						// 	// Remove, remember all others
						// 	unset($this->mrecs[$mrecs_key]);
						// }

						echo ' /><div class="'.$this->getCSSclass($this->misccss.'-check', null, null, $this->misccss2).'"></div></label></td>'."\n";

						// remove all displayed misc records as these
						// are handled by the check-boxes.
						while (($mrecs_key = array_search($mrecRecordData, $this->mrecs)) !== false) {
							unset($this->mrecs[$mrecs_key]);
						}
					}
				} elseif ($this->sys_cols /* $this->filter_enabled() */) {
					echo '<td class="',$css_class_name,'" colspan="',$this->sys_cols,'">&nbsp;</td>',"\n";
				}
			} /* }}} */
			for ($k = 0; $k < $this->num_fds; $k++) { /* {{{ */
				$fd = $this->fds[$k];
				if (! $this->displayed[$k] || $this->hidden($k)) {
					continue;
				}
				$css_postfix	= @$this->fdd[$k]['css']['postfix'];
				$css_class_name = $this->getCSSclass('cell', null, true, $css_postfix);
				$cell_data = $this->cellDisplay($k, $row, $css_class_name);
				$title = '';
				$helptip = $this->fetchCellPopup($k, $row, $cell_data);
				if (!empty($helptip)) {
					$title = ' title="'.$this->enc($helptip).'"';
				}
				echo '<td class="',$css_class_name,'"',$this->getColAttributes($fd),' ';
				echo $this->getColAlign($fd),$title,'>';
				if (isset($this->fdd[$k]['display']['prefix'])) {
					$prefix = $this->fdd[$k]['display']['prefix'];
					if (is_callable($prefix)) {
						echo call_user_func($prefix, 'display', 'prefix', $row, $k, $this);
					} else {
						echo $this->fdd[$k]['display']['prefix'];
					}
				}
				echo $cell_data;
				if (isset($this->fdd[$k]['display']['postfix'])) {
					$postfix = $this->fdd[$k]['display']['postfix'];
					if (is_callable($postfix)) {
						echo call_user_func($postfix, 'display', 'postfix', $row, $k, $this);
					} else {
						echo $this->fdd[$k]['display']['postfix'];
					}
				}
				echo '</td>',"\n";
			} /* }}} */
			echo '</tr>',"\n";
		}

		/*
		 * Display and accumulate column aggregation info, do totalling query
		 * XXX this feature does not work yet!!!
		 */
		// aggregates listing (if any)
		if (false && $$var_to_total) {
			// do the aggregate query if necessary
			//if ($vars_to_total) {
			$qp = array();
			$qp[self::QPARTS_TYPE] = self::QPARTS_SELECT;
			$qp[self::QPARTS_SELECT] = $aggr_from_clause;
			$qp[self::QPARTS_FROM]	  = @$this->get_SQL_join_clause();
			$qp[self::QPARTS_WHERE]  = @$this->get_SQL_where_from_query_opts();
			$tot_query	  = @$this->get_SQL_query($qp);
			$totals_result = $this->myquery($tot_query,__LINE__);
			$tot_row	   = $this->sql_fetch($totals_result);
			//}
			$qp_aggr = $qp;
			echo "\n",'<tr class="TODO-class">',"\n",'<td class="TODO-class">&nbsp;</td>',"\n";
			/*
			  echo '<td>';
			  echo printarray($qp_aggr);
			  echo printarray($vars_to_total);
			  echo '</td>';
			  echo '<td colspan="'.($this->num_fds-1).'">'.$var_to_total.' '.$$var_to_total.'</td>';
			*/
			// display the results
			for ($k=0;$k<$this->num_fds;$k++) {
				$fd = $this->fds[$k];
				if (stristr($this->fdd[$fd]['options'],'L') or !isset($this->fdd[$fd]['options'])) {
					echo '<td>';
					$aggr_var  = 'qf'.$k.'_aggr';
					$$aggr_var = $this->get_sys_cgi_var($aggr_var);
					if ($$aggr_var) {
						echo $this->sql_aggrs[$$aggr_var],': ',$tot_row[$aggr_var];
					} else {
						echo '&nbsp;';
					}
					echo '</td>',"\n";
				}
			}
			echo '</tr>',"\n";
		}
		echo '</tbody></table>',"\n"; // end of table rows listing
		$this->display_list_table_buttons('down');
		// Finally add some more hidden stuff ...
		if ($this->misc_enabled()) {
			$this->emit_misc_recs();
		}
		$this->form_end();
	} /* }}} */

	function display_record() /* {{{ */
	{
		$postfix = $this->css['postfix'];
		$formCssClass = $this->getCSSclass('list', null, null, $postfix);

		// PRE Triggers
		$trigger = '';
		if ($this->change_operation()) {
			$trigger = self::SQL_QUERY_UPDATE;
			$formCssClass = $this->getCSSclass('change', null, null, $postfix);
			if (!$this->exec_triggers_simple($trigger, self::TRIGGER_PRE)) {
				// if PRE update fails, then back to view operation
				// @TODO: why? Just emit an error?
				$this->operation = $this->labels['View'];

				// recurse in order to restart the logic.
				return $this->display_record();
			}
		} else {
			if ($this->add_operation() || $this->copy_operation()) {
				$formCssClass = $this->getCSSclass('copyadd', null, null, $postfix);
				$trigger = self::SQL_QUERY_INSERT;
			}
			if ($this->view_operation()) {
				$formCssClass = $this->getCSSclass('view', null, null, $postfix);
				$trigger = self::SQL_QUERY_SELECT;
			}
			if ($this->delete_operation()) {
				$formCssClass = $this->getCSSclass('delete', null, null, $postfix);
				$trigger = self::SQL_QUERY_DELETE;
			}
			$ret = $this->exec_triggers_simple($trigger, self::TRIGGER_PRE);
			// if PRE insert/view/delete fail, then back to the list
			if ($ret == false) {
				// @TODO: this may be located in another window
				$this->operation = '';
				$this->list_table();
				return;
			}
		}

		$row = false;
		if (!$this->add_operation()) {
			$qparts[self::QPARTS_TYPE]	  = self::QPARTS_SELECT;
			$qparts[self::QPARTS_SELECT] = @$this->get_SQL_column_list();
			$qparts[self::QPARTS_FROM]	  = @$this->get_SQL_join_clause();
			$qparts[self::QPARTS_WHERE] = $this->key_record_where();
			//$qparts[self::QPARTS_GROUPBY] = $this->get_SQL_groupby_query_opts();
			//$qparts[self::QPARTS_HAVING] = $this->get_SQL_having_query_opts();

			$res = $this->myquery($this->get_SQL_query($qparts),__LINE__);
			if (! ($row = $this->sql_fetch($res))) {
				$row = false;
			}
			$this->exec_data_triggers($trigger, $row);
		}

		/* echo '<PRE>'; */
		/* $this->print_vars(); */
		/* echo '</PRE>'; */
		$this->form_begin($formCssClass);
		if ($this->cgi['persist'] != '') {
			echo $this->get_origvars_html($this->cgi['persist']);
		}
		// Finally add some more hidden stuff ...
		if ($this->misc_enabled()) {
			$this->emit_misc_recs();
		}
		// Also transport the query options ...
		for ($k = 0; $k < $this->num_fds; $k++) {
			foreach (array('','_idx','_comp') as $suf) {
				$qf = $this->get_sys_cgi_var('qf'.$k.$suf);
				if (isset($qf) && $qf != '') {
					if (is_array($qf) ) {
						foreach($qf as $key => $value) {
							echo $this->htmlHiddenSys('qf'.$k.$suf.'['.$key.']', $value);
						}
					} else {
						echo $this->htmlHiddenSys('qf'.$k.$suf, $qf);
					}
				}
			}
		}
		echo $this->get_origvars_html($this->get_sfn_cgi_vars());
		echo $this->get_origvars_html($this->qfn);
		echo $this->htmlHiddenSys('cur_tab', $this->cur_tab);
		echo $this->htmlHiddenSys('qfn', $this->qfn);
		if ($this->copy_operation() || empty($this->rec)) {
			echo $this->htmlHiddenSys('rec', '');
		} else if (count($this->rec) == 1) {
			echo $this->htmlHiddenSys('rec', array_values($this->rec)[0]);
		} else {
			foreach ($this->rec as $key => $value) {
				echo $this->htmlHiddenSys('rec['.$key.']', $value);
			}
		}
		if (!empty($this->groupby_rec)) {
			foreach ($this->groupby_rec as $key => $value) {
				echo $this->htmlHiddenSys('groupby_rec['.$key.']', $value);
			}
		}
		echo $this->htmlHiddenSys('fm', $this->fm);
		echo $this->htmlHiddenSys('np', $this->inc);
		echo $this->htmlHiddenSys('translations', $this->translations);
		echo $this->htmlHiddenSys('fl', $this->fl);
		echo $this->htmlHiddenSys('op_name', $this->operationName());
		$this->display_record_buttons('up');

		if ($this->tabs_enabled()) {
			$tab_class = $this->cur_tab < 0 ? ' tab-all' : ' tab-'.$this->cur_tab;
		} else {
			$tab_class = '';
		}

		echo '<table class="',$this->getCSSclass('main'),$tab_class,'" summary="',$this->tb,'"><tbody>',"\n";
		if ($this->add_operation()) {
			$this->display_add_record();
		} else {
			$this->display_copy_change_delete_record($row);
		}
		echo '</tbody></table>',"\n";
		$this->display_record_buttons('down');

		$this->form_end();
	} /* }}} */

	/*
	 * Action functions
	 */

	function do_add_record() /* {{{ */
	{
		$key_col_val = [];
		$newvals	 = array();
		for ($k = 0; $k < $this->num_fds; $k++) {
			if ($this->processed($k)) {
				$fd = $this->fds[$k];
				if ($this->disabled($k)) {
					$fn = (string) @$this->fdd[$k]['default'];
				} else {
					$fn = $this->get_data_cgi_var($fd);
				}
				if ($this->col_has_datemask($k)) {
					$fn = trim($fn);
					if ($fn != '') {
						// Convert back to a date/time object understood by mySQL
						$stamps = strtotime($fn);
						$fn = date('Y-m-d H:i:s', $stamps);
						echo "<!-- ".$fn." -->\n";
					}
				}
				if  ($this->col_has_checkboxes($k) ||
					 ($this->col_has_radio_buttons($k) && $this->col_has_multiple_select($k))) {
					if  (empty($fn)) {
						$fn = @$this->fdd[$k]['default'];
					}
				}
				foreach (array_keys($this->key) as $key) {
					if ($fd == $key) {
						$key_col_val[$key] = $fn;
					}
				}
				if (is_array($fn) && self::is_flat($fn)) {
					$newvals[$fd] = join(',',$fn);
				} else {
					$newvals[$fd] = $fn;
				}
			}
		}
		// Creating array of changed keys ($changed)
		$changed = array_keys($newvals);
		// Before trigger, newvals can be efectively changed
		if ($this->exec_triggers(self::SQL_QUERY_INSERT, self::TRIGGER_BEFORE, $oldvals, $changed, $newvals) == false) {
			return false;
		}

		// the trigger may have changed the key-values (e.g. by inserting the row itself)
		foreach (array_keys($this->key) as $key) {
			if ($newvals[$key] != $key_col_val[$key]) {
				$key_col_val[$key] = $newvals[$key];
			}
		}

		// Real query (no additional query in this method)
		$query = ''; // query_groups not supported, would be difficult
		foreach ($changed as $fd) {
			if ($fd == '') continue;
			$val = $newvals[$fd];
			$fdn = $this->fdn[$fd];
			$fdd = $this->fdd[$fdn];
			if ($this->skipped($fdn)) {
				continue;
			}
			if (is_array($val)) {
				// if the triggers still left the stuff as array, try to do something useful.
				$val = self::is_flat($val) ? join(',', $val) : json_encode($val);
			}
			if (false) {
				// query_groups not supported, would be difficult
				if (isset($fdd['querygroup'])) {
					// Split update query if requested by calling app
					$query_group = $fdd['querygroup'];
					if (!isset($query_groups[$query_group])) {
						$query_groups[$query_group] = '';
					}
				} else {
					$query_group = 'default';
				}
				$query = &$query_groups[$query_group];
			}

			if (!empty($fdd['encryption'])) {
				// encrypt the value
				$val = call_user_func($this->fdd[$this->fdn[$fd]]['encryption']['encrypt'], $val);
			}
			if ($this->col_has_sqlw($fdn)) {
				$val_as	 = addslashes($val);
				$val_qas = '"'.addslashes($val).'"';
				$value = $this->substituteVars(
					$fdd['sqlw'], array(
						'val_qas' => $val_qas,
						'val_as'  => $val_as,
						'val'	  => $val
						));
			} else {
				$value = "'".addslashes($val)."'";
			}
			if ($query == '') {
				$query = 'INSERT INTO '.$this->sd.$this->tb.$this->ed.' ('.$this->sd.$fd.$this->ed.''; // )
				$query2 = ') VALUES ('.$value.'';
			} else {
				$query	.= ', '.$this->sd.$fd.$this->ed.'';
				$query2 .= ', '.$value.'';
			}
		}
		if (!empty($query)) {
			$query .= $query2.')';
			$res	= $this->myquery($query, __LINE__);
			$this->message = $this->sql_affected_rows().' '.$this->labels['record added'];
			if (! $res) {
				return false;
			}
			$rec = $this->sql_insert_id();
		} else {
			$rec = -1;
		}
		if ($rec > 0 && count($this->key) == 1) {
			$this->rec = [ array_keys($this->key)[0] => $rec ];
		} else if (count($key_col_val) == count($this->key)) {
			$this->rec = $key_col_val;
		}
		// Notify list
		if (@$this->notify[self::SQL_QUERY_INSERT] || @$this->notify['all']) {
			$this->email_notify(false, $newvals);
		}
		// Note change in log table
		if ($this->logtable) {
			$this->logQuery(self::SQL_QUERY_INSERT, $oldvals, $changed, $newvals);
		}
		// After trigger
		$changed = array_keys($newvals); // rebuild if reset by previous triggers.
		if ($this->exec_triggers(self::SQL_QUERY_INSERT, self::TRIGGER_AFTER, $oldvals, $changed, $newvals) == false) {
			return false;
		}
		return true;
	} /* }}} */

	function do_change_record() /* {{{ */
	{
		// Preparing queries
		$where_part = " WHERE ".$this->key_record_where();
		$query_groups = array($this->tb => '');
		$where_groups = array($this->tb => $where_part);
		$query_oldrec = '';
		$newvals	  = array();
		$oldvals	  = array();
		$changed	  = array();
		$stamps		  = array();
		$checks       = array();
		$defaults     = array();
		// Prepare query to retrieve oldvals
		for ($k = 0; $k < $this->num_fds; $k++) {
			if ($this->processed($k)) {
				$fd = $this->fds[$k];
				$fn = $this->get_data_cgi_var($fd);
				//error_log(__METHOD__.' got value for '.$fd.': '.print_r($fn, true).' post '.print_r($_POST[$fd], true));
				if ($this->col_has_datemask($k)) {
					if ($fn == '') {
						$stamps[$fd] = false;
					} else {
						// Convert back to a date/time object understood by mySQL
						$stamps[$fd] = strtotime($fn);
						$fn = date('Y-m-d H:i:s', $stamps[$fd]);
						echo "<!-- ".$fn." -->\n";
					}
				}
				if ($this->col_has_checkboxes($k) ||
					($this->col_has_radio_buttons($k) && $this->col_has_multiple_select($k))) {
					$checks[$fd] = true;
					$defaults[$fd] = @$this->fdd[$k]['default'];
					//error_log('checkbox: '.$fd.' value '.$fn);
				}
				// Don't include disabled fields into newvals, but
				// keep for reference in oldvals. Keep readonly-fields in newvals
				if (!$this->disabled($k) || $this->readonly($k)) {
					// leave complictated arrays to the trigger hooks.
					if (is_array($fn) && self::is_flat($fn)) {
						$newvals[$fd] = join(',', $fn);
					} else {
						$newvals[$fd] = $fn;
					}
				}
				$query_part = $this->sql_field($k)." AS '".$fd."'";
				if ($query_oldrec == '') {
					$query_oldrec = 'SELECT '.$query_part;
				} else {
					$query_oldrec .= ','.$query_part;
				}
			}
		}
		//$query_newrec  = $query_oldrec.' FROM ' . $this->tb;
		$joinTables = $this->get_SQL_join_clause();
		$query_newrec  = $query_oldrec.' FROM ' . $joinTables;
		//$query_oldrec .= ' FROM ' . $this->sd.$this->tb.$this->ed . $where_part;
		$query_oldrec .= ' FROM ' . $joinTables . $where_part;
		// Additional query (must go before real query)
		//error_log('old query '.$query_oldrec);
		$res	 = $this->myquery($query_oldrec, __LINE__);
		$oldvals = $this->sql_fetch($res);
		$this->sql_free_result($res);

		// Creating array of changed keys ($changed)
		foreach ($newvals as $fd => $value) {
			echo "<!-- ".$value." ".$oldvals[$fd]." -->\n";
			if (isset($stamps[$fd])) {
				$oldstamp = $oldvals[$fd] != "" ? strtotime($oldvals[$fd]) : false;
				//$this->logInfo($fd." Stamp: '".$stamps[$fd]."' old Stamp: '".$oldstamp."' oldvals: '".$oldvals[$fd]."' value '".$value."'");
				if ($oldstamp != $stamps[$fd]) {
					//$this->logInfo('Changed '.$fd.' "'.$oldstamps.'" "'.$stamps[$fd].'"');
					$changed[] = $fd;
				} else {
					$oldvals[$fd] = $value; // force equal, no reason to change.
				}
			} else if (!empty($checks[$fd]) && empty($value)) {
				if (intval($value) !== intval($oldvals[$fd])) {
					// checkboxes, empty means unchecked, but the DB
					// may as well store nothing or 0.
					// $this->logInfo('Changed check '.$fd.' "'.intval($oldvals[$fd]).'" "'.intval($value));
					$changed[] = $fd;
					$newvals[$fd] = $defaults[$fd];
				}
			} else if ($value != $oldvals[$fd]) {
				//error_log('Changed '.$fd.' "'.$oldvals[$fd].'" "'.$value.'"');
				//error_log($fd.' old: '.$oldvals[$fd].' '.$value);
				$fdn = $this->fdn[$fd]; // $fdn == field number
				if ($this->col_has_multiple($k) && !$this->skipped($fdn)) {
					$tmpval1 = explode(',',$value);
					sort($tmpval1);
					$tmpval2 = explode(',',$oldvals[$fd]);
					sort($tmpval2);
					if ($tmpval1 != $tmpval2) {
						$changed[] = $fd;
					} else {
						$newvals[$fd] = $oldvals[$fd]; // fake
					}
				} else {
					$changed[] = $fd;
				}
			}
		}


		// Before trigger
		if ($this->exec_triggers(self::SQL_QUERY_UPDATE, self::TRIGGER_BEFORE, $oldvals, $changed, $newvals) === false) {
			return false;
		}

		// Creatinng WHERE part for query groups, after the trigger as
		// it may even have added things.
		foreach($oldvals as $fd => $value) {
			//error_log('new '.$fd.' '.$value.' '.print_r($newvals, true));
			$fdn = $this->fdn[$fd]; // $fdn == field number
			$fdd = $this->fdd[$fdn];
			if (isset($fdd['querygroup'])) {
				$queryGroup = $fdd['querygroup'];
				if ($queryGroup['key']) {
					$table = $queryGroup['table'];
					$tablename = $queryGroup['tablename'];
					$key = $queryGroup['column'];
					$rec = $value;
					$where_groups[$tablename] =
											  ' WHERE ('.self::MAIN_ALIAS.'.'.$this->sd.$key.$this->ed.'='.$this->key_delim.$rec.$this->key_delim.')';
				}
			}
		}

		// Build the real query respecting changes to the newvals array
		//foreach ($newvals as $fd => $val) {
		foreach($changed as $fd) {
			if ($fd == '') continue;
			$fdn = $this->fdn[$fd];
			if ($this->skipped($fdn) ||
				(!$this->col_has_datemask($fdn) && $this->readonly($fdn))) {
				// we allow update of read-only timestamps. RO here
				// just means: not settable by the user.
				continue;
			}
			//error_log($fd.' old: '.$oldvals[$fd].' new: '.$newvals[$fd]);
			$fdd = $this->fdd[$fdn];
			$table = '';
			$tablename = '';
			$val = $newvals[$fd];
			if (is_array($val)) {
				// if the triggers still left the stuff as array, try to do something useful.
				$val = self::is_flat($val) ? join(',', $val) : json_encode($val);
			}
			if (isset($fdd['querygroup'])) {
				// Split update query if requested by calling app
				$table = $fdd['querygroup']['table'];
				$tablename = $fdd['querygroup']['tablename'];
				$column = $fdd['querygroup']['column'];
				if (!isset($query_groups[$tablename])) {
					$query_groups[$tablename] = '';
				}
			} else {
				$tablename = $table = $this->tb;
				$column = $fd;
			}
			$query_real = &$query_groups[$tablename];
			if (!empty($fdd['encryption'])) {
				// encrypt the value
				$val = call_user_func($fdd['encryption']['encrypt'], $val);
			}
			if ($this->col_has_sqlw($fdn)) {
				$val_as	 = addslashes($val);
				$val_qas = '"'.addslashes($val).'"';
				$value = $this->substituteVars(
					$fdd['sqlw'], array(
						'val_qas' => $val_qas,
						'val_as'  => $val_as,
						'val'	  => $val
						));
				//error_log($fdd['sqlw']);
			} else if (isset($stamps[$fd]) && $val == '') {
				$value = 'NULL';
			} else {
				$value = "'".addslashes($val)."'";
			}
			if ($query_real == '') {
				$query_real	  = 'UPDATE '.$this->sd.$table.$this->ed.' AS '.$this->sd.self::MAIN_ALIAS.$this->ed.'
  SET '.$this->sd.$column.$this->ed.'='.$value;
			} else {
				$query_real	  .= ','.$this->sd.$column.$this->ed.'='.$value;
			}
		}
		$affected_rows = 0;
		$updateResult = true;
		foreach ($query_groups as $tablename => $query) {
			if ($query === '') {
				continue;
			}
			//error_log('name: '.$tablename.' query '.$query);
			$query .= $where_groups[$tablename];
			// Real query
			$res = $this->myquery($query, __LINE__);
			if ($res !== false) {
				$num_rows = $this->sql_affected_rows();
				$affected_rows = max($num_rows, $affected_rows);
			}
			$updateResult = $updateRestult && $res;
		}
		if ($affected_rows == 1) {
			$this->message = $affected_rows.' '.$this->labels['record changed'];
		} else {
			$this->message = $affected_rows.' '.$this->labels['records changed'];
		}

		// Another additional query (must go after real query). This
		// also really determines the changed records, in case only
		// some of the query-groups have failed.
		foreach (array_keys($this->key) as $key) {
			if (in_array($key, $changed)) {
				$this->rec[$key] = $newvals[$key]; // key has changed
				//error_log('changed '.print_r($changed, true));
			}
		}
		$query_newrec .= ' WHERE '.$this->key_record_where();
		$res	 = $this->myquery($query_newrec, __LINE__);
		if ($res === false) {
			$this->logError('Could not query new records');
			return false;
		}
		$newvals = $this->sql_fetch($res);
		$this->sql_free_result($res);
		// Creating array of changed keys ($changed)
		$changed = array();
		foreach ($newvals as $fd => $value) {
			$k = $this->fdn[$fd];
			if ($this->col_has_datemask($k)) {
				if (strtotime($value) != strtotime($oldvals[$fd])) {
					$changed[] = $fd;
				}
			} else if ($value != $oldvals[$fd]) {
				$changed[] = $fd;
			}
		}
		// Notify list
		if (@$this->notify[self::SQL_QUERY_UPDATE] || @$this->notify['all']) {
			if (count($changed) > 0) {
				$this->email_notify($oldvals, $newvals);
			}
		}
		// Note change in log table
		if ($this->logtable) {
			$this->logQuery(self::SQL_QUERY_UPDATE, $oldvals, $changed, $newvals);
		}
		// After trigger
		if ($this->exec_triggers(self::SQL_QUERY_UPDATE, self::TRIGGER_AFTER, $oldvals, $changed, $newvals) == false) {
			return false;
		}
		return $updateResult; // return error or success status of the update query
	} /* }}} */

	function do_delete_record() /* {{{ */
	{
		// Additional query
		$query	 = 'SELECT * FROM '.$this->sd.$this->tb.$this->ed
				 .' AS '.$this->sd.self::MAIN_ALIAS.$this->ed
			.' WHERE '.$this->key_record_where();
		$res	 = $this->myquery($query, __LINE__);
		$oldvals = $this->sql_fetch($res);
		$this->sql_free_result($res);
		// Creating array of changed keys ($changed)
		// how the heck could this be empty?
		$changed = is_array($oldvals) ? array_keys($oldvals) : array();
		$newvals = array();
		// Before trigger
		if ($this->exec_triggers(self::SQL_QUERY_DELETE, self::TRIGGER_BEFORE, $oldvals, $changed, $newvals) == false) {
			return false;
		}
		if (!empty($changed)) {
			// Real query
			$query = 'DELETE '.self::MAIN_ALIAS.' FROM '.$this->tb.' '.self::MAIN_ALIAS.' WHERE '.$this->key_record_where();
			$res = $this->myquery($query, __LINE__);
			$this->message = $this->sql_affected_rows().' '.$this->labels['record deleted'];
			if (! $res) {
				return false;
			}
		}

		// remove deleted record from misc selection
		$recordData =
			(count($this->rec) == 1)
			? array_values($this->rec)[0]
			: $recordData = json_encode($this->rec);
		while (($mrecs_key = array_search($recordData, $this->mrecs)) !== false) {
			unset($this->mrecs[$mrecs_key]);
		}

		// Notify list
		if (@$this->notify[self::SQL_QUERY_DELETE] || @$this->notify['all']) {
			$this->email_notify($oldvals, false);
		}
		// Note change in log table
		if ($this->logtable) {
			$this->logQuery(self::SQL_QUERY_DELETE, $oldvals, $changed, null);
		}
		// After trigger
		if ($this->exec_triggers(self::SQL_QUERY_DELETE, self::TRIGGER_AFTER, $oldvals, $changed, $newvals) == false) {
			return false;
		}
		return true;
	} /* }}} */

	function email_notify($old_vals, $new_vals) /* {{{ */
	{
		if (! function_exists('mail')) {
			return false;
		}
		if ($old_vals != false && $new_vals != false) {
			$action	 = self::SQL_QUERY_UPDATE;
			$subject = 'Record updated in';
			$kparts = [];
			foreach ($this->rec as $key => $rec) {
				$delim = $this->key_delim[$key];
				$kparts[] = $this->fdd[$this->key]['name'].' = '.$this->key_delim.$this->rec.$this->key_delim;
			}
			$body	 = 'An item with '.implode(', ', $kparts).' was updated in';
			$vals	 = $new_vals;
		} elseif ($new_vals != false) {
			$action	 = self::SQL_QUERY_INSERT;
			$subject = 'Record added to';
			$body	 = 'A new item was added into';
			$vals	 = $new_vals;
		} elseif ($old_vals != false) {
			$action	 = self::SQL_QUERY_DELETE;
			$subject = 'Record deleted from';
			$body	 = 'An item was deleted from';
			$vals	 = $old_vals;
		} else {
			return false;
		}
		$addr  = $this->get_server_var('REMOTE_ADDR');
		$user  = $this->get_server_var('REMOTE_USER');
		$body  = 'This notification e-mail was automatically generated by phpMyEdit.'."\n\n".$body;
		$body .= ' table '.$this->tb.' in SQL database '.$this->db.' on '.$this->page_name;
		$body .= ' by '.($user == '' ? 'unknown user' : "user $user").' from '.$addr;
		$body .= ' at '.date('d/M/Y H:i').' with the following fields:'."\n\n";
		$i = 1;
		foreach ($vals as $k => $text) {
			$name = isset($this->fdd[$k]['name~'])
				? $this->fdd[$k]['name~'] : $this->fdd[$k]['name'];
			if ($action == self::SQL_QUERY_UPDATE) {
				if ($old_vals[$k] == $new_vals[$k]) {
					continue;
				}
				$body .= sprintf("[%02s] %s (%s)\n		WAS: %s\n	   IS:	%s\n",
								 $i, $name, $k, $old_vals[$k], $new_vals[$k]);
			} else {
				$body .= sprintf('[%02s] %s (%s): %s'."\n", $i, $name, $k, $text);
			}
			$i++;
		}
		$body	 .= "\n--\r\n"; // \r is needed for signature separating
		$body	 .= "phpMyEdit\ninstant SQL table editor and code generator\n";
		$body	 .= "http://platon.sk/projects/phpMyEdit/\n\n";
		$subject  = @$this->notify['prefix'].$subject.' '.$this->dbp.$this->tb;
		$subject  = trim($subject); // just for sure
		$wrap_w	  = intval(@$this->notify['wrap']);
		$wrap_w > 0 || $wrap_w = 72;
		$from	  = (string) @$this->notify['from'];
		$from != '' || $from = 'webmaster@'.strtolower($this->get_server_var('SERVER_NAME'));
		$headers  = 'From: '.$from."\n".'X-Mailer: PHP/'.phpversion().' (phpMyEdit)';
		$body	  = wordwrap($body, $wrap_w, "\n", 1);
		$emails	  = (array) $this->notify[$action] + (array) $this->notify['all'];
		foreach ($emails as $email) {
			if (! empty($email)) {
				mail(trim($email), $subject, $body, $headers);
			}
		}
		return true;
	} /* }}} */

	/*
	 * A callback called after data has been fetched, but before any
	 * HTML has been generated.
	 */
	function exec_data_triggers($op, &$row)
	{
		$step = 'data';
		if (!isset($this->triggers[$op][$step])) {
			return true;
		}
		$trig = $this->triggers[$op][$step];
		if (is_array($trig)) {
			ksort($trig);
			for ($ret = true, $t = reset($trig); $t !== false && $ret != false; $t = next($trig)) {
				if (is_callable($t)) {
					$ret = call_user_func_array($t, array(&$this, $op, $step, &$row));
				} else {
					$ret = include($t);
				}
			}
		} else {
			if (is_callable($trig)) {
				call_user_func_array($trig, array(&$this, $op, $step, &$row));
			} else {
				include($trig);
			}
		}
	} /* }}} */

	/*
	 * Apply triggers function
	 * Run a (set of) trigger(s). $trigger can be an Array or a filename
	 * Break and return false as soon as a trigger return false
	 * we need a reference on $newvals to be able to change value before insert/update
	 */
	function exec_triggers($op, $step, &$oldvals, &$changed, &$newvals) /* {{{ */
	{
		if (!empty($this->triggers[$op][$step])) {
			$trig = $this->triggers[$op][$step];
		} else if (!empty($this->triggers['*'][$step])) {
			$trig = $this->triggers['*'][$step];
		}
		if (empty($trig)) {
			return true;
		}
		$ret  = true;
		if (!is_array($trig) && !is_callable($trig)) {
			$trig = array($trig);
		}
		ksort($trig);
		for ($t = reset($trig); $t !== false && $ret != false; $t = next($trig)) {
			if (is_callable($t)) {
				$ret = call_user_func_array($t,
											array(&$this,
												  $op, $step, &$oldvals,
												  &$changed, &$newvals));
			} else {
				$ret = include($t);
			}
		}

		$changed = array_unique($changed);
		//echo "<PRE>".$this->options."</PRE>";

		return $ret;
	} /* }}} */

	function exec_triggers_simple($op, $step) /* {{{ */
	{
		$oldvals = $newvals = $changed = array();
		return $this->exec_triggers($op, $step, $oldvals, $changed, $newvals);
	} /* }}} */

	/*
	 * Recreate functions
	 */
	function recreate_fdd($default_page_type = 'L') /* {{{ */
	{
		// TODO: one level deeper browsing
		$this->page_type = $default_page_type;
		$this->filter_operation() && $this->page_type = 'F';
		$this->view_operation()	  && $this->page_type = 'V';
		if ($this->add_operation()
			|| $this->label_cmp($this->saveadd, 'Save')
			|| $this->label_cmp($this->applyadd, 'Apply')
			|| $this->label_cmp($this->moreadd, 'More')) {
			$this->page_type = 'A';
		}
		if ($this->change_operation()
			|| $this->label_cmp($this->savechange, 'Save')
			|| $this->label_cmp($this->morechange, 'Apply')) {
			$this->page_type = 'C';
		}
		if ($this->copy_operation()
			|| $this->label_cmp($this->savecopy, 'Save')
			|| $this->label_cmp($this->applycopy, 'Apply')) {
			$this->page_type = 'P';
		}
		if ($this->delete_operation()
			|| $this->label_cmp($this->savedelete, 'Delete')) {
			$this->page_type = 'D';
		}
		// Restore backups (if exists)
		foreach (array_keys($this->fdd) as $column) {
			foreach (array_keys($this->fdd[$column]) as $col_option) {
				if ($col_option[strlen($col_option) - 1] != '~')
					continue;

				$this->fdd[$column][substr($col_option, 0, strlen($col_option) - 1)]
					= $this->fdd[$column][$col_option];
				unset($this->fdd[$column][$col_option]);
			}
		}
		foreach (array_keys($this->fdd) as $column) {
			foreach (array_keys($this->fdd[$column]) as $col_option) {
				if (! strchr($col_option, '|')) {
					continue;
				}
				$col_ar = explode('|', $col_option, 2);
				if (! stristr($col_ar[1], $this->page_type)) {
					continue;
				}
				// Make field backups
				if (isset($this->fdd[$column][$col_ar[0]])) {
					$this->fdd[$column][$col_ar[0] .'~'] = $this->fdd[$column][$col_ar[0]];
				} else {
					$this->fdd[$column][$col_ar[0] .'~'] = null;
				}
				$this->fdd[$column][$col_option.'~'] = $this->fdd[$column][$col_option];
				// Set particular field
				$this->fdd[$column][$col_ar[0]] = $this->fdd[$column][$col_option];
				unset($this->fdd[$column][$col_option]);
			}
		}
	} /* }}} */

	function recreate_displayed() /* {{{ */
	{
		$field_num			  = 0;
		$num_fields_displayed = 0;
		$this->fds			  = array();
		$this->fdn			  = array();
		$this->displayed	  = array();
		$this->guidance		  = false;
		foreach (array_keys($this->fdd) as $key) {
			if (preg_match('/^\d+$/', $key)) { // skipping numeric keys
				continue;
			}
			$this->fds[$field_num] = $key;
			$this->fdn[$key] = $field_num;
			/* We must use here displayed() function, because displayed[] array
			   is not created yet. We will simultaneously create that array as well. */
			if ($this->displayed[$field_num] = $this->displayed($field_num)) {
				$num_fields_displayed++;
			}
			$ref = $this->join_table_reference($this->fdd[$key]);
			if ($ref !== false &&
				!isset($this->fdd[$key][self::FDD_VALUES]['join']['table'])) {
				$this->fdd[$key][self::FDD_VALUES]['table'] = $this->fdd[$ref][self::FDD_VALUES]['table'];
			}
			if (is_array(@$this->fdd[$key][self::FDD_VALUES]) &&
				empty($this->fdd[$key][self::FDD_VALUES]['table'])) {
				foreach ($this->fdd[$key][self::FDD_VALUES] as $val) {
					$this->fdd[$key]['values2'][$val] = $val;
				}
				unset($this->fdd[$key][self::FDD_VALUES]);
			}
			isset($this->fdd[$key]['help']) && $this->guidance = true;

			$this->fdd[$field_num] = $this->fdd[$key];
			$field_num++;
		}
		$this->num_fds				= $field_num;
		$this->num_fields_displayed = $num_fields_displayed;
		foreach (array_keys($this->key) as $key) {
			$this->key_num[$key] = $this->fdn[$key];
		}
		foreach ($this->groupby as $key) {
			$field_num = $this->fdn[$key];
			$this->groupby_num[$key] = $field_num;
			$this->fdd[$field_num]['options'] .= 'LF';
		}

		/* Adds first displayed column into sorting fields by replacing last
		   array entry. Also remove duplicite values and change column names to
		   their particular field numbers.

		   Note that entries like [0]=>'9' [1]=>'-9' are correct and they will
		   have desirable sorting behaviour. So there is no need to remove them.
		*/
		$this->sfn = array_unique($this->sfn);
		/*
		 * Well: unfortunately portions of this code treat
		 * this as an associative array. Actually, a problem
		 * which is built into PHP. Gnah.
		 */
		ksort($this->sfn, SORT_NUMERIC);
		$check_ar = array();
		foreach ($this->sfn as $key => $val) {
			if (preg_match('/^[-]?\d+$/', $val)) { // skipping numeric keys
				$val = abs($val);
				if (in_array($val, $check_ar) || $this->password($val)) {
					unset($this->sfn[$key]);
				} else {
					$check_ar[] = $val;
				}
				continue;
			}
			if ($val[0] == '-') {
				$val = substr($val, 1);
				$minus = '-';
			} else {
				$minus = '';
			}
			if (($val = array_search($val, $this->fds)) === false || $this->password($val)) {
				unset($this->sfn[$key]);
			} else {
				$val = intval($val);
				if (in_array($val, $check_ar)) {
					unset($this->sfn[$key]);
				} else {
					$this->sfn[$key] = $minus.$val;
					$check_ar[] = $val;
				}
			}
		}
		$this->sfn = array_unique($this->sfn);

		$this->dfltsfn = array_unique($this->dfltsfn);
		/*
		 * Well: unfortunately portions of this code treat
		 * this as an associative array. Actually, a problem
		 * which is built into PHP. Gnah.
		 */
		ksort($this->dfltsfn, SORT_NUMERIC);
		$check_ar = array();
		foreach ($this->dfltsfn as $key => $val) {
			if (preg_match('/^[-]?\d+$/', $val)) { // skipping numeric keys
				$val = abs($val);
				if (in_array($val, $check_ar) || $this->password($val)) {
					unset($this->dfltsfn[$key]);
				} else {
					$check_ar[] = $val;
				}
				continue;
			}
			if ($val[0] == '-') {
				$val = substr($val, 1);
				$minus = '-';
			} else {
				$minus = '';
			}
			if (($val = array_search($val, $this->fds)) === false || $this->password($val)) {
				unset($this->dfltsfn[$key]);
			} else {
				$val = intval($val);
				if (in_array($val, $check_ar)) {
					unset($this->dfltsfn[$key]);
				} else {
					$this->dfltsfn[$key] = $minus.$val;
					$check_ar[] = $val;
				}
			}
		}
		$this->dfltsfn = array_unique($this->dfltsfn);

		return true;
	} /* }}} */

	function backward_compatibility() /* {{{ */
	{
		foreach (array_keys($this->fdd) as $column) {
			// move ['required'] to ['js']['required']
			// if (! isset($this->fdd[$column]['js']['required']) && isset($this->fdd[$column]['required'])) {
			// 	$this->fdd[$column]['js']['required'] = $this->fdd[$column]['required'];
			// }
			// move 'HWR0' flags from ['options'] into ['input']
			if (isset($this->fdd[$column]['options'])) {
				$this->fdd[$column]['options'] = strtoupper($this->fdd[$column]['options']);
				if (!isset($this->fdd[$column]['input'])) {
					$this->fdd[$column]['input'] = '';
				}
				if (!empty($this->fdd[$column]['options'])) {
					strstr($this->fdd[$column]['options'], 'H') !== false && $this->fdd[$column]['input'] .= 'H';
					strstr($this->fdd[$column]['options'], 'W') !== false && $this->fdd[$column]['input'] .= 'W';
					strstr($this->fdd[$column]['options'], 'R') !== false && $this->fdd[$column]['input'] .= 'R';
					strstr($this->fdd[$column]['options'], '0') !== false && $this->fdd[$column]['input'] .= 'D';
					$this->fdd[$column]['options'] = preg_replace('/[HWR0]/', '', $this->fdd[$column]['options']);
					// if options otherwise is empty, unset it
					if (empty($this->fdd[$column]['options'])) {
						unset($this->fdd[$column]['options']);
					}
				}

			}
		}
	} /* }}} */

	/*
	 * Error handling function
	 */
	function error($message, $additional_info = '') /* {{{ */
	{
		echo '<h1>phpMyEdit error: ',htmlspecialchars($message),'</h1>',"\n";
		if ($additional_info != '') {
			echo '<hr size="1" />',htmlspecialchars($additional_info);
		}
		return false;
	} /* }}} */

	/*
	 * Database connection function
	 */
	function connect() /* {{{ */
	{
		if ($this->dbhValid()) {
			return true;
		}
		if (!isset($this->db)) {
			$this->error('no database defined');
			return false;
		}
		if (!isset ($this->tb)) {
			$this->error('no table defined');
			return false;
		}
		$this->sql_connect();
		if (!$this->dbhValid()) {
			$this->error('could not connect to SQL');
			return false;
		}
		return true;
	} /* }}} */

	/*
	 * The workhorse
	 */
	function execute() /* {{{ */
	{
		/* echo '<PRE>'; */
		/* echo "op: ".$this->operation." view ena: ".$this->view_enabled; */
		/* echo '</PRE>'; */
		//	DEBUG -	 uncomment to enable
		/*
		//phpinfo();
		$this->print_get_vars();
		$this->print_post_vars();
		$this->print_vars();
		echo "<pre>query opts:\n";
		echo print_r($this->query_opts);
		echo "</pre>\n";
		echo "<pre>get vars:\n";
		echo print_r($this->get_opts);
		echo "</pre>\n";
		*/

		$error_reporting = error_reporting(E_ALL & ~E_NOTICE);

		// Checking if language file inclusion was successful
		if (! is_array($this->labels)) {
			$this->error('could not locate language files', 'searched path: '.$this->dir['lang']);
			return false;
		}
		// Database connection
		if ($this->connect() == false) {
			return false;
		}

		/*
		 * ======================================================================
		 * Pass 3: process any updates generated if the user has selected
		 * a save or cancel button during Pass 2
		 * ======================================================================
		 */
		// Cancel button - Cancel Triggers
		if ($this->add_canceled() || $this->copy_canceled()) {
			$this->exec_triggers_simple(self::SQL_QUERY_INSERT, self::TRIGGER_CANCEL);
		}
		if ($this->view_canceled()) {
			$this->exec_triggers_simple(self::SQL_QUERY_SELECT, self::TRIGGER_CANCEL);
		}
		if ($this->change_canceled() || $this->change_reloaded()) {
			$this->exec_triggers_simple(self::SQL_QUERY_UPDATE, self::TRIGGER_CANCEL);
		}
		if ($this->delete_canceled()) {
			$this->exec_triggers_simple(self::SQL_QUERY_DELETE, self::TRIGGER_CANCEL);
		}
		// Save/More Button - database operations
		if ($this->label_cmp($this->saveadd, 'Save')
			|| $this->label_cmp($this->savecopy, 'Save')) {
			$this->add_enabled() && $this->do_add_record();
			if (empty($this->rec)) {
				$this->operation = $this->labels['Add']; // to force add operation
			} else {
				$this->saveadd	= null; // unset($this->saveadd)
				$this->savecopy = null; // unset($this->savecopy)
			}
			$this->recreate_fdd();
		}
		elseif ($this->label_cmp($this->applyadd, 'Apply')
				|| $this->label_cmp($this->applycopy, 'Apply')) {
			$this->add_enabled() && $this->do_add_record();
			if (empty($this->rec)) {
				$this->operation = $this->labels['Add']; // to force add operation
			} else {
				$this->saveadd	 = null; // unset($this->saveadd)
				$this->savecopy  = null; // unset($this->savecopy)
				$this->applyadd  = null; // unset($this->applyadd)
				$this->applycopy = null; // unset($this->applycopy)
				$this->operation = $this->labels['Change']; // to force change operation
			}
			$this->recreate_fdd();
			$this->backward_compatibility();
			$this->recreate_displayed();
		}
		elseif ($this->label_cmp($this->moreadd, 'More')) {
			$this->add_enabled() && $this->do_add_record();
			if (empty($this->rec)) {
				$this->operation = $this->labels['Add']; // to force add operation
			}
			$this->recreate_fdd();
			$this->backward_compatibility();
			$this->recreate_displayed();
		}
		elseif ($this->label_cmp($this->savechange, 'Save')) {
			$this->change_enabled() && $this->do_change_record();
			$this->savechange = null; // unset($this->savechange)
			$this->recreate_fdd();
		}
		elseif ($this->label_cmp($this->morechange, 'Apply')) {
			$this->change_enabled() && $this->do_change_record();
			$this->operation = $this->labels['Change']; // to force change operation
			$this->recreate_fdd();
			$this->backward_compatibility();
			$this->recreate_displayed();
		}
		elseif ($this->label_cmp($this->savedelete, 'Delete')) {
			$this->delete_enabled() && $this->do_delete_record();
			$this->savedelete = null; // unset($this->savedelete)
			$this->recreate_fdd();
		}
		elseif ($this->label_cmp($this->reloadview, 'Reload')) {
			$this->operation = $this->labels['View']; // force view operation.
			$this->reloadview = null;
			$this->recreate_fdd();
			$this->backward_compatibility();
			$this->recreate_displayed();
		}
		elseif ($this->label_cmp($this->reloadchange, 'Reload')) {
			$this->operation = $this->labels['Change']; // to force change operation
			$this->recreate_fdd();
			$this->backward_compatibility();
			$this->recreate_displayed();
		}


		/*
		 * ======================================================================
		 * Pass 2: display an input/edit/confirmation screen if the user has
		 * selected an editing button on Pass 1 through this page
		 * ======================================================================
		 */
		if ($this->add_operation()
			|| $this->change_operation() || $this->delete_operation()
			|| $this->view_operation()	 || $this->copy_operation()) {
			$this->display_record();
		}

		/*
		 * ======================================================================
		 *
		 * If the "misc"-callback is there and the misc-button has been pressed
		 * then forward to that script.
		 *
		 * ======================================================================
		 */
		elseif ($this->misc_operation()) {
			$this->sql_disconnect();
			return call_user_func($this->miscphp);
		}

		/*
		 * ======================================================================
		 * Pass 1 and Pass 3: display the SQL table in a scrolling window on
		 * the screen (skip this step in 'Add More' mode)
		 * ======================================================================
		 */
		else {
			$this->recreate_fdd();
			$this->backward_compatibility();
			$this->recreate_displayed();
			$this->list_table();
		}

		$this->sql_disconnect();
		if ($this->display['time'] && $this->timer != null) {
			echo '<span>'.$this->timer->end().' miliseconds'.'</span>';
		}
	} /* }}} */

	/*
	 * Class constructor
	 */
	function __construct($opts) /* {{{ */
	{
		$error_reporting = error_reporting(E_ALL & ~E_NOTICE);
		// Database handle variables
		$this->sql_delimiter();
		if (isset($opts['dbh'])) {
			$this->close_dbh = false;
			$this->dbh = $opts['dbh'];
			$this->dbp = '';
		} else {
			$this->close_dbh = true;
			$this->dbh = null;
			$this->dbp = $this->sd.$opts['db'].$this->ed.'.';
			$this->hn  = $opts['hn'];
			$this->un  = $opts['un'];
			$this->pw  = $opts['pw'];
			$this->db  = $opts['db'];
		}
		$this->tb  = $opts['tb'];
		// Other variables§
		$this->key		 = $opts['key'];
		if (!is_array($this->key)) {
			$this->key = [ $this->key => $opts['key_type'] ];
		}
		$this->groupby   = @$opts['groupby_fields'];
		if ($this->groupby) {
			if (!is_array($this->groupby)) {
				$this->groupby = array($this->groupby);
			}
			$this->groupby = array_values(array_unique(array_merge(array_keys($this->key), $this->groupby)));
			$this->groupby_where = @$opts['groupby_where'];
			if ($this->groupby_where === true) {
				$this->groupby_where = 'ACDPV'; // any single record view
			}
		}

		$this->inc		 = $opts['inc'];
		$this->options	 = $opts['options'];
		$this->fdd		 = $opts['fdd'];
		$this->multiple	 = intval($opts['multiple']);
		$this->multiple <= 0 && $this->multiple = 2;

		// WHERE filters
		$this->filters   = array('AND' => false, 'OR' => false);
		if (!empty($opts['filters'])) {
			$filters = $opts['filters'];
			if (!is_array($filters)) {
				$filters = array('AND' => array($filters), 'OR' => false);
			}
			if (!isset($filters['AND']) && !isset($filters['OR'])) {
				$filters = array('AND' => $filters, 'OR' => false);
			}
			if (!isset($filters['AND'])) {
				$filters['AND'] = false;
			}
			if (!isset($filters['OR'])) {
				$filters['OR'] = false;
			}
			foreach ($filters as $junctor => $filter) {
				if (empty($filter)) {
					continue;
				}
				if (is_array($filter)) {
					$this->filters[$junctor] = join(' '.$junctor.' ', $filter);
				} else {
					$this->filters[$junctor] = $filter;
				}
			}
		}
		// at this point $this->filters is a normalized array

		// HAVING filters
		$this->having   = array('AND' => false, 'OR' => false);
		if (isset($opts['having'])) {
			$filters = $opts['having'];
			if (!is_array($filters)) {
				$filters = array('AND' => array($filters), 'OR' => false);
			}
			if (!isset($filters['AND']) && !isset($filters['OR'])) {
				$filters = array('AND' => $filters, 'OR' => false);
			}
			if (!isset($filters['AND'])) {
				$filters['AND'] = false;
			}
			if (!isset($filters['OR'])) {
				$filters['OR'] = false;
			}
			foreach ($filters as $junctor => $filter) {
				if (empty($filter)) {
					continue;
				}
				if (is_array($filter)) {
					$this->having[$junctor] = join(' '.$junctor.' ', $filter);
				} else {
					$this->having[$junctor] = $filter;
				}
			}
		}
		// at this point $this->having is a normalized array

		$this->triggers	 = @$opts['triggers'];
		$this->notify	 = @$opts['notify'];
		$this->logtable	 = @$opts['logtable'];
		$this->miscphp	 = @$opts['misc']['php'];
		$this->misccss   = @$opts['misc']['css']['major'];
		$this->misccss2  = @$opts['misc']['css']['minor'];
		if (!$this->misccss) {
			$this->misccss = 'misc';
		}
		if ($this->misccss2) {
			$this->misccss2 = ' '.$this->misccss2;
		}
		$this->page_name = @$opts['page_name'];
		if (! isset($this->page_name)) {
			$this->page_name = basename($this->get_server_var('PHP_SELF'));
			isset($this->page_name) || $this->page_name = $this->tb;
		}
		$this->display['query'] = @$opts['display']['query'];
		$this->display['sort']	= @$opts['display']['sort'];
		$this->display['time']	= @$opts['display']['time'];
		if ($this->display['time']) {
			$this->timer = new phpMyEdit_timer();
		}
		$this->display['tabs'] = isset($opts['display']['tabs'])
			? $opts['display']['tabs'] : true;
		$this->display['form'] = isset($opts['display']['form'])
			? $opts['display']['form'] : true;
		$this->display['num_records'] = isset($opts['display']['num_records'])
			? $opts['display']['num_records'] : true;
		$this->display['num_pages'] = isset($opts['display']['num_pages'])
			? $opts['display']['num_pages'] : true;
		$this->display['navigation'] = isset($opts['display']['navigation'])
			? $opts['display']['navigation'] : 'VCPD'; // default: all

		$this->display['readonly'] =
			isset($opts['display']['readonly'])
			? $opts['display']['readonly']
			: 'readonly';
		$this->display['disabled'] =
			isset($opts['display']['disabled'])
			? $opts['display']['disabled']
			: 'disabled';

		// Creating directory variables
		$this->dir['root'] = dirname(realpath(__FILE__))
			. (strlen(dirname(realpath(__FILE__))) > 0 ? '/' : '');
		$this->dir['lang'] = $this->dir['root'].'lang/';
		// Creating URL variables
		$this->url['images'] = 'images/alt/';
		isset($opts['url']['images']) && $this->url['images'] = $opts['url']['images'];
		// CSS classes policy
		$this->css = @$opts['css'];
		!isset($this->css['textarea'])  && $this->css['textarea']  = '';
		!isset($this->css['separator']) && $this->css['separator'] = '-';
		!isset($this->css['prefix'])	&& $this->css['prefix']	   = 'pme';
		!isset($this->css['postfix'])	&& $this->css['postfix']   = '';
		!isset($this->css['page_type']) && $this->css['page_type'] = false;
		!isset($this->css['position'])	&& $this->css['position']  = false;
		!isset($this->css['divider'])	&& $this->css['divider']   = 2;
		!isset($this->css['row'])   	&& $this->css['row']       = '';
		$this->css['divider'] = intval(@$this->css['divider']);
		// JS overall configuration
		$this->js = @$opts['js'];
		!isset($this->js['prefix']) && $this->js['prefix'] = 'PME_js_';
		// DHTML overall configuration
		$this->dhtml = @$opts['dhtml'];
		!isset($this->dhtml['prefix']) && $this->dhtml['prefix'] = 'PME_dhtml';
		// Navigation
		$this->navigation = @$opts['navigation'];
		if (!stristr($this->navigation, 'N')) {
			if (! $this->nav_buttons() && ! $this->nav_text_links() && ! $this->nav_graphic_links()) {
				$this->navigation .= 'B'; // buttons are default
			}
			if (! $this->nav_up() && ! $this->nav_down()) {
				$this->navigation .= 'D'; // down position is default
			}
		}
		$this->buttons = @$opts['buttons'];
		// Language labels (must go after navigation)
		$this->labels = $this->make_language_labels(isset($opts['language'])
													? $opts['language'] : $this->get_server_var('HTTP_ACCEPT_LANGUAGE'));
		if (isset($opts['labels']) && is_array($opts['labels'])) {
			if (isset($opts['labels']['Misc'])) {
				$this->labels['Misc'] = $opts['labels']['Misc'];
			}
			if (isset($opts['labels']['Sort Field'])) {
				$this->labels['Sort Field'] = $opts['labels']['Sort Field'];;
			}
		}
		$this->tooltips = array();
		if (isset($opts['tooltips']) && (is_array($opts['tooltips']) || ($opts['tooltips'] instanceof \ArrayAccess))) {
			$this->tooltips = $opts['tooltips'];
			/* echo '<PRE>'; */
			/* print_r($this->tooltips); */
			/* echo '</PRE>'; */
		}

		// CGI variables
		$this->cgi = @$opts['cgi'];
		foreach (array('operation', 'sys', 'data') as $type) {
			if (! isset($this->cgi['prefix'][$type])) {
				$this->cgi['prefix'][$type] = $this->get_default_cgi_prefix($type);
			}
		}
		// Sorting variables
		$this->sfn	 = $this->get_sys_cgi_var('sfn');
		isset($this->sfn)			  || $this->sfn			 = array();
		is_array($this->sfn)		  || $this->sfn			 = array($this->sfn);

		// Make sure also the key are sorted numerically. Bloody PHP.
		ksort($this->sfn, SORT_NUMERIC);

		// Check whether we have new sort-fields
		$sort = $this->get_sys_cgi_var('sort');
		isset($sort) || $sort = array();
		foreach ($sort as $k => $fqn) {
			$this->sfn[] = "$k";
		}
		// Check whether we have to revert sort-fields
		$rvrt = $this->get_sys_cgi_var('rvrt');
		isset($rvrt) || $rvrt = array();
		foreach ($rvrt as $k => $fqn) {
			if (($i = array_search("$k", $this->sfn, true)) !== false) {
				$this->sfn[$i] = "-$k";
			} elseif (($i = array_search("-$k", $this->sfn, true)) !== false) {
				$this->sfn[$i] = "$k";
			}
		}

		isset($opts['sort_field'])	  || $opts['sort_field'] = array();
		is_array($opts['sort_field']) || $opts['sort_field'] = array($opts['sort_field']);
		$this->dfltsfn = $opts['sort_field'];
		if (false) {
			echo '<PRE>';
			print_r($this->sfn);
			print_r($sort);
			echo '</PRE>';
		}
		// Get operation.
		$this->operation = $this->get_sys_cgi_var('operation');
		if (false) {
			echo '<PRE>';
			print_r($this->operation);
			echo '</PRE>';
		}
		/* Getting rid of text-links makes it necessary to
		 * attach further information to the operation
		 * field. This is done in the style of a _GET()
		 * value. So first strip the trailing string after the
		 * first '?' sign and between '&' signs. Perfectly
		 * un-fool-proof, of course.
		 *
		 * Behold: parse_url() needs alpha-numeric chars, not
		 * multi-byte etc. characters.
		 */

		/* First get any hard-coded record, then possibly
		 * override by operation query string.
		 */
		$this->rec = $this->get_sys_cgi_var('rec', []);
		if (!is_array($this->rec)) {
			if (!empty($this->rec)) {
				$this->rec = [ array_keys($this->key)[0] => $this->rec ];
			} else {
				$this->rec = [];
			}
		}
		$this->groupby_rec = $this->get_sys_cgi_var('groupby_rec', []);

		$querypart = '';
		$qpos = strpos($this->operation, '?');
		if ($qpos !== false) {
			$querypart = substr($this->operation, $qpos);
			$this->operation = substr($this->operation, 0, $qpos);
		}
		$opreq = parse_url('fake://pme/operation'.$querypart);
		$opquery = array();
		if (isset($opreq['query'])) {
			parse_str($opreq['query'], $opquery);
		}
		/* May be more complicated in the future, but for now
		 * we only expect the record here, so only check for
		 * that.
		 */
		if (count($opquery) > 2) {
			$this->logWarn('Too many faked _GET parameters: '.print_r($opquery, true));
		}
		$key = $this->cgi['prefix']['sys'].'rec';
		if (isset($opquery[$key])) {
			$this->rec = $opquery[$key];
		}
		if (!empty($this->rec) && !is_array($this->rec)) {
			$this->rec = [ array_keys($this->key)[0] => $this->rec ];
		}
		$key = $this->cgi['prefix']['sys'].'groupby_rec';
		if (isset($opquery[$key])) {
			$this->groupby_rec = $opquery[$key];
		}


		/* echo '<PRE>'; */
		/* print_r($opquery); */
		/* echo "\nkey: ".$key."\n"; */
		/* echo "op: ".$this->operation; */
		/* echo "\nreq: ".$this->rec."\n"; */
		/* echo '</PRE>'; */

		/****************************************************************/

		$oper_prefix_len = strlen($this->cgi['prefix']['operation']);
		if (! strncmp($this->cgi['prefix']['operation'], $this->operation, $oper_prefix_len)) {
			$this->operation = $this->labels[substr($this->operation, $oper_prefix_len)];
		}
		// Persistent values.
		$this->cgi['persist'] = '';
		if (!@is_array($opts['cgi']['persist'])) {
			$opts['cgi']['persist'] = array();
		}
		$this->mrecs = $this->get_sys_cgi_var('mrecs', array());
		$this->mrecs = array_values(array_unique($this->mrecs));

		foreach ($opts['cgi']['persist'] as $key => $val) {
			if (is_array($val)) {
				// We need to handle sys_recs in a special way: never
				// use absolute indices, because this kills the
				// information submitted by the user (checkboxes)
				if ($key == $this->cgi['prefix']['sys'].'mrecs') {
					foreach($val as $key2 => $val2) {
						$this->cgi['persist'] .= '&'.rawurlencode($key)
							.'[]='.rawurlencode($val2);
					}
				} else {
					if (false) {
						foreach($val as $key2 => $val2) {
							$this->cgi['persist'] .= '&'.rawurlencode($key)
								.'['.rawurlencode($key2).']='.rawurlencode($val2);
						}
					} else {
						$this->cgi['persist'] .= '&'.http_build_query(array($key => $val));
					}
				}
			} else {
				$this->cgi['persist'] .= '&'.rawurlencode($key).'='.rawurlencode($val);
			}
		}
		// Form variables all around
		$this->fl	 = intval($this->get_sys_cgi_var('fl'));
		$this->fm	 = intval($this->get_sys_cgi_var('fm'));
//		$old_page = ceil($this->fm / abs($this->inc)) + 1;
		$this->qfn	 = $this->get_sys_cgi_var('qfn');
		$this->sw	 = $this->get_sys_cgi_var('sw');
		$this->navop = $this->get_sys_cgi_var('navop');
		$navfmup	 = $this->get_sys_cgi_var('navfmup');
		$navfmdown	 = $this->get_sys_cgi_var('navfmdown');
		$navpnup	 = $this->get_sys_cgi_var('navpnup');
		$navpndown	 = $this->get_sys_cgi_var('navpndown');
		$navnpup	 = $this->get_sys_cgi_var('navnpup');
		$navnpdown	 = $this->get_sys_cgi_var('navnpdown');
		$prevnp		 = $this->get_sys_cgi_var('np');
		if ($this->misc_enabled() && ($this->operation == '-' || $this->operation == '+')) {
			// force the user to view all the mess.
			$this->inc = -1;
		} elseif ($prevnp != '') {
			$this->inc = $prevnp;
			if($navnpdown != NULL && $navnpdown != $this->inc) $this->inc = $navnpdown;
			elseif($navnpup != NULL && $navnpup != $this->inc) $this->inc = $navnpup;
		}
		if ($prevnp != NULL && $prevnp != $this->inc && $this->inc > 0 && $prevnp > 0) {
			// Set current form such that it is at least close to the old position.
			$this->navfm = intval($this->fm / $this->inc) * $this->inc;
		} else {
			if($navfmdown!=NULL && $navfmdown != $this->fm) $this->navfm = $navfmdown;
			elseif($navfmup!=NULL && $navfmup != $this->fm) $this->navfm = $navfmup;
			elseif($navpndown!=NULL && ($navpndown-1)*$this->inc != $this->fm) $this->navfm = ($navpndown-1)*$this->inc;
			elseif($navpnup!=NULL && ($navpnup-1)*$this->inc != $this->fm) $this->navfm = ($navpnup-1)*$this->inc;
			else $this->navfm = $this->fm;
		}
		$this->saveadd		= $this->get_sys_cgi_var('saveadd');
		$this->moreadd		= $this->get_sys_cgi_var('moreadd');
		$this->applyadd		= $this->get_sys_cgi_var('applyadd');
		$this->canceladd	= $this->get_sys_cgi_var('canceladd');
		$this->savechange	= $this->get_sys_cgi_var('savechange');
		$this->morechange	= $this->get_sys_cgi_var('morechange');
		$this->cancelchange = $this->get_sys_cgi_var('cancelchange');
		$this->reloadchange = $this->get_sys_cgi_var('reloadchange');
		$this->savecopy		= $this->get_sys_cgi_var('savecopy');
		$this->applycopy	= $this->get_sys_cgi_var('applycopy');
		$this->cancelcopy	= $this->get_sys_cgi_var('cancelcopy');
		$this->savedelete	= $this->get_sys_cgi_var('savedelete');
		$this->canceldelete = $this->get_sys_cgi_var('canceldelete');
		$this->cancelview	= $this->get_sys_cgi_var('cancelview');
		$this->reloadview	= $this->get_sys_cgi_var('reloadview');

		// Filter setting
		if (isset($this->sw)) {
			$this->label_cmp($this->sw, 'Search') && $this->fl = 1;
			$this->label_cmp($this->sw, 'Hide')	  && $this->fl = 0;
			//$this->label_cmp($this->sw, 'Clear')	&& $this->fl = 0;
		}
		// TAB names
		$this->tabs = array();
		// Setting key_delim according to key_type
		foreach ($this->key as $key => $key_type) {
			if ($key_type == 'real') {
				/* If 'real' key_type does not work,
				   try change MySQL datatype from float to double */
				$this->rec[$key] = doubleval($this->rec[$key]);
				$this->key_delim[$key] = '';
			} elseif ($key_type == 'int') {
				$this->rec[$key] = intval($this->rec[$key]);
				$this->key_delim[$key] = '';
			} else {
				$this->key_delim[$key] = "'";
				// $this->rec remains unmodified
			}
		}
		// Specific $fdd modifications depending on performed action
		$this->recreate_fdd();
		// Issue backward compatibility
		$this->backward_compatibility();
		// Extract SQL Field Names and number of fields
		$this->recreate_displayed();
		// Gathering query options
		$this->gather_query_opts();
		// Call to action
		!isset($opts['execute']) && $opts['execute'] = 1;
		$opts['execute'] && $this->execute();
		error_reporting($error_reporting);
	} /* }}} */

}

// Local Variables: ***
// mode: php ***
// c-basic-offset: 4 ***
// tab-width: 4 ***
// indent-tabs-mode: t ***
// web-mode-code-indent-offset: 4 ***
// End: ***

?>
