<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024 Claus-Justus Heine
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

use RuntimeException;

use mikehaertl\pdftk\Pdf as PdfTk;

use Psr\Log\LoggerInterface as ILogger;
use OCP\Files\File;

/** Fill a PDF-form with given data. */
class PDFFormFiller
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var PdfTk */
  private $pdfTk = null;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
  ) {
  }
  // phpcs:enable

  /**
   * Fill in the given fields.
   *
   * @param mixed $data
   *
   * @param array $fields Array of simple KEY => VALUE pairs.
   *
   * @return PDFFormFiller
   */
  public function fill(mixed $data, array $fields):PDFFormFiller
  {
    $this->pdfTk = new PdfTk('-');
    $command = $this->pdfTk->getCommand();

    if ($data instanceof File) {
      $data = $data->getContent();
    } elseif (!is_string($data)) {
      $data = (string)$data;
    }

    if (!is_string($data)) {
      throw new RuntimeException('$data argument not convertible to string');
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

  /**
   * Get the content of the filled PDF as string.
   *
   * @return string
   */
  public function getContent():string
  {
    if (empty($this->pdfTk)) {
      return null;
    }
    return file_get_contents((string)$this->pdfTk->getTmpFile());
  }
}
