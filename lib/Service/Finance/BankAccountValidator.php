<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Wrapped\rotdrop\bav\BAV;

/**
 * Thin adapter around the German bank account validator BAV facade. The
 * BAV class is only referenced here via ::class, which does not trigger
 * autoloading, so that this class can be mocked or stubbed without the BAV
 * library being installed.
 */
class BankAccountValidator
{
  /** {@inheritdoc} */
  public function __construct(
    private ContainerInterface $appContainer,
  ) {
  }

  /** @return object The BAV service. */
  private function bav(): object
  {
    return $this->appContainer->get(BAV::class);
  }

  /**
   * @param string $blz German bank code.
   *
   * @return bool
   */
  public function isValidBank(string $blz): bool
  {
    return (bool)$this->bav()->isValidBank($blz);
  }

  /**
   * @param string $account German bank account number.
   *
   * @return bool
   */
  public function isValidAccount(string $account): bool
  {
    return (bool)$this->bav()->isValidAccount($account);
  }

  /**
   * @param string $blz German bank code.
   *
   * @return BankAgency
   *
   * @throws \Exception The BAV library throws if the BLZ is unknown or the
   * data backend fails, it never returns null.
   */
  public function getMainAgency(string $blz): BankAgency
  {
    $agency = $this->bav()->getMainAgency($blz);
    return new BankAgency(
      bic: (string)$agency->getBIC(),
      name: (string)$agency->getName(),
      city: (string)$agency->getCity(),
    );
  }
}
