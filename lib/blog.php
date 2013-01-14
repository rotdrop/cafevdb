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

  /**Set the sticky switch.
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

  public static function fetchSticky()
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    $query  = 'SELECT * FROM '.$table.' WHERE sticky = 1 ORDER BY created DESC';
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute(array());
    return $result->fetchAll();
  }
  
  public static function fetchThreadHeads()
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    $query  = 'SELECT * FROM '.$table.' WHERE inreplyto = -1 ORDER BY created DESC';
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute(array());
    return $result->fetchAll();
  }

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
   */
  public static function fetchThreadDisplay()
  {
    $result = array();
    try {
      $sticky = self::fetchSticky();
      foreach ($sticky as $row) {
        $result[] = array('head' => $row,
                          'children' => array());
      }
      $heads  = self::fetchThreadHeads();
      foreach ($heads as $row) {
        $tree = self::fetchThreadTree(array('head' => $row));
        if ($tree['head']['sticky'] == 1 && empty($tree['children'])) {
          continue; // already displayed as sticky item without children.
        }
        $result[] = $tree;
      }
    } catch (\Exception $e) {
      array_unshift($result, $e->getMessage());
    }
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
