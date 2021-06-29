<?php

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\Cloud\Entities\Blog as BlogEntry;
use OCA\CAFEVDB\PageRenderer\Renderer as PageRenderer;

class BlogMapper extends Mapper
{
  use \OCA\CAFEVDB\Traits\LoggerTrait; // required IL10N, ILogger

  public function __construct(
    IDBConnection $db
    , $appName
    , IL10N $l10n
    , ILogger $logger
  ) {
    parent::__construct($db, $appName);
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**Create a new note.
   *
   * @param $author The author of this mess.
   *
   * @param $inReply The id of a previous note this one refers
   * to. If $blogId >= 0 then this parameter is ignored; only the
   * message text is affected.
   *
   * @param $content The message text.
   *
   * @param $priority The priority, should be between 0 and
   * 255. Only top-level notes may carry a priority (so if $inReplay
   * >= 0, then $priority is ignored).
   *
   * @param $popup if @c true then the note will appear as one-time popup.
   *            We remeber the user-id of the reader in the db, after the user
   *            has clicked away the alert box.
   *            as a one-time popup.
   *
   * @return bool, @c true on success.
   */
  public function createNote($author, $inReply, $content, $priority = false, $popup = false)
  {
    $priority = $inReply < 0 ? intval($priority) % 256 : 0;

    $note = new BlogEntry();
    $note->setAuthor($author);
    $note->setCreated(time());
    $note->setInReplyTo($inReply);
    $note->setMessage(trim($content));
    $note->setPriority($priority);
    $note->setPopup($popup);

    return $this->insert($note);
  }

  /**Modify a note.
   *
   * @param $author The author of this mess.
   *
   * @param $blogId The blog-Id of an existing entry.
   *
   * @param $content The message text. Ignored if empty.
   *
   * @param $priority The priority in the range 0...255. Ignored if @c false.
   *
   * @param $popup false: do not change. +1: Mark this as a one-time popup-note.
   *                   -1: unmark as popup note. false: ignore
   *
   * @param $reader Comma separated list of users for which the note is marked
   *            as read. If false, nothing changes. If < 0 remove all readers.
   *
   * @return bool, @c true on success.
   */
  public function modifyNote($author, $blogId, $content = '', $priority = false, $popup = null, $reader = false)
  {
    if ($reader < 0) {
      $reader = '';
      $note = new BlogEntry();
    } else if ($reader !== false) {
      $note = $this->find($blogId);
      if ($note === false) {
        return false;
      }
      if ($note->getReader() != '' && $reader != '') {
        $note->setReader($reader.','.$note->getReader());
      }
    }

    if ($content != '' || $reader === false) {
      $note->setEditor($author);
      $note->setModified(time());
    }

    $content = strval($content);
    if (!empty($content)) {
      $note->setMessage($content);
    }
    if ($priority !== false) {
      $note->setPriority($priority);
    }
    if ($popup !== null) {
      $note->setPopup($popup);
    }

    $note->setId($blogId);

    $updated = $note->getUpdatedFields();
    if (count($update) == 1 && is_set($updated['id'])) {
      $this->logError(__METHOD__.': no fields to update, args: '.print_r(func_get_args()));
      return null;
    }

    return $this->update($note);
  }

  /**Delete the given Note and the entire thread referring to
   * it. There is intentionally no security.
   *
   * @param $blogId The unique Id of the note to delete.
   *
   * @param $drop If @c true then really delete the message,
   * otherwise just mark them as deleted.
   *
   * @return bool, @c true on success.
   */
  public function deleteNote($blogId, $drop = false)
  {
    $note = new BlogEntry();
    $children = $this->findThread($blogId);
    $result = true;
    foreach ($children as $child) {
      $result = $result && $this->deleteNote($child->getId(), $drop);
    }

    $note->setId($blogId);
    if (!$drop) {
      $note->setDelete(true);
      $note->setModified(time());
      return $this->update($note);
    } else {
      return $this->delete($entity);
    }
  }

  /**
   * Fetch the array of messages referring to $id via inReplyTo.
   */
  private function findThread($id)
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->tableName)
       ->where(
         $qb->expr()->eq('in_reply_to', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
       )
       ->orderBy('created', 'ASC');

    return $this->findEntities($qb);
  }

  /**
   * Fetch the top-level thread-starters, sorted by their priority.
   */
  private function findThreadHeads()
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->tableName)
       ->where($qb->expr()->eq('in_reply_to', $qb->createNamedParameter(-1, IQueryBuilder::PARAM_INT)))
       ->orderBy('priority', 'DESC')
       ->orderBy('created', 'DESC');

    return $this->findEntities($qb);
  }

 /**Generate a complex tree structure reflecting the message
   * threads. This function calls itself recursively until all
   * sub-threads have been fetched.
   */
  private function findThreadTree($parent)
  {
    $children = $this->findThread($parent['head']->getId());
    $parent['children'] = [];
    foreach ($children as $entity) {
      $child = $this->findThreadTree(['head' => $entity]);
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
   *       'data' => array([0] => array('head' => THREAD_HEAD,
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
  public function findThreadDisplay()
  {
    $data   = [];
    try {
      $heads = $this->findThreadHeads();
      foreach ($heads as $entity) {
        $tree = $this->findThreadTree(['head' => $entity]);
        $data[] = $tree;
      }
    } catch (\Throwable $t) {
      $this->logger->logException($t);
      throw new \Exception("Cannot create blog thread display", $t->getCode(), $t);
    }
    $this->notificationPending('claus');
    return $data;
  }

  /**Get the time stamp of the most recent post.
   */
  public function lastModifiedTimestamp()
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select($qb->func()->max('b.modified'))
       ->from($this->tableName, 'b');

    $cursor = $qb->execute();
    $row = $cursor->fetchColumn();
    $cursor->closeCursor();

    return $row;
  }

  /**Obtain "some popup notice is pending" for the given user / all users */
  public function notificationPending(string $userId)
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('b.reader')
       ->from($this->tableName, 'b')
       ->where($qb->expr()->eq('popup', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
    $cursor = $qb->execute();
    $userPendingNotifications = false;
    while ($row = $cursor->fetch()) {
      $pendingNotifications = true;
      $re = '/(^|[,])+'.$userId.'($|[,])+/';
      if (preg_match($re, $reader) !== 1) {
        if (!$usePendingNotifications) {
          $this->logInfo(__METHOD__.': '."User `$userId' has pending notifications");
        }
        $userPendingNotifications = true;
      }
    }
    $cursor->closeCursor();

    return $userPendingNotifications;
  }

  public function needPhpSession():bool
  {
    return false;
  }
}
