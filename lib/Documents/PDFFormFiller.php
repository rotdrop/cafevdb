<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\Files\File;

class PDFFormFiller
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var PdfTk */
  private $pdfTk = null;

  public function __construct(ILogger $logger)
  {
    $this->logger = $logger;
  }

  /**
   * Fill in the given fields.
   *
   * @parm mixed $data
   *
   * @param array $fields Array of simple KEY => VALUE pairs.
   *
   * @return PDFFormFiller
   */
  public function fill($data, array $fields):PDFFormFiller
  {
    $this->pdfTk = new PdfTk('-');
    $command = $this->pdfTk->getCommand();

    if ($data instanceof File) {
      $data = $data->getContent();
    } else if (!is_string($data)) {
      $data = (string)$data;
    }

    if (!is_string($data)) {
      throw new \RuntimeException('$data argument not convertible to string');
    }

    $command->setStdIn($data);

    $result = $this->pdfTk
      ->fillForm($fields)
      ->needAppearances()
      ->execute();

    if ($result === false) {
      $this->logError('ERROR? ' . $this->pdfTk->getError());
    }

    return $this;
  }

  public function getContent()
  {
    if (empty($this->pdfTk)) {
      return null;
    }
    return file_get_contents((string)$this->pdfTk->getTmpFile());
  }
}
