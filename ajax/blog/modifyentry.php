<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
use CAFEVDB\Util;
use CAFEVDB\Blog;

OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();

Config::init();

$author  = Util::cgiValue('author', OCP\User::getUser());
$blogId  = Util::cgiValue('blogId', -1);
$inReply = Util::cgiValue('inReply', -1);
$text    = Util::cgiValue('text', '');
$sticky  = Util::cgiValue('sticky', false);

if ($author === false || $author == '') {
  OCP\JSON::error(
    array(
      'data' => array(
        'message' => L::t('Refusing to create blog entry without author identity.'))));
  return false;
}

if ($blogId < 0 && $text == '') {
  OCP\JSON::error(
    array(
      'data' => array(
        'message' => L::t('Refusing to create empty blog entry.'))));
  return false;  
}

// Insert the stuff into the data base. Afterwards the page will
// reload and update the display.

try {
  if ($blogId >= 0 && $sticky !== false) {
    $result = Blog::stickyNote($author, $blogId, $sticky);
  } else if ($blogId >= 0 && $text == '') {
    $result = Blog::deleteNote($blogId, false);
  } else {
    $result = Blog::modifyAddNote($author, $blogId, $inReply, $text);
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
  OCP\JSON::success();
  return true;
} else {
  OCP\JSON::error(
    array(
      'data' => array(
        'message' => L::t('There was an error inserting the blog-entry.'))));
  return false;
}

?>
