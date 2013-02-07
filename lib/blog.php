<?php

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB;

/**Front-page blog.
 */
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

  /**Create a new note.
   *
   * @param[in] $author The author of this mess.
   *
   * @param[in] $inReply The id of a previous note this one refers
   * to. If $blogId >= 0 then this parameter is ignored; only the
   * message text is affected.
   *
   * @param[in] $text The message text.
   *
   * @param[in] $priority The priority, should be between 0 and
   * 255. Only top-level notes may carry a priority (so if $inReplay
   * >= 0, then $priority is ignored).
   *
   * @return bool, @c true on success.
   */
  public static function createNote($author, $inReply, $text, $priority = false)
  {
    $table = '*PREFIX*'.Config::APP_NAME.'_blog';
    $priority = $inReply < 0 ? intval($priority) % 256 : 0;
    $query = 'INSERT INTO '.$table.'
 (author,created,message,inreplyto,priority) VALUES (?,?,?,?,?)';
    $params = array($author, time(), $text, $inReply, $priority);
    $query = \OCP\DB::prepare($query);
    return $query->execute($params);
  }

  /**Modify a note. 
   *
   * @param[in] $author The author of this mess.
   *
   * @param[in] $blogId The blog-Id of an existing entry.
   *
   * @param[in] $text The message text. Ignored if empty.
   *
   * @param[in] $priority The priority in the range 0...255. Ignored if @c false.
   *
   * @return bool, @c true on success.
   */
  public static function modifyNote($author, $blogId, $text = '', $priority = false)
  {
    $table = '*PREFIX*'.Config::APP_NAME.'_blog';
    $modify = 'editor = ?, modified = ?';
    $params = array($author, time());
    $text = strval($text);
    if ($text != '') {
      $modify .= ', message = ?';
      $params[] = $text;
    }
    if ($priority !== false) {
      $modify .= ', priority = ?';
      $params[] = $priority;
    }
    $params[] = $blogId;

    $query = 'UPDATE '.$table.' SET '.$modify.' WHERE id = ?';
    $query = \OCP\DB::prepare($query);

    return $query->execute($params);
  }

  /**Fetch a single note with the given Id.
   */
  public static function fetchNote($blogId)
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

  /**Fetch the top-level thread-starters, sorted by their priority.
   *
   * @return The respective rows from the database.
   */
  public static function fetchThreadHeads()
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    $query  = 'SELECT * FROM '.$table.' WHERE inreplyto = -1
 ORDER BY priority DESC, created DESC';
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute(array());
    return $result->fetchAll();
  }

  /**Fetch the message-thread referring to $blogId.
   */
  public static function fetchThread($blogId)
  {
    $table  = '*PREFIX*'.Config::APP_NAME.'_blog';
    $query  = 'SELECT * FROM '.$table.' WHERE inreplyto = ? ORDER BY created ASC';
    $query  = \OCP\DB::prepare($query);
    $result = $query->execute(array($blogId));
    return $result->fetchAll();
  }

  /**Generate a complex tree structure reflecting the message
   * threads. This function calls itself recursively until all
   * sub-threads have been fetched.
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
   * Thread-heads are sorted according to priority, then according to
   * date (newest first). The dangling threads are sorted according to
   * date (oldest first).
   *
   * @return Tree-like nested array structure modelling the message
   * threads.
   */
  public static function fetchThreadDisplay()
  {
    $data   = array();
    $result = array('status' => 'success');
    try {
      $heads = self::fetchThreadHeads();
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
};


?>
