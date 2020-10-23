<?php

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCP\IDBConnection;

class BlogMapper extends Mapper
{
  /** @var ILogger */
  private $logger;

  public function __construct(
    IDBConnection $db
    , $appName
    , \ILogger $logger
  ) {
    parent::__construct($db, $appName);
    $this->logger = $logger;
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

    return $this->findEntities($qp);
  }

  /**
   * Fetch the top-level thread-starters, sorted by their priority.
   */
  private function findThreadHeads()
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->tableName)
       ->where($qb->expr()->eq('in_reply_to', -1))
       ->orderBy('priority', 'DESC')
       ->orderBy('created', 'DESC');

    return $this->findEntities($qp);
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
  public function fetchThreadDisplay()
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
    return $data;
  }

}
