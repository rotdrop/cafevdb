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

  const OVERLAY_FONT = 'dejavusans';
  const OVERLAY_FONTSIZE = 16;

  const FILES_KEY = 'files';
  const FOLDERS_KEY = 'folders';

  /** @var ITempManager */
  protected $tempManager;

  /** @var IL10N */
  protected $l;

  /** @var \TCPDF */
  protected $pdfGenerator;

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
    $this->initializePdfGenerator();
    $this->initializeDocumentTree();
  }

  private function initializePdfGenerator()
  {
    $pdf = new \TCPDF();
    $pdf->setPageUnit('pt');
    $pdf->setFont(self::OVERLAY_FONT);
    $pdf->setFontSize(self::OVERLAY_FONTSIZE);
    $pdf->setMargins(0, 0);
    $pdf->setCellPaddings(0, 0);
    $pdf->setAutoPageBreak(false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAlpha(0);
    $this->pdfGenerator = $pdf;
  }

  private function makePageLabel(string $path, int $pageNumber, int $pageMax)
  {
    $pdf = $this->pdfGenerator;
    $text = sprintf('%s %d / %d', $path, $pageNumber, $pageMax);
    $stringWidth = $pdf->GetStringWidth($text);
    $orientation = self::OVERLAY_FONTSIZE > $stringWidth ? 'P' : 'L';
    $pdf->startPage($orientation, [ self::OVERLAY_FONTSIZE, $stringWidth ]);
    $pdf->Text(0, self::OVERLAY_FONTSIZE, calign: 'D', valign: 'T', align: 'L');
    return $pdf->Output($text, 'S');
  }

  /** Reset the directory tree to an empty nodes array */
  private function initializeDocumentTree()
  {
    $this->documentTree = [ self::FILES_KEY => [], self::FOLDERS_KEY => [], ];
  }

  /**
   * Build as file-system like tree structure for the added documents and add
   * bookmarks. The book-mark level needs to be adjusted later as higher
   * bookmark-level can only follow lower bookmark level. So we store the
   * level of the bookmarks in their title and make an additional fix-up run
   * afterwards.
   *
   * @param string $data The PDF file data to add
   *
   * @param array $pathChain The exploded files-system path leading to $data
   *
   * @param array $tree The current nodes-array [ 'files' => FILE_NODE_ARRAY, 'folders' => FOLDER_NODE_ARRAY ]
   *
   * @param int $level Recursion level.
   *
   * @param array $bookmarks Bookmark array corresponding to $pathChain
   */
  private function addToDocumentTree(string $data, array $pathChain, array &$tree, int $level = 0)
  {
    $nodeName = array_shift($pathChain);
    if (empty($pathChain)) {
      // leaf element -- always a plain file
      $fileName = $this->tempManager->getTemporaryFile();
      file_put_contents($fileName,  $data);
      $tree[self::FILES_KEY][$nodeName] = [
        'name' => $nodeName,
        'file' => $fileName,
        'level' => $level,
      ];
    } else {
      if (!isset($tree[self::FOLDERS_KEY][$nodeName])) {
        $tree[self::FOLDERS_KEY][$nodeName] = [
          'name' => $nodeName,
          'level' => $level,
          'nodes' => [
            self::FILES_KEY => [],
            self::FOLDERS_KEY => [],
          ],
        ];
      }
      $this->addToDocumentTree($data, $pathChain, $tree[self::FOLDERS_KEY][$nodeName]['nodes'], $level + 1);
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

  /**
   * Add the file-nodes of the document-tree to the PdfTk instance. The tree
   * is traversed with folders first. Nodes of the same level or traversed in
   * alphabetical order.
   */
  private function addFromDocumentTree(PdfTk $pdfTk, array $tree, int $level = 0, array $bookmarks = [])
  {
    // first walk down the directories
    usort($tree[self::FOLDERS_KEY], fn($a, $b) => strcmp($a['name'], $b['name']));
    $first = true;
    foreach ($tree[self::FOLDERS_KEY] as $folderNode) {
      $nodeName = $folderNode['name'];
      $folderBookmarks = [
        [
          'Title' => ($level + 1). '|' . $nodeName,
          'Level' => 1,
          'PageNumber' => 1,
        ],
      ];
      if ($first) {
        $folderBookmarks = array_merge($bookmarks, $folderBookmarks);
      }
      $this->addFromDocumentTree($pdfTk, $folderNode['nodes'], $level + 1, $folderBookmarks);
      $first = false;
    }
    // then add the files from this level
    usort($tree[self::FILES_KEY], fn($a, $b) => strcmp($a['name'], $b['name']));
    foreach ($tree[self::FILES_KEY] as $fileNode) {
      $nodeName = $fileNode['name'];
      $fileName = $fileNode['file'];
      $fileData = file_get_contents($fileName);
      $nodeBookmark = [
        'Title' => ($level + 1). '|' . $nodeName,
        'Level' => 1,
        'PageNumber' => 1,
      ];
      $bookmarks[] = $nodeBookmark;

      // merge the file-start bookmarks with any existing bookmarks
      $pdfTk2 = new PdfTk('-');
      $command = $pdfTk2->getCommand();
      $command->setStdIn($fileData);
      $pdfData = (array)$pdfTk2->getData();
      $pdfData['Bookmark'] = $pdfData['Bookmark'] ?? [];
      foreach ($pdfData['Bookmark'] as &$bookmark) {
        $bookmark['Title'] = ($bookmark['Level'] + $level + 1) . '|'. $bookmark['Title'];
      }
      $pdfData['Bookmark'] = array_merge($bookmarks, $pdfData['Bookmark']);
      $pdfTk2 = new PdfTk('-'); // restart
      $command = $pdfTk2->getCommand();
      $command->setStdIn($fileData);
      $pdfTk2->updateInfo($pdfData)->saveAs($fileName);
      $bookmarks = []; // only the first file gets the directory bookmarks

      // then add the bookmared file to the outer pdftk instance
      $pdfTk->addFile($fileName);
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

    $this->initializeDocumentTree();

    return $result;
  }
}
