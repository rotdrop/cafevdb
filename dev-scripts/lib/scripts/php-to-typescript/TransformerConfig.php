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

namespace OCA\RotDrop\DevScripts\PhpToTypeScript;

use OCA\RotDrop\DevScripts\PhpToTypeScript\Collectors\DefaultCollector;
use OCA\RotDrop\DevScripts\PhpToTypeScript\Writers\TypeDefinitionWriter;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\TypeResolver;
use Spatie\TypeScriptTransformer\Exceptions\InvalidDefaultTypeReplacer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Spatie\TypeScriptTransformer\Writers\Writer;

/**
 * Extend the default configuration class and add further options.
 */
class TransformerConfig extends TypeScriptTransformerConfig
{
  private bool $constantsAsConstants = true;

  private ?string $scopedNamespacePrefix = null;

  private array $scopedNamespaces = [];

  private string $appNamespace = 'RotDrop';

  private array $autoDiscoverExcludePaths = [];

  private ?string $autoDiscoverExcludeRegExp = null;

  private array $defaultInlineTypeReplacements = [];

  private array $collectorClasses = [DefaultCollector::class];

  /** {@inheritdoc} */
  public static function create(): static
  {
    return new static();
  }

  /**
   * Exclude all files with a path starting with one of the given prefixes.
   *
   * @param string ...$paths
   *
   * @return static
   */
  public function autoDiscoverExcludePaths(string ...$paths): static
  {
    $this->autoDiscoverExcludePaths = array_merge($this->autoDiscoverExcludePaths, $paths);

    return $this;
  }

  /**
   * Exclude all files with a path matching the given regular expression.
   *
   * @param string $regExp
   *
   * @return static
   */
  public function autoDiscoverExcludeRegExp(string $regExp): static
  {
    $this->autoDiscoverExcludeRegExp = $regExp;

    return $this;
  }

  /** @return array */
  public function getAutoDiscoverExcludePaths(): array
  {
    return $this->autoDiscoverExcludePaths;
  }

  /** @return null|string */
  public function getAutoDiscoverExcludeRegExp(): ?string
  {
    return $this->autoDiscoverExcludeRegExp;
  }

  /**
   * The parent class always appends the upstream default collector, so keep
   * track of the collectors ourselves in order to append our own variant.
   *
   * {@inheritdoc}
   */
  public function collectors(array $collectors): static
  {
    $this->collectorClasses = array_merge($collectors, [DefaultCollector::class]);

    return $this;
  }

  /** {@inheritdoc} */
  public function getCollectors(): array
  {
    return array_map(
      fn (string $collector) => new $collector($this),
      $this->collectorClasses,
    );
  }

  /** {@inheritdoc} */
  public function getWriter(): Writer
  {
    return new TypeDefinitionWriter();
  }

  /**
   * Set the app-namespace.
   *
   * @param string $appNamespace
   *
   * @return static
   */
  public function appNamespace(string $appNamespace): static
  {
    $appNamespace = trim($appNamespace, '\\');
    if (str_starts_with($appNamespace, 'OCA\\')) {
      $appNamespace = substr($appNamespace, strlen('OCA\\'));
    }
    $this->appNamespace = $appNamespace;

    return $this;
  }

  /**
   * The namespace prefix for classes wrapped by humbug/php-scoper.
   *
   * @param ?string $scopedNamespacePrefix
   *
   * @return static
   */
  public function scopedNamespacePrefix(?string $scopedNamespacePrefix): static
  {
    $this->scopedNamespacePrefix = $scopedNamespacePrefix;

    return $this;
  }

  /**
   * A list of namespaces wrapped by humbug/php-scoper.
   *
   * @param array $scopedNamespaces
   *
   * @return static
   */
  public function scopedNamespaces(array $scopedNamespaces): static
  {
    $this->scopedNamespaces = $scopedNamespaces;

    return $this;
  }

  /**
   * Define replacements which are inlined at the place of use.
   *
   * @param array $replacements
   *
   * @return static
   */
  public function defaultInlineTypeReplacements(array $replacements): static
  {
    if ($this->scopedNamespacePrefix !== null) {
      foreach ($replacements as $class => $replacement) {
        foreach ($this->scopedNamespaces as $namespace) {
          if (str_starts_with($class, $namespace)) {
            $replacements[$this->scopedNamespacePrefix . '\\' . $class] = $replacement;
            unset($replacements[$class]);
            break;
          }
        }
      }
    }
    $this->defaultInlineTypeReplacements = $replacements;
    return $this;
  }

  /**
   * Like getDefaultTypeReplacements() for the inline replacements.
   *
   * @return array
   */
  public function getDefaultInlineTypeReplacements(): array
  {
    $typeResolver = new TypeResolver();

    $replacements = [];

    foreach ($this->defaultInlineTypeReplacements as $class => $replacement) {
      if (!class_exists($class) && !interface_exists($class)) {
        throw InvalidDefaultTypeReplacer::classDoesNotExist($class);
      }

      $replacements[$class] = $replacement instanceof Type
        ? $replacement
        : $typeResolver->resolve($replacement);
    }

    return $replacements;
  }

  public function defaultTypeReplacements(array $replacements): static
  {
    if ($this->scopedNamespacePrefix !== null) {
      foreach ($replacements as $class => $replacement) {
        foreach ($this->scopedNamespaces as $namespace) {
          if (str_starts_with($class, $namespace)) {
            $replacements[$this->scopedNamespacePrefix . '\\' . $class] = $replacement;
            unset($replacements[$class]);
            break;
          }
        }
      }
    }
    parent::defaultTypeReplacements($replacements);
    return $this;
  }

  /**
   * Request to emit constants as literal type typed constants.
   *
   * @param bool $constantsAsConstants
   *
   * @return ConstantsTransformerConfig
   */
  public function constantsAsConstants(bool $constantsAsConstants = true): static
  {
    $this->constantsAsConstants = $constantsAsConstants;

    return $this;
  }

  /**
   * Request to emit constants as literal type typed interface properties.
   *
   * @param bool $constantsAsProperties
   *
   * @return ConstantsTransformerConfig
   */
  public function constantsAsProperties(bool $constantsAsProperties = true)
  {
    return $this->constantsAsConstants(!$constantsAsProperties);
  }

  /**
   * Return the scoped app namespace.
   *
   * @return string;
   */
  public function getAppNamespace(): string
  {
    return $this->appNamespace;
  }

  /**
   * Return the scoped namespace prefix.
   *
   * @return ?string;
   */
  public function getScopedNamespacePrefix(): ?string
  {
    return $this->scopedNamespacePrefix;
  }

  /**
   * Return the scoped namespaces.
   *
   * @return string;
   */
  public function getScopedNamespaces(): array
  {
    return $this->scopedNamespaces;
  }

  /**
   * @return bool Whether constants should be emitted as literal type typed constants variables.
   */
  public function emitConstantsAsConstants(): bool
  {
    return $this->constantsAsConstants;
  }

  /**
   * @return bool Whether constants should be emitted as literal type typed interface properties.
   */
  public function emitConstantsAsProperties(): bool
  {
    return !$this->emitConstantsAsConstants();
  }
}
