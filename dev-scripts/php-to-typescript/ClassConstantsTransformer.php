<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\DevScripts\PhpToTypeScript;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionProperty;

use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

/** Transform class constants to TypeScript constants. */
class ClassConstantsTransformer implements Transformer
{
  /** {@inheritdoc} */
  public function __construct(
    protected ClassConstantsTransformerConfig $config,
  ) {
  }

  /** {@inheritdoc} */
  public function transform(ReflectionClass $class, string $name):null|TransformedType|TypesCollection
  {
    if (!$this->canTransform($class)) {
      return null;
    }

    $constants = $this->resolveConstants($class);

    if ($this->config->emitConstantsAsConstants()) {
      $collection = new TypesCollection();

      /** @var ReflectionClassConstant $constant */
      foreach ($constants as $constant) {
        $name = $constant->getName();
        $value = $constant->getValue();
        // @todo: also support non-scalars
        if (!is_scalar($value)) {
          continue;
        }
        if (is_string($value)) {
          if (str_contains($value, "\n")) {
            $value = "`" . $value . "`";
          } else {
            $value = "'" . $value . "'";
          }
        }
        $constantType = TransformedTypeOrConstant::create(
          $class,
          $constant->getName(),
          $value,
          keyword: 'const',
        );
        $collection[$class->getName() . '.' . $constant->getName()] = $constantType;
      }

      return $collection;
    } else {
      $properties = $this->transformConstantsToProperties($class);

      return TransformedType::create(
        $class,
        $name,
        "{" . PHP_EOL . $properties . "}",
        keyword: 'interface',
      );
    }
  }

  /**
   * Transform constants into literal type typed members.
   *
   * @param ReflectionClass $class
   *
   * @return string
   */
  protected function transformConstantsToProperties(ReflectionClass $class): string
  {
    return array_reduce(
      $this->resolveConstants($class),
      function (string $carry, ReflectionClassConstant $constant) {
        $value = $constant->getValue();
        // @todo: also support non-scalars
        if (!is_scalar($value)) {
          return $carry;
        }
        if (is_string($value)) {
          if (str_contains($value, "\n")) {
            $value = "`" . $value . "`";
          } else {
            $value = "'" . $value . "'";
          }
        }
        $name = $constant->getName();
        return "{$carry}    {$name}: $value," . PHP_EOL;
      },
      '',
    );
  }

  /** {@inheritdoc} */
  protected function canTransform(ReflectionClass $class): bool
  {
    // This is for const-only classes.
    return count($this->resolveProperties($class)) == 0;
  }

  /** {@inheritdoc} */
  protected function resolveConstants(ReflectionClass $class): array
  {
    return $class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);
  }

  /** {@inheritdoc} */
  protected function resolveProperties(ReflectionClass $class): array
  {
    $properties = array_filter(
      $class->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => ! $property->isStatic()
    );

    return array_values($properties);
  }
}
