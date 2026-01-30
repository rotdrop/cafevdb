<?php
/**
 * Copyright (c) 2006-2018 Doctrine Project
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\ConversionException;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;

use function is_resource;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_get_contents;
use function unserialize;

/**
 * Type that maps a PHP array to a clob SQL type.
 */
class ArrayType extends Type
{
  /**
   * {@inheritDoc}
   */
  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
  {
    return $platform->getClobTypeDeclarationSQL($column);
  }

  /**
   * {@inheritDoc}
   */
  public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
  {
    return serialize($value);
  }

  /**
   * {@inheritDoc}
   */
  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
  {
    if ($value === null) {
      return null;
    }

    $value = is_resource($value) ? stream_get_contents($value) : $value;

    set_error_handler(function (int $code, string $message): bool {
      if ($code === E_DEPRECATED || $code === E_USER_DEPRECATED) {
        return false;
      }

      throw ConversionException::conversionFailedUnserialization($this->getName(), $message);
    });

    try {
      return unserialize($value);
    } finally {
      restore_error_handler();
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getName()
  {
    return 'array';
  }

  /**
   * {@inheritDoc}
   */
  public function requiresSQLCommentHint(AbstractPlatform $platform)
  {
    return true;
  }
}
