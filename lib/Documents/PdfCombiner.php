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

use mikehaertl\pdftk\Pdf as PdfTk;

use OCP\ILogger;
use OCP\IL10N;
use OCP\ITempManager;

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

  public function __construct(
    ITempManager $tempManager
    , ILogger $logger
    , IL10N $l
  ) {
    $this->tempManager = $tempManager;
    $this->logger = $logger;
    $this->l = $l;
  }

  public function addDocument(string $data, ?string $name = null)
  {
    if (empty($name)) {
      $name = Uuid::create();
    }

    $file = $this->tempManager->getTemporaryFile();

    $pdfTk = new PdfTk('-');
    $command = $pdfTk->getCommand();
    $command->setStdIn($data);
    $pdfData = (array)$pdfTk->getData();
    $this->logInfo('PDF DATA FOR ' . $name . ': ' . print_r($pdfData, true));

    $pdfData['Bookmark'] = $pdfData['Bookmark'] ?? [];
    foreach ($pdfData['Bookmark'] as &$bookmark) {
      $bookmark['Level'] = $bookmark['Level'] + 1;
    }
    $pdfData['Bookmark'][] = [
      'Title' => basename($name),
      'Level' => 1,
      'PageNumber' => 1,
    ];

    file_put_contents($file, $data);

    // Does not work.
    // $pdfTk = new PdfTk($file);
    // $pdfTk->updateInfo($pdfData);

    $this->logInfo('ADDING ' . $file . '@' . $name . ': ' . strlen($data));
    $this->documents[$name] = $file;
  }

  public function combine():string
  {
    $pdfTk = new PdfTk(array_values($this->documents));
    $result = $pdfTk->cat()->toString();

    if ($result === false) {
      throw new \RuntimeException(
        $this->l->t('Combining PDFs failed')
        . $pdfTk->getCommand()->getStdErr()
      );
    }

    foreach ($this->documents as $name => $file) {
      unlink($file);
    }
    return $result;
  }
}
