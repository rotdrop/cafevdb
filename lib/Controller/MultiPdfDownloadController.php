<?php
/**
 * Orchestra member, musician and project management application.
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
/**
 * @file Handle downloads requests for directory contents as multi-page PDF.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCP\Files\Node as FileSystemNode;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Documents\AnyToPdf;
use OCA\CAFEVDB\Documents\PdfCombiner;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Walk throught a directory tree, convert all files to PDF and combine the
 * resulting PDFs into a single PDF. Present this as download response.
 */
class MultiPdfDownloadController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var UserStorage */
  private $userStorage;

  /** @var PdfCombiner */
  private $pdfCombiner;

  /** @var AnyToPdf */
  private $anyToPdf;

  public function __construct(
    string $appName
    , IRequest $request
    , ConfigService $configService
    , UserStorage $userStorage
    , PdfCombiner $pdfCombiner
    , AnyToPdf $anyToPdf
  ) {
    parent::__construct($appName, $request);
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->userStorage = $userStorage;
    $this->pdfCombiner = $pdfCombiner;
    $this->anyToPdf = $anyToPdf;
  }

  private function addFilesRecursively(Folder $folder, string $parentName = '')
  {
    $parentName = $parentName . '::' . $folder->getName();
    /** @var FileSystemNode $node */
    foreach ($folder->getDirectoryListing() as $node) {
      if ($node->getType() != FileInfo::TYPE_FILE) {
        $this->addFilesRecursively($node, $parentName);
      } else {
        /** @var File $node */
        $this->pdfCombiner->addDocument(
          $this->anyToPdf->convertData($node->getContent(), $node->getMimeType()),
          $parentName . '::' . $node->getName()
        );
      }
    }
  }

  /**
   * Download the contents (plain-files only, non-recursive) of the given
   * folder as multi-page PDF after converting everything to PDF.
   *
   * @NoAdminRequired
   * @return Response
   */
  public function get(string $folder):Response
  {
    $folderPath = urldecode($folder);

    $folder = $this->userStorage->getFolder($folderPath);
    $this->addFilesRecursively($folder);

    $fileName = basename($folderPath) . '.pdf';

    return self::dataDownloadResponse($this->pdfCombiner->combine(), $fileName, 'application/pdf');
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
