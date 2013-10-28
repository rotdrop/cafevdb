<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
use CAFEVDB\Util;
use CAFEVDB\Blog;
use CAFEVDB\Error;

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::callCheck();

try {

  Error::exceptions(true);

  Config::init();

  $author   = Util::cgiValue('author', OCP\User::getUser());

  $blogId   = Util::cgiValue('blogId', -1);
  $inReply  = Util::cgiValue('inReply', -1);
  $text     = Util::cgiValue('text', '');
  $priority = Util::cgiValue('priority', false);

  if ($author === false || $author == '') {
    OCP\JSON::error(
      array(
        'data' => array(
          'message' => L::t('Refusing to create blog entry without author identity.'))));
    return false;
  }

  if ($blogId >= 0 && $inReply == -1 && $text == '') {
    // This is an edit attempt.
    try {
      $entry = Blog::fetchNote($blogId);
    } catch (\Exception $e) {
      OCP\JSON::error(
        array(
          'data' => array(
            'message' => L::t('Error, caught an exception `%s\'.',
                              array($e->getMessage())))));
      return false;
    }
    if (!$entry) {
      OCP\JSON::error(
        array(
          'data' => array(
            'message' => L::t('Blog entry with id `%s\' could not be retrieved.',
                              array($blogId)))));
      return false;
    }
    $text     = $entry['message'];
    if ($entry['inreplyto'] < 0) {
      $priority = $entry['priority'];
    } else {
      $priority = false;
    }
  } else if ($inReply >= 0) {  
    $priority = false;
  }

  $tmpl = new OCP\Template(Config::APP_NAME, 'blogedit');

  $tmpl->assign('priority', $priority, false);

  $html = $tmpl->fetchPage();

  OCP\JSON::success(
    array('data' => array('content' => $html,
                          'author' => $author,
                          'blogId' => $blogId,
                          'inReply' => $inReply,
                          'text' => $text,
                          'priority' => $priority,
                          'message' => $text.' '.$blogId.' '.$inReply)));

  return true;

} catch (\Exception $e) {
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
}

?>
