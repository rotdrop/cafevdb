<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Documents;

use mikehaertl\pdftk\Pdf as PdfTk;

use OCP\Files\File;

class PDFFormFiller
{
  /** @var PdfTk */
  private $pdfTk;

  public function __construct()
  {
    // @todo check what $pdfData is ...
    $this->pdfTk = new PdfTk('-');
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

    $this->pdfTk
      ->fillForm($fields)
      ->needAppearances()
      ->execute();
    return $this;
  }

  public function getContent()
  {
    // $this->pdfTk->execute();
    return file_get_contents((string)$this->pdfTk->getTmpFile());
  }
}
