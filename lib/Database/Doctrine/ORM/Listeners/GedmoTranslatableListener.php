<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;

use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\ObjectManager as DoctrineObjectManager;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\L10N\BiDirectionalL10N;

/**
 * Override the default listener to use a modified event subscriber
 * which also queries other sources of translations if a concrete
 * translations has not been persisted yet.
 */
class GedmoTranslatableListener extends \OCA\CAFEVDB\Wrapped\Gedmo\Translatable\TranslatableListener
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /**
   * @var string
   * Translations should use the app's locale
   */
  const LOCALE_CLASS_APP = 'app';
  /**
   * @var string
   * Translations should use default locale, i.e. ConfigService::DEFAULT_LOCALE
   */
  const LOCALE_CLASS_DEFAULT = 'default';
  /**
   * @var string
   * Translations should use the locale of the logged in user
   */
  const LOCALE_CLASS_USER = 'user';

  /** @var BiDirectionalL10N */
  private $musicL10n;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    BiDirectionalL10N $musicL10n,
  ) {
    parent::__construct();
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->musicL10n = $musicL10n;
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  protected function getFallbackTranslation($originalValue)
  {
    if (empty($originalValue)) {
      return null;
    }
    $translatedValue = $this->musicL10n->t($originalValue);
    return ($translatedValue !== $originalValue) ? $translatedValue : null;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFallbackUntranslation($translatedValue)
  {
    if (empty($translatedValue)) {
      return null;
    }
    $originalValue = $this->musicL10n->backTranslate($translatedValue);
    return ($translatedValue !== $originalValue) ? $originalValue : null;
  }

  /**
   * {@inheritdoc}
   *
   * Override the global listener configuration on a per object basis, e.g. to
   * add or remove translated fields.
   */
  public function getObjectConfiguration($object, DoctrineObjectManager $objectManager, string $class)
  {
    $config = parent::getObjectConfiguration($object, $objectManager, $class);
    if (method_exists($object, 'filterTranslatableFields')) {
      $config['fields'] = $object->filterTranslatableFields($config['fields']);
    }
    return $config;
  }
}
