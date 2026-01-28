<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Traits;

use BadMethodCallException;
use Error;
use InvalidArgumentException;
use Throwable;
use ValueError;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IL10N;

use OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait;

/** Example enum for testing. */
enum TranslatableEnumExample: string
{
  use TranslatableEnumTrait;

  case ONE = 'one';
  case TWO = 'two';
}

/** Example enum for testing. */
enum TranslatableEnumExampleL10NOverride: string
{
  use TranslatableEnumTrait;

  case ONE = 'one';
  case TWO = 'two';

  /** {@inheritdoc} */
  public static function l10nTag(): string
  {
    return self::L10N_TAG . '_MY_OWN_ADDITION: ';
  }
}

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversTrait(TranslatableEnumTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
class TranslatableEnumTraitTest extends TestCase
{
  private IL10N $l10n;

  private const TRANSLATIONS = [
    'one' => 'eins',
    'two' => 'zwei',
  ];

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->l10n = $this->getMockBuilder(IL10N::class)
      ->getMock();
  }

  /** @return void */
  public function testGetL10NValues(): void
  {
    $this->l10n->expects($this->exactly(4))->method('t')->willReturnCallback(
      fn(string $arg) => self::TRANSLATIONS[$arg] ?? $arg,
    );
    $enumValues = TranslatableEnumExample::values();
    $expected = array_combine(
      $enumValues,
      array_map(
        fn(string $s) => self::TRANSLATIONS[$s] ?? $s,
        $enumValues,
      ),
    );
    $values = TranslatableEnumExample::getL10NValues($this->l10n);
    $this->assertEquals($expected, $values);
  }

  /** @return void */
  public function testGetL10NTags(): void
  {
    $this->l10n->expects($this->exactly(4))->method('t')->willReturnCallback(
      fn(string $arg) => self::TRANSLATIONS[$arg] ?? $arg,
    );
    $this->assertEquals(
      TranslatableEnumExample::L10N_TAG . '_MY_OWN_ADDITION: ',
      TranslatableEnumExampleL10NOverride::l10nTag(),
    );
    $this->l10n
      ->expects($this->atLeastOnce(TranslatableEnumExample::L10N_TAG . '_MY_OWN_ADDITION: ' . 'one'))
      ->method('t');
    TranslatableEnumExampleL10NOverride::getL10NValues($this->l10n);
  }

  /** @return void */
  public function testTranslationByMethodT(): void
  {
    $this->l10n->expects($this->exactly(4))->method('t')->willReturnCallback(
      fn(string $arg) => self::TRANSLATIONS[$arg] ?? $arg,
    );
    foreach (TranslatableEnumExample::cases() as $case) {
      $this->assertEquals(self::TRANSLATIONS[$case->value], $case->t($this->l10n));
    }
  }

  /** @return void */
  public function testTaggedTranslationByMethodT(): void
  {
    $this->l10n->expects($this->exactly(2))->method('t')->willReturnCallback(
      fn(string $arg) => self::TRANSLATIONS[substr($arg, strlen(TranslatableEnumExample::l10nTag()))] ?? $arg,
    );
    foreach (TranslatableEnumExample::cases() as $case) {
      $this->assertEquals(self::TRANSLATIONS[$case->value], $case->t($this->l10n));
    }
  }

  /** @return void */
  public function testTranslationToStringByStaticCallMagicMethod(): void
  {
    $this->l10n->expects($this->exactly(4))->method('t')->willReturnCallback(
      fn(string $arg) => self::TRANSLATIONS[$arg] ?? $arg,
    );
    foreach (TranslatableEnumExample::toArray() as $case => $value) {
      $this->assertEquals($value, TranslatableEnumExample::{$case}());
    }
    try {
      TranslatableEnumExample::BLAH();
    } catch (Throwable $t) {
      $this->assertInstanceOf(BadMethodCallException::class, $t);
    }
    try {
      TranslatableEnumExample::ONE('never');
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
    }
    foreach (TranslatableEnumExample::toArray() as $case => $value) {
      $this->assertEquals(self::TRANSLATIONS[$value], TranslatableEnumExample::{$case}($this->l10n));
    }
  }

  /** @return void */
  public function testTaggedTranslationToStringByStaticCallMagicMethod(): void
  {
    $this->l10n->expects($this->exactly(2))->method('t')->willReturnCallback(
      fn(string $arg) => self::TRANSLATIONS[substr($arg, strlen(TranslatableEnumExample::l10nTag()))] ?? $arg,
    );
    foreach (TranslatableEnumExample::toArray() as $case => $value) {
      $this->assertEquals($value, TranslatableEnumExample::{$case}());
    }
    try {
      TranslatableEnumExample::BLAH();
    } catch (Throwable $t) {
      $this->assertInstanceOf(BadMethodCallException::class, $t);
    }
    try {
      TranslatableEnumExample::ONE('never');
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
    }
    foreach (TranslatableEnumExample::toArray() as $case => $value) {
      $this->assertEquals(self::TRANSLATIONS[$value], TranslatableEnumExample::{$case}($this->l10n));
    }
  }
}
