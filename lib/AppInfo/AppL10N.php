<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\AppInfo;

use OCP\IL10N;
use OCP\L10N\IFactory as IL10NFactory;

use OCA\CAFEVDB\Service\Registration;

/**
 * AppL10N for the sake of dependency injection is defined as registerd
 * service which simply return an instance of IL10N which reads the app's
 * config space in order to have a IL10N instance bound to the configured
 * orchestra locale. This "interface" is just to help autocompletion and such
 * and to have the type defined.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class AppL10N implements IL10N
{
  /** @var IL10N */
  protected IL10N $appL10n;

  /**
   * @param string $appName
   *
   * @param IL10NFactory $l10NFactory
   *
   * @param string $appLocale
   */
  public function __construct(
    protected string $appName,
    protected IL10NFactory $l10NFactory,
    string $appLocale,
  ) {
    $appLanguage = locale_get_primary_language($appLocale);
    // The following is a hack because get() below does not underst .UTF-8 etc
    $appLocale = $appLanguage . '_' . locale_get_region($appLocale);
    /** @var IL10NFactory $l10NFactory */
    $this->appL10n = $l10NFactory->get($appName, $appLanguage, $appLocale);
  }

  /** {@inheritdoc} */
  public function t(string $text, $parameters = []): string
  {
    return $this->appL10n->t($text, $parameters);
  }

  /** {@inheritdoc} */
  public function n(string $textSingular, string $textPlural, int $count, array $parameters = []): string
  {
    return $this->appL10n->n($textSingular, $textPlural, $count, $parameters);
  }

  /** {@inheritdoc} */
  public function l(string $type, $data, array $options = [])
  {
    return $this->appL10n->l($type, $data, $options);
  }

  /** {@inheritdoc} */
  public function getLanguageCode(): string
  {
    return $this->appL10n->getLanguageCode();
  }

  /** {@inheritdoc} */
  public function getLocaleCode(): string
  {
    return $this->appL10n->getLocaleCode();
  }

  /** {@inheritdoc} */
  public function getTranslations(): array
  {
    return $this->appL10n->getTranslations();
  }
}
