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

  $author  = Util::cgiValue('author', OCP\User::getUser());

  $action   = Util::cgiValue('action', false);
  $blogId   = Util::cgiValue('blogId', -1);
  $inReply  = Util::cgiValue('inReply', -1);
  $text     = Util::cgiValue('text', '');
  $priority = Util::cgiValue('priority', false);
  $popup    = Util::cgiValue('popup', false);
  $reader   = Util::cgiValue('reader', '');
  $clearRdr = Util::cgiValue('clearReader', false);

  if ($popup == 'false' || $popup == 0) {
    $popup = false;
  }

  if ($clearRdr) {
    $reader = -1;
  }

  if ($author === false || $author == '') {
    OCP\JSON::error(
      array(
        'data' => array(
          'message' => L::t('Refusing to create blog entry without author identity.'))));
    return false;
  }

  if ($priority !== false && !is_numeric($priority)) {
    OCP\JSON::error(
      array(
        'data' => array(
          'message' => L::t('Message priority should be numeric (and in principle positiv and in the range 0 - 255). I got `%s\'',
                            array($priority)))));
    return false;
  }

  try {

    $generateContents = true;
    $html = '';

    switch ($action) {
    case 'create':
      // Sanity checks
      if (trim($text) == '') {
        OCP\JSON::error(
          array(
            'data' => array(
              'message' => L::t('Refusing to create empty blog entry.'))));
        return false;  
      }
      $priority = intval($priority) % 256;
      $result = Blog::createNote($author, $inReply, $text, $priority, $popup);
      break;
    case 'modify':
      // Sanity checks
      if ($blogId < 0) {
        OCP\JSON::error(
          array(
            'data' => array(
              'message' => L::t('Cannot modify a blog-entry without id.'))));
        return false;  
      }
      $priority = intval($priority) % 256;
      $result = Blog::modifyNote($author, $blogId, trim($text), $priority, $popup, $reader);
      break;
    case 'markread':
      // Sanity checks
      if ($blogId < 0) {
        OCP\JSON::error(
          array(
            'data' => array(
              'message' => L::t('Cannot modify a blog-entry without id.'))));
        return false;  
      }
      $result = Blog::modifyNote($author, $blogId, '', false, false, $author);
      $generateContents = false;
      break;
    case 'delete':
      // Sanity checks
      if ($blogId < 0) {
        OCP\JSON::error(
          array(
            'data' => array(
              'message' => L::t('Cannot delete a blog-thread without id.'))));
        return false;  
      }
      $result = Blog::deleteNote($blogId, false);
      break;
    default:
      OCP\JSON::error(
        array(
          'data' => array(
            'message' => L::t('Unknown request: `%s\'.',
                              array($action)))));
      return false;
      break;
    }

    if ($generateContents) {
      // If everything worked out, then finally fetch the new
      // blog-threads
      $tmpl = new OCP\Template(Config::APP_NAME, 'blogthreads');

      $tmpl->assign('timezone', Util::getTimezone());
      $tmpl->assign('locale', Util::getLocale());
      $tmpl->assign('user', \OCP\User::getUser());

      $html = $tmpl->fetchPage();
    }

  } catch (\Exception $e) {
    OCP\JSON::error(
      array(
        'data' => array(
          'message' => L::t('Error, caught an exception `%s\'.',
                            array($e->getMessage())))));
    return false;
  }

  if ($result) {
    OCP\JSON::success(array('data' => array('contents' => $html)));
    return true;
  } else {
    OCP\JSON::error(
      array(
        'data' => array(
          'message' => L::t('There was an error with the request'),
          'debug' => print_r($_POST, true))));
    return false;
  }

} catch (\Exception $e) {
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
}

?>
