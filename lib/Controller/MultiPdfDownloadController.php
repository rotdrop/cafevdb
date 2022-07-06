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

use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Make the stored personal data accessible for the web-interface. This is
 * meant for newer parts of the web-interface in contrast to the legacy PME
 * stuff.
 */
class MultiPdfDownloadController extends Controller
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var UserStorage */
  private $userStorage;

  /** @var IL10N */
  protected $l;

  public function __construct(
    string $appName
    , IRequest $request
    , IL10N $l10n
    , ILogger $logger
    , UserStorage $userStorage
  ) {
    parent::__construct($appName, $request);
    $this->l = $l10n;
    $this->logger = $logger;
    $this->userStorage = $userStorage;
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
    $folder = urldecode($folder);
    $this->logInfo('FOLDER ' . urldecode($folder));

    $dirNode = $this->userStorage->getFolder($folder);

    /** @var FileSystemNode $node */
    foreach ($dirNode->getDirectoryListing() as $node) {
      $this->logInfo('NODE ' . $node->getInternalPath());
      if ($node->getType() != FileInfo::TYPE_FILE) {
        continue; // we do not convert recursively
      }
    }

    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
