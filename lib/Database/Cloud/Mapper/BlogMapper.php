<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use Exception;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\Cloud\Entities\Blog as BlogEntry;
use OCA\CAFEVDB\PageRenderer\Renderer as PageRenderer;

/** Mapper for the blog entities. */
class BlogMapper extends Mapper
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait; // required IL10N, ILogger

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IDBConnection $db,
    string $appName,
    IL10N $l10n,
    ILogger $logger,
  ) {
    parent::__construct($db, $appName);
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /**
   * Create a new note.
   *
   * @param string $author The author of this mess.
   *
   * @param int $inReply The id of a previous note this one refers
   * to. If $blogId > 0 then this parameter is ignored; only the
   * message text is affected.
   *
   * @param string $content The message text.
   *
   * @param int $priority The priority, should be between 0 and
   * 255. Only top-level notes may carry a priority (so if $inReply
   * > 0, then $priority is ignored).
   *
   * @param bool $popup if @c true then the note will appear as one-time popup.
   *            We remeber the user-id of the reader in the db, after the user
   *            has clicked away the alert box.
   *            as a one-time popup.
   *
   * @return bool @c true on success.
   */
  public function createNote(
    string $author,
    ?int $inReply,
    string $content,
    int $priority = 0,
    bool $popup = false,
  ) {
    $priority = ($inReply ?? 0) <= 0 ? intval($priority) % 256 : 0;

    $note = new BlogEntry();
    $note->setAuthor($author);
    $note->setCreated(time());
    $note->setInReplyTo($inReply);
    $note->setMessage(trim($content));
    $note->setPriority($priority);
    $note->setPopup($popup);

    return $this->insert($note);
  }

  /**
   * Modify a note.
   *
   * @param string $author The author of this mess.
   *
   * @param int $blogId The blog-Id of an existing entry.
   *
   * @param string $content The message text. Ignored if empty.
   *
   * @param bool|int $priority The priority in the range 0...255. Ignored if @c false.
   *
   * @param null|bool $popup false: do not change. +1: Mark this as a one-time popup-note.
   *                   -1: unmark as popup note. false: ignore.
   *
   * @param mixed $reader Comma separated list of users for which the note is marked
   *            as read. If false, nothing changes. If < 0 remove all readers.
   *
   * @return bool @c true on success.
   */
  public function modifyNote(
    string $author,
    int $blogId,
    string $content = '',
    mixed $priority = false,
    ?bool $popup = null,
    mixed $reader = false,
  ) {
    if ($reader < 0) {
      $reader = '';
      $note = new BlogEntry();
    } elseif ($reader !== false) {
      $note = $this->find($blogId);
      if ($note === false) {
        return false;
      }
      if ($note->getReader() != '' && $reader != '') {
        $note->setReader($reader.','.$note->getReader());
      } else {
        $note->setReader($reader);
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
    if (count($updated) == 1 && isset($updated['id'])) {
      $this->logError('No fields to update, args: '.print_r(func_get_args(), true));
      return null;
    }

    return $this->update($note);
  }

  /**
   * Delete the given Note and the entire thread referring to
   * it. There is intentionally no security.
   *
   * @param int $blogId The unique Id of the note to delete.
   *
   * @param bool $drop If @c true then really delete the message,
   * otherwise just mark them as deleted.
   *
   * @return bool @c true on success.
   */
  public function deleteNote(int $blogId, bool $drop = false)
  {
    $note = new BlogEntry();
    $children = $this->findThread($blogId);
    $result = true;
    foreach ($children as $child) {
      $result = $result && $this->deleteNote($child->getId(), $drop);
    }

    $note->setId($blogId);
    if (!$drop) {
      $note->setDeleted(true);
      $note->setModified(time());
      return $this->update($note);
    } else {
      return $this->delete($note);
    }
  }

  /**
   * Fetch the array of messages referring to $id via inReplyTo.
   *
   * @param int $id
   *
   * @return array
   */
  private function findThread(int $id)
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
   *
   * @return array
   */
  private function findThreadHeads()
  {
    /** @var IQueryBuilder $qb */
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->tableName)
      ->where($qb->expr()->isNull('in_reply_to'))
      ->orderBy('priority', 'DESC')
      ->orderBy('created', 'DESC');

    return $this->findEntities($qb);
  }

  /**
   * Generate a complex tree structure reflecting the message
   * threads. This function calls itself recursively until all
   * sub-threads have been fetched.
   *
   * @param array $parent
   *
   * @return array
   */
  private function findThreadTree(array $parent)
  {
    $children = $this->findThread($parent['head']->getId());
    $parent['children'] = [];
    foreach ($children as $entity) {
      $child = $this->findThreadTree(['head' => $entity]);
      $parent['children'][] = $child;
    }
    return $parent;
  }

  /**
   * Generate a complex tree structure reflecting the message threads.
   *
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
   * @return array Tree-like nested array structure modelling the message
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
      throw new Exception("Cannot create blog thread display", $t->getCode(), $t);
    }
    $this->notificationPending('claus');
    return $data;
  }

  /**
   * Obtain "some popup notice is pending" for the given user / all users.
   *
   * @param string $userId
   *
   * @return bool
   */
  public function notificationPending(string $userId)
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('b.reader')
       ->from($this->tableName, 'b')
       ->where($qb->expr()->eq('popup', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
    $cursor = $qb->execute();
    $userPendingNotifications = false;
    while ($row = $cursor->fetch()) {
      $regex = '/(^|[,])+'.$userId.'($|[,])+/';
      if (preg_match($regex, $row['reader']) !== 1) {
        if (!$userPendingNotifications) {
          $this->logInfo('User "' . $userId . '" has pending notifications.');
        }
        $userPendingNotifications = true;
      }
    }
    $cursor->closeCursor();

    return $userPendingNotifications;
  }

  /**
   * @return bool
   *
   * @todo This does not seem to be used ...
   */
  public function needPhpSession():bool
  {
    return false;
  }
}
