<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 */

namespace OCA\CAFEVDB\Documents;

use OCP\ILogger;
use OCP\IL10N;
use OCP\ITempManager;

use OCA\CAFEVDB\Documents\Util\PdfTk;
use OCA\CAFEVDB\Common\Uuid;

/**
 * A class which combines several PDFs into one.
 */
class PdfCombiner
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var ITempManager */
  protected $tempManager;

  /** @var IL10N */
  protected $l;

  /**
   * @var array
   * The documents-data to be combined into one document.
   */
  private $documents = [];

  private $documentTree = [];

  public function __construct(
    ITempManager $tempManager
    , ILogger $logger
    , IL10N $l
  ) {
    $this->tempManager = $tempManager;
    $this->logger = $logger;
    $this->l = $l;
  }

  /**
   * Build as file-system like tree structure for the added documents and add
   * bookmarks. The book-mark level needs to be adjusted later as higher
   * bookmark-level can only follow lower bookmark level. So we store the
   * level of the bookmarks in their title and make an additional fix-up run
   * afterwards.
   */
  private function addToDocumentTree(string $data, array $pathChain, array &$tree, int $level = 0, array $bookmarks = [])
  {
    // [ NODE1 NODE2 ... ]


    $nodeName = array_shift($pathChain);
    $nodeBookmark = [
      'Title' => ($level + 1). '|' . $nodeName,
      'Level' => 1,
      'PageNumber' => 1,
    ];
    if (empty($pathChain)) {
      // leaf element
      $file = $this->tempManager->getTemporaryFile();
      $pdfTk = new PdfTk('-');
      $command = $pdfTk->getCommand();
      $command->setStdIn($data);
      $pdfData = (array)$pdfTk->getData();

      $pdfData['Bookmark'] = $pdfData['Bookmark'] ?? [];
      foreach ($pdfData['Bookmark'] as &$bookmark) {
        $bookmark['Title'] = ($bookmark['Level'] + $level +1) . '|'. $bookmark['Title'];
      }
      if (empty($tree)) {
        $bookmarks[] = $nodeBookmark;
      } else {
        $bookmarks = [ $nodeBookmark ];
      }
      $pdfData['Bookmark'] = array_merge($bookmarks, $pdfData['Bookmark']);
      $pdfTk = new PdfTk('-');
      $command = $pdfTk->getCommand();
      $command->setStdIn($data);
      $pdfTk->updateInfo($pdfData)->saveAs($file);

      $tree[$nodeName] = [
        'type' => 'file',
        'name' => $nodeName,
        'data' => $file,
        'level' => $level,
      ];
    } else {
      if (!isset($tree[$nodeName])) {
        $tree[$nodeName] = [
          'type' => 'folder',
          'name' => $nodeName,
          'level' => $level,
          'nodes' => [],
        ];
        $bookmarks[] = $nodeBookmark;
      }
      $this->addToDocumentTree($data, $pathChain, $tree[$nodeName]['nodes'], $level + 1, $bookmarks);
    }
  }

  public function addDocument(string $data, ?string $name = null)
  {
    if (empty($name)) {
      $name = Uuid::create();
    }

    $name = trim(preg_replace('|//+|', '/', $name), '/');
    $pathChain = explode('/', $name);

    $this->addToDocumentTree($data, $pathChain, $this->documentTree);
  }

  private function addFromDocumentTree(PdfTk $pdfTk, array $tree)
  {
    foreach ($tree as $node) {
      if ($node['type'] == 'file') {
        $pdfTk->addFile($node['data']);
      } else {
        $this->addFromDocumentTree($pdfTk, $node['nodes']);
      }
    }
  }

  public function combine():string
  {
    $pdfTk = new PdfTk;
    $this->addFromDocumentTree($pdfTk, $this->documentTree);
    $result = $pdfTk->cat()->toString();

    if ($result === false) {
      throw new \RuntimeException(
        $this->l->t('Combining PDFs failed')
        . $pdfTk->getCommand()->getStdErr()
      );
    }

    $pdfTk = new PdfTk('-');
    $command = $pdfTk->getCommand();
    $command->setStdIn($result);
    $pdfData = (array)$pdfTk->getData();

    foreach ($pdfData['Bookmark'] as &$bookmark) {
      list($level, $title) = explode('|', $bookmark['Title'], 2);
      $bookmark['Title'] = $title;
      $bookmark['Level'] = $level;
    }

    $pdfTk = new PdfTk('-');
    $command = $pdfTk->getCommand();
    $command->setStdIn($result);
    $result = $pdfTk->updateInfo($pdfData)->toString();

    if ($result === false) {
      throw new \RuntimeException(
        $this->l->t('Combining PDFs failed')
        . $pdfTk->getCommand()->getStdErr()
      );
    }

    $this->documentTree = [];

    return $result;
  }
}
