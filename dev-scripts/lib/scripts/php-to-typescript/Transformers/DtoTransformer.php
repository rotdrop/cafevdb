<?php
/**
 * Some PHP utility functions for Nextcloud apps.
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
 */

namespace OCA\RotDrop\DevScripts\PhpToTypeScript\Transformers;

use OCA\RotDrop\DevScripts\PhpToTypeScript\Actions\TranspileTypeToTypeScriptAction;
use OCA\RotDrop\DevScripts\PhpToTypeScript\Structures\TransformedTypeOrConstant;
use OCA\RotDrop\DevScripts\PhpToTypeScript\TemplateParameters;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Nullable;
use Spatie\TypeScriptTransformer\Attributes\Hidden;
use Spatie\TypeScriptTransformer\Attributes\Optional;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\DtoTransformer as BaseTransformer;
use Spatie\TypeScriptTransformer\TypeProcessors\DtoCollectionTypeProcessor;
use Spatie\TypeScriptTransformer\TypeProcessors\ReplaceDefaultsTypeProcessor;
use Spatie\TypeScriptTransformer\TypeProcessors\TypeProcessor;
use Spatie\TypeScriptTransformer\TypeReflectors\TypeReflector;
use Spatie\TypeScriptTransformer\Types\TypeScriptType;

/**
 * Differences to upstream:
 *
 * - template parameters through the TemplateParameters attribute
 * - properties are indented and the nullable-optional decision honours the doc-block
 * - inline type replacements are used in the type processors
 * - "unknown" instead of "any"
 */
class DtoTransformer extends BaseTransformer
{
  /** {@inheritdoc} */
  public function transform(ReflectionClass $class, string $name): ?TransformedType
  {
    if (!$this->canTransform($class)) {
      return null;
    }

    $missingSymbols = new MissingSymbolsCollection();

    $type = join([
      $this->transformProperties($class, $missingSymbols),
      $this->transformMethods($class, $missingSymbols),
      $this->transformExtra($class, $missingSymbols),
    ]);

    $templateTypes = [];
    foreach ($class->getAttributes(TemplateParameters::class) as $reflectionAttribute) {
      $templateTypes = array_merge($templateTypes, $reflectionAttribute->newInstance()->parameters);
    }

    return TransformedTypeOrConstant::create(
      $class,
      $name,
      '{' . PHP_EOL . $type . '  }',
      $missingSymbols,
      templateTypes: $templateTypes,
    );
  }

  /** {@inheritdoc} */
  protected function transformProperties(
    ReflectionClass $class,
    MissingSymbolsCollection $missingSymbols
  ): string {
    $isClassOptional = !empty($class->getAttributes(Optional::class));
    $nullablesAreOptional = $this->config->shouldConsiderNullAsOptional();

    return array_reduce(
      $this->resolveProperties($class),
      function (string $carry, ReflectionProperty $property) use ($isClassOptional, $missingSymbols, $nullablesAreOptional) {
        $isHidden = !empty($property->getAttributes(Hidden::class));

        if ($isHidden) {
          return $carry;
        }

        $type = $this->reflectionToType(
          $property,
          $missingSymbols,
          ...$this->typeProcessors()
        );

        if ($type === null) {
          return $carry;
        }

        $isOptional = $isClassOptional
          || !empty($property->getAttributes(Optional::class))
          || (($property->getType()?->allowsNull() ?? ($type instanceof Nullable)) && $nullablesAreOptional);

        $transformed = $this->typeToTypeScript(
          $type,
          $missingSymbols,
          $isOptional,
          $property->getDeclaringClass()?->getName(),
        );

        $propertyName = $this->transformPropertyName($property, $missingSymbols);

        return $isOptional
          ? "{$carry}    {$propertyName}?: {$transformed};" . PHP_EOL
          : "{$carry}    {$propertyName}: {$transformed};" . PHP_EOL;
      },
      ''
    );
  }

  /** {@inheritdoc} */
  protected function typeProcessors(): array
  {
    return [
      new ReplaceDefaultsTypeProcessor(
        $this->config->getDefaultInlineTypeReplacements()
      ),
      new DtoCollectionTypeProcessor(),
    ];
  }

  /**
   * Same as upstream, but fall back to "unknown" instead of "any".
   *
   * {@inheritdoc}
   */
  protected function reflectionToType(
    ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection,
    MissingSymbolsCollection $missingSymbolsCollection,
    TypeProcessor ...$typeProcessors
  ): ?Type {
    $reflector = TypeReflector::new($reflection);
    $type = $reflector->reflectionFromAttribute()
      ?? $reflector->reflectFromDocblock()
      ?? $reflector->reflectFromReflection()
      ?? new TypeScriptType('unknown');

    foreach ($typeProcessors as $processor) {
      $type = $processor->process($type, $reflection, $missingSymbolsCollection);

      if ($type === null) {
        return null;
      }
    }

    return $type;
  }

  /** {@inheritdoc} */
  protected function typeToTypeScript(
    Type $type,
    MissingSymbolsCollection $missingSymbolsCollection,
    bool $nullablesAreOptional = false,
    ?string $currentClass = null,
  ): string {
    $transpiler = new TranspileTypeToTypeScriptAction(
      $missingSymbolsCollection,
      $nullablesAreOptional,
      $currentClass,
    );

    return $transpiler->execute($type);
  }
}
