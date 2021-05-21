<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage;

use OCP\IL10N;
use OCP\ILogger;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class DatabaseStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const PATH_SEP = '/';

  /** @var string */
  protected $appName;

  public function __construct(
    $appName
    , EntityManager $entityManager
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function get($fileIdentifier):?Entities\File
  {
    if ($fileIdentifier instanceof Entities\File) {
      return $fileIdentifier;
    }
    try {
      $id = filter_var($fileIdentifier, FILTER_VALIDATE_INT, ['min_range' => 1]);
      if ($id !== false) {
        return $this->getDatabaseRepository(Entities\File::class)->find($id);
      } else if (is_string($fileIdentifier)) {
        return $this->getDatabaseRepository(Entities\File::class)->findOneBy([ 'fileName' => $fileIdentifier ]);
      }
    } catch (\Throwable $t) {
      // nothing
    }
    return null;
  }

  public function getDownloadLink($fileIdentifier)
  {
    $file = $this->get($fileIdentifier);
    $id = $file->getId();

    $urlGenerator = \OC::$server->getURLGenerator();
    $filesUrl = $urlGenerator->inkToRoute(
      $this->appName.'.downloads.get', [
        'section' => 'database',
        'object' => $id,
      ])
              . '?requesttoken=' . urlencode(\OCP\Util::callRegister());
    return $filesUrl;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
