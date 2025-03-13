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
use OCP\IUser;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\L10N\ILanguageIterator;

use OCA\CAFEVDB\Exceptions;

/**
 * A decoratory which strips the encoding suffix from a given locale as
 * Nextcloud L10N stuff cannot deal with that.
 */
class L10NFactory implements IL10NFactory
{
  /** {@inheritdoc} */
  public function __construct(
    protected IL10NFactory $base,
    protected IL10N $l,
  ) {
  }

  /**
   * In particular strip the encoding suffix.
   *
   * @param null|string $locale
   *
   * @return null|string
   */
  private static function sanitizeLocale(?string $locale):?string
  {
    if ($locale === null) {
      return $locale;
    }
    $language = locale_get_primary_language($locale);
    $region = locale_get_region($locale);
    return empty($region) ? $language : $locale . '_' . $region;
  }

  /** {@inheritdoc} */
  public function get($app, $lang = null, $locale = null)
  {
    return $this->base->get($app, self::sanitizeLocale($lang), self::sanitizeLocale($locale));
  }

  /** {@inheritdoc} */
  public function findLanguage(?string $appId = null): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function findGenericLanguage(?string $appId = null): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function findLocale($lang = null)
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function findLanguageFromLocale(string $app = 'core', ?string $locale = null)
  {
    return $this->base->findLanguageFromLocale($app, self::sanitizeLocale($locale));
  }

  /** {@inheritdoc} */
  public function findAvailableLanguages($app = null): array
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function findAvailableLocales()
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function languageExists($app, $lang)
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function localeExists($locale)
  {
    return $this->base->localeExists(self::sanitizeLocale($locale));
  }

  /** {@inheritdoc} */
  public function getLanguageIterator(?IUser $user = null): ILanguageIterator
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function getLanguages(): array
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function getUserLanguage(?IUser $user = null): string
  {
    return static::__call(__FUNCTION__, func_get_args());
  }

  /** {@inheritdoc} */
  public function __call($method, $args)
  {
    if (is_callable([ $this->base, $method ])) {
      return call_user_func_array([ $this->base, $method ], $args);
    }
    throw new Exceptions\DecoratorException(
      $this->l->t('Undefined method - %1$s::%2$s', [ get_class($this->base), $method ]),
    );
  }
}
