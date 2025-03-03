<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\L10N;

use OCP\IL10N;

use OCA\CAFEVDB\Exceptions;

/**
 * AppL10N for the sake of dependency injection is defined as registerd
 * service which simply return an instance of IL10N which reads the app's
 * config space in order to have a IL10N instance bound to the configured
 * orchestra locale.
 *
 * $appLocale is registered as a service by the app.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class AppL10N implements IL10N
{
  /** @var IL10N */
  protected IL10N $base;

  /** {@inheritdoc} */
  public function __construct(
    protected IL10N $l,
    protected L10NFactory $l10NFactory,
    protected string $appName,
    string $appLocale,
  ) {
    $appLanguage = locale_get_primary_language($appLocale);
    $this->base = $l10NFactory->get($appName, $appLanguage, $appLocale);
  }

  /** {@inheritdoc} */
  public function t(string $text, $parameters = []): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function n(string $textSingular, string $textPlural, int $count, array $parameters = []): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function l(string $type, $data, array $options = [])
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function getLanguageCode(): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function getLocaleCode(): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function getTranslations(): array
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  protected function __call($method, $args)
  {
    if (is_callable([ $this->base, $method ])) {
      return call_user_func_array([ $this->base, $method ], $args);
    }
    throw new Exceptions\DecoratorException(
      $this->l->t('Undefined method - %1$s::%2$s', [ get_class($this->base), $method ]),
    );
  }
}
