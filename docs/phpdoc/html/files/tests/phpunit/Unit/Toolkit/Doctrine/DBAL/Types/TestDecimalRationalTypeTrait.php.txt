<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational;
use OCA\CAFEVDB\Toolkit\Common\RationalNumber;
use OCA\CAFEVDB\Toolkit\Doctrine\DBAL\Types\AbstractDecimalRationalType;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\ConversionException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Exception\SerializationFailed;
use OCA\RotDrop\Tests\DeprecationException;

/** Test quasi-fixed-point numbers. */
trait TestDecimalRationalTypeTrait
{
  private AbstractPlatform $dbPlatform;

  private int $precision;

  private int $scale;

  /** {@inheritdoc} */
  public function setup(): void
  {
    error_reporting(E_ALL);
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->precision = (self::NUMBER_CLASS)::PRECISION;
    $this->scale = (self::NUMBER_CLASS)::SCALE;

    $this->dbPlatform = $this->createStub(AbstractPlatform::class);
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testGetName(): void
  {
    if (defined(self::DATABASE_TYPE_CLASS . '::NAME')) {
      $expected = (self::DATABASE_TYPE_CLASS)::NAME;
    } else {
      $expected = implode('_', [AbstractDecimalRationalType::NAME_BASE, $this->precision, $this->scale]);
    }
    $this->assertEquals($expected, new (self::DATABASE_TYPE_CLASS)()->getName());
  }

  /** @return void */
  public function testGetSqlDeclaration(): void
  {
    $platform = $this->getMockBuilder(AbstractPlatform::class)
      ->disableOriginalConstructor()
      ->getMock();
    $platform->expects($this->atLeastOnce())
      ->method('getDecimalTypeDeclarationSQL')
      ->willReturnCallback(fn(array $column) => "DECIMAL({$column['precision']},{$column['scale']})");
    $expected = 'DECIMAL(' . $this->precision .  ',' . $this->scale . ')';
    $this->assertEquals(
      $expected,
      new (self::DATABASE_TYPE_CLASS)()->getSQLDeclaration([], $platform),
    );
  }

  /**
   * @param int $whole
   *
   * @param int $fraction
   *
   * @return string
   */
  private function padDecimal(int $whole, int $fraction): string
  {
    $whole = (string)$whole;
    if ($whole != 0) {
      $this->assertLessThanOrEqual($this->precision - $this->scale, strlen($whole));
    }
    $fraction = (string)$fraction;
    return $whole . '.' . str_pad($fraction, $this->scale, '0');
  }


  /** @return void */
  public function testConvertToPhpValue(): void
  {
    $this->assertNull(new (self::DATABASE_TYPE_CLASS)()->convertToPHPValue(null, $this->dbPlatform));
    $this->assertNull(new (self::DATABASE_TYPE_CLASS)()->convertToPHPValue('', $this->dbPlatform));
    $values = [
      [RationalNumber::create(0, 1, 2), $this->padDecimal(0, 5)],
      ['0.5', $this->padDecimal(0, 5)],
    ];
    if ($this->precision - $this->scale >= 1) {
      $values[] = [RationalNumber::create(1, 1, 2), $this->padDecimal(1, 5)];
      $values[] = ['1.5', $this->padDecimal(1, 5)];
    }
    foreach ($values as $testCase) {
      $phpValue = new (self::DATABASE_TYPE_CLASS)()->convertToPHPValue($testCase[0], $this->dbPlatform);
      $this->assertInstanceOf(self::NUMBER_CLASS, $phpValue);
      $this->assertEquals($testCase[1], $phpValue->jsonSerialize());
    }
    $number = (self::NUMBER_CLASS)::create(1, 2, 3);
    $this->assertTrue($number === new (self::DATABASE_TYPE_CLASS)()->convertToPHPValue($number, $this->dbPlatform));
  }

  /** @return void */
  public function testConvertToDatabaseValue(): void
  {
    $values = [
      [(self::NUMBER_CLASS)::create(0, 1, 2), $this->padDecimal(0, 5)],
      [RationalNumber::create(0, 1, 2), $this->padDecimal(0, 5)],
      [0.5, $this->padDecimal(0, 5)],
    ];
    if ($this->precision - $this->scale >= 1) {
      $values[] = [(self::NUMBER_CLASS)::create(1, 1, 2), $this->padDecimal(1, 5)];
      $values[] = [RationalNumber::create(1, 1, 2), $this->padDecimal(1, 5)];
      $values[] = ['1.5', $this->padDecimal(1, 5)];
      $values[] = [1.5, $this->padDecimal(1, 5)];
    }
    if ($this->precision - $this->scale >= 2) {
      $values[] = ['42', $this->padDecimal(42, 0)];
    }
    foreach ($values as $testCase) {
      $databaseValue = new (self::DATABASE_TYPE_CLASS)()->convertToDatabaseValue($testCase[0], $this->dbPlatform);
      $this->assertEquals($testCase[1], $databaseValue);
    }
    $outOfBoundsValue = str_repeat('9', 1 + $this->precision - $this->scale) . '.' . str_repeat('9', $this->scale);
    $this->expectException(SerializationFailed::class);
    new (self::DATABASE_TYPE_CLASS)()->convertToDatabaseValue($outOfBoundsValue, $this->dbPlatform);
  }
}
