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

namespace OCA\CAFEVDB\Tests\Unit\Service\Finance;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Wrapped\rotdrop\bav\BAV;

use OCA\CAFEVDB\Service\Finance\BankAccountValidator;
use OCA\CAFEVDB\Service\Finance\BankAgency;

#[Attributes\CoversClass(BankAccountValidator::class)]
#[Attributes\UsesClass(BankAgency::class)]
class BankAccountValidatorTest extends TestCase
{
  public function testIsValidBankUsesBavFacade(): void
  {
    $bav = new class {
      public function isValidBank(string $blz): bool
      {
        return $blz === '70010080';
      }
    };
    $validator = $this->validator($bav);

    self::assertTrue($validator->isValidBank('70010080'));
  }

  public function testIsValidAccountUsesBavFacade(): void
  {
    $bav = new class {
      public function isValidAccount(string $account): bool
      {
        return $account === '0030876808';
      }
    };
    $validator = $this->validator($bav);

    self::assertTrue($validator->isValidAccount('0030876808'));
  }

  public function testGetMainAgencyMapsBavAgency(): void
  {
    $bav = new class {
      public function getMainAgency(string $blz): object
      {
        return new class {
          public function getBIC(): string
          {
            return 'PBNKDEFFXXX';
          }

          public function getName(): string
          {
            return 'Postbank';
          }

          public function getCity(): string
          {
            return 'Bonn';
          }
        };
      }
    };
    $validator = $this->validator($bav);

    $agency = $validator->getMainAgency('70010080');

    self::assertSame('PBNKDEFFXXX', $agency->getBIC());
    self::assertSame('Postbank', $agency->getName());
    self::assertSame('Bonn', $agency->getCity());
  }

  private function validator(object $bav): BankAccountValidator
  {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects(self::once())
      ->method('get')
      ->with(BAV::class)
      ->willReturn($bav);

    return new BankAccountValidator($container);
  }
}
