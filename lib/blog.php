<?php

namespace CAFEVDB;

class Blog 
{
  /**Delete the given Note and the entire thread referring to
   * it. There is intentionally no security.
   *
   * @param[in] $blogId The unique Id of the note to delete.
   *
   * @param[in] $drop If @c true then really delete the message,
   * otherwise just mark them as deleted.
   *
   * @return bool, @c true on success.
   */
  public static function deleteNote($blogId, $drop = false)
  {
    $table = '*PREFIX*'.Config::APP_NAME.'_blog';
    $children = self::fetchThread($blogId);

    $result = true;
    foreach ($children as $child) {
      $result = $result && self::deleteNote($child['id'], $drop);
    }

    $params = array($blogId, $blogId);
    if (!$drop) {
      $query = 'UPDATE '.$table.' SET deleted = ? WHERE id = ? OR inreplyto = ?';
      array_unshift($params, time());
    } else {
      $query = 'DELETE FROM '.$table.' WHERE WHERE id = ? OR inreplyto = ?';
    }
    $query = \OCP\DB::prepare($query);
    return $result && $query->execute($params);
  }

  /**Set the sticky switch. Only top-level notes can be sticky, making
   *follow-ups stickz just makes no sense.
   *
   * @param[in] $author The author of this mess.
   *
   * @param[in] $blogId The Id. If Id does not exist in the data-base,
   * then a new message is added, otherwise the old message is
   * replaced.
   *
   * @param[in] $sticky Sticky messages are display on the top of the page.
   *
   * @return bool, @c true on success.
   */
  public static function stickyNote($author, $blogId, $sticky)
  {
    $row = self::fetchEntry($blogId);
    if ($row['inreplyto'] >= 0) {
      return false;
    }
    $table = '*PREFIX*'.Config::APP_NAME.'_blog';
    $sticky = $sticky ? 1 : 0;
    $query = 'UPDATE '.$table.'
 SET editor = ?, modified = ?, sticky = ?
 WHERE id = ?';
    $params = array($author, time(), $sticky, $blogId);
    $query = \OCP\DB::prepare($query);
    return $query->execute($params);
  }  

  /**Either modify or add a new note.
   *
   * @param[in] $author The author of this mess.
   *
   * @param[in] $blogId The Id. If Id does not exist in the data-base,
   * then a new message is added, otherwise the old message is
   * replaced.
   *
   * @param[in] $inReply The id of a previous note this one refers
   * to. If $blogId >= 0 then this parameter is ignored; only the
   * message text is affected.
   *
   * @param[in] $text The message text.
   *
   * @param[in] $sticky Sticky messages are display on the top of the page.
   *
   * @return bool, @c true on success.
   */
  public static function modifyAddNote($author, $blogId, $inReply, $text, $sticky = false)
  {
    $table = '*PREFIX*'.Config::APP_NAME.'_blog';
    $sticky = $sticky ? 1 : 0;
    if ($blogId >= 0) {
      $query = 'UPDATE '.$table.'
 SET editor = ?, modified = ?, message = ?, sticky = ?
 WHERE id = ?';
      $params = array($author, time(), $text, $sticky, $blogId);
    } else {
      $query = 'INSERT INTO '.$table.' (author,created,message,inreplyto,sticky) VALUES (?,?,?,?,?)';
      $params = array($author, time(), $text, $inReply, $sticky);
    }
    $query = \OCP\DB::prepare($query);
    return $query->execute($params);
  }  

  public static function fetchEntry($blogId)
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    $query  = 'SELECT * FROM '.$table.' WHERE id = ? ORDER BY created DESC';
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute(array($blogId));
    $result = $result->fetchAll();
    if (!is_array($result) || count($result) != 1) {
      return false;
    }
    return array_shift($result);
  }

  /**Fetch the top-level thread-starters, possibly depending on their
   * "stickyness".
   *
   * @param[in] $sticky Default @c false, which means to fetch
   * all. Otherwise should be @c 0 to fetch only un-sticked notes, or
   * @c 1 to fetch only the sticky notes.
   *
   * @return The respective rows from the database.
   */
  public static function fetchThreadHeads($sticky = false)
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    if ($sticky === false) {
      $query  = 'SELECT * FROM '.$table.' WHERE inreplyto = -1 ORDER BY created DESC';
      $parameters = array();
    } else {
      $query  = 'SELECT * FROM '.$table.' WHERE inreplyto = -1 && sticky = ? ORDER BY created DESC';
      $parameters = array($sticky);
    }  
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute($parameters);
    return $result->fetchAll();
  }

  /**Fetch the message-thread referring to $blogId.
   */
  public static function fetchThread($blogId)
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    $query  = 'SELECT * FROM '.$table.' WHERE inreplyto = ? ORDER BY created DESC';
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute(array($blogId));
    return $result->fetchAll();
  }

  /**Generate a complex tree structure reflecting the message threads.
   */
  public static function fetchThreadTree($parent)
  {
    $children = self::fetchThread($parent['head']['id']);
    $parent['children'] = array();
    foreach ($children as $row) {
      $child = self::fetchThreadTree(array('head' => $row));
      $parent['children'][] = $child;
    }
    return $parent;
  }  

  /**Generate a complex tree structure reflecting the message threads.
   * This is the entry point for the template: fetch all notes in
   * turn. If the function succeeds the return value has the following
   * layout:
   *
   * array('status' => 'success',
   *       'data' => array([0] => array('head' => <ThreadHead>,
   *                                    'children' => array([0] => array('head' =>
   *
   * and so on, i.e. a representatin of the message-thread tree. Data
   * will be the empty array if there is not data. On error the
   * following structure is returned:
   *
   * array('status' => 'error',
   *       'data' => ERROR_MESSAGE)
   *
   * The returned array will contain the sticky threads as first
   * elements, sorted descending with respect to creation date
   * followed by all other notes (not that I expect that there will be
   * so many notes ...)
   *
   * @return Tree-like nested array structure modelling the message
   * threads.
   */
  public static function fetchThreadDisplay()
  {
    $data   = array();
    $result = array('status' => 'success');
    try {
      $sticky   = self::fetchThreadHeads(1);
      $ordinary = self::fetchThreadHeads(0);
      $heads    = array_merge($sticky, $ordinary);
      foreach ($heads as $row) {
        $tree = self::fetchThreadTree(array('head' => $row));
        $data[] = $tree;
      }
    } catch (\Exception $e) {
      $result['status'] = 'error';
      $data = $e->getMessage();
    }
    $result['data'] = $data;
    return $result;
  }
  
  public static function fetchPhoto($user)
  {
    if (\OCP\App::isEnabled('user_photo')) {
      return \OC::$WEBROOT.'/?app=user_photo&getfile=ajax%2Fshowphoto.php&user='.$user;
    } else {
      return \OCP\Util::imagePath('cafevdb', 'photo.png');
    }
  }
};


?>
