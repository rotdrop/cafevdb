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

namespace OCA\CAFEVDB\Hooks;

use OCP\ILogger;
use OCP\Files\Config\IMountProviderCollection;
use OCA\CAFEVDB\Storage\Database\MountProvider as DatabaseMountProvider;

class MountProviderHook
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  private const SIGNAL_CLASS = 'OC_Filesystem';
  // private const SIGNAL_NAME = 'setup';
  private const SIGNAL_NAME = 'post_initMountPoints';
  private const SIGNAL_HANDLER = 'handler';

  /** @var IMountProviderCollection */
  private $mountProviderCollection;

  /** @var DatabaseMountProvider */
  private $mountProvider;

  public function __construct(
    ILogger $logger
    , IMountProviderCollection $mountProviderCollection
    , DatabaseMountProvider $mountProvider
  ) {
    $this->logger = $logger;
    $this->mountProviderCollection = $mountProviderCollection;
    $this->mountProvider = $mountProvider;
    \OCP\Util::connectHook(self::SIGNAL_CLASS, self::SIGNAL_NAME, $this, self::SIGNAL_HANDLER);
  }

  public function handler($arguments)
  {
    //$this->logInfo(print_r($arguments, true));
    //$this->logException(new \Exception(''));
    $this->mountProviderCollection->registerProvider($this->mountProvider);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
