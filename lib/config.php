<?php

namespace CAFEVDB;

// PHP shit
date_default_timezone_set('Europe/Berlin');

class Config
{
  public static $appbase = "apps/cafevdb/";
  public static $prefix = false;
  public static $triggers = false;
  public static $debug_query = false;
  public static $Wartung = true;
  public static $pmeopts = array();
  public static $opts = array();
  public static $cgiVars = array();
  public static $Languages = array();
  private static $initialized = false;

  static public function init() {
    if (self::$initialized == true) {
      return;
    }
    self::$initialized = true;

    if (!self::$prefix) {
      self::$prefix = self::$appbase . "lib/";
    }
    if (!self::$triggers) {
      self::$triggers = self::$prefix . "triggers/";
    }
    self::$pmeopts['hn'] = 'localhost';
    self::$pmeopts['un'] = 'camerata';
    self::$pmeopts['pw'] = 'foo!bar';
    self::$pmeopts['db'] = 'camerata';
    self::$pmeopts['url']['images'] = self::$appbase . 'img/';
    global $HTTP_SERVER_VARS;
    self::$pmeopts['page_name'] = $HTTP_SERVER_VARS['PHP_SELF'].'?app=cafevdb';

    self::$pmeopts['logtable'] = 'changelog';
    //self::$pmeopts['language'] = 'DE-UTF8';
    self::$pmeopts['cgi']['append']['PME_sys_fl'] = 1;

    // Set default prefixes for variables for PME
    self::$pmeopts['js']['prefix']               = 'PME_js_';
    self::$pmeopts['dhtml']['prefix']            = 'PME_dhtml_';
    self::$pmeopts['cgi']['prefix']['operation'] = 'PME_op_';
    self::$pmeopts['cgi']['prefix']['sys']       = 'PME_sys_';
    self::$pmeopts['cgi']['prefix']['data']      = 'PME_data_';

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    self::$pmeopts['navigation'] = 'GUDM';
    self::$pmeopts['miscphp'] = 'Email::display';
    self::$pmeopts['labels']['Misc'] = 'Em@il';
    //self::$pmeopts['labels']['Sort Field'] = 'Sortierfeld';

    self::$opts['phpmyadmin'] = 'https://ch.homelinux.net:8888/phpmyadmin/index.php?user=camerata&db=camerata';
    self::$opts['geburtstag'] = array('name'     => 'Geburtstag',
                                      'select'   => 'T',
                                      'maxlen'   => 10,
                                      'sort'     => true,
                                      /*'datemask' => 'Y-m-d',*/
                                      'calendar' => array('showTime' => '24',
                                                          'dateFormat' =>'%Y-%m-%d'),
                                      'default' => '1970-01-01'
                                      );
    self::$opts['email'] = array('name'     => 'Em@il',
                                'mask'     => '<A HReF="mailto:%1$s">&lt;%1$s&gt;</A>',
                                'select'   => 'T',
                                'maxlen'   => 768,
                                'sort'     => true,
                                'nowrap'   => true,
                                'escape'   => false);
    
    self::$opts['money'] = array('name' => 'Unkostenbeitrag<BR/>(Gagen negativ)',
                                 'phpview' => self::$prefix . 'money.inc.php',
                                 'align' => 'right',
                                 'select' => 'N',
                                 'maxlen' => '6',
                                 'escape' => false,
                                 'sort' => true);
    
    self::$opts['calendar'] = array('select'   => 'T',
                                    'maxlen'   => 19,
                                    'sort'     => true,
                                    'datemask' => 'Y-m-d H:i:s',
                                    'calendar' => array(
                                                        'showTime' => '24',
                                                        'dateFormat' =>'%Y-%m-%d %H:%M:%S'
                                                        )
                                    );
    //  add as needed
    self::$opts['languages'] = explode(',','Deutsch,Englisch,Französich,Spanisch,Polnisch,Russisch,Japanisch,Koreanisch');
    sort(self::$opts['languages']);

    self::$cgiVars = array('Action' => 'BriefInstrumentation',
                           'SubAction' => '',
                           'Template' => 'projects',
                           'MusicianId' => -1,
                           'ProjectId' => -1,
                           'Project' => '',
                           'RecordsPerPage' => -1);
    self::$pmeopts['tooltips'] = ToolTips::pmeToolTips();
  }
};

?>
