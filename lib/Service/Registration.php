<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Psr\Container\ContainerInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\IL10N;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Crypto\CloudSymmetricCryptor;

/** Register some utiltiy services in order to ease dependency injection. */
class Registration
{
  const MANAGEMENT_GROUP_ID = 'ManagementGroupId';
  const USER_LOCALE = 'UserLocale';
  const USER_LANGUAGE = 'UserLanguage';
  const USER_L10N = 'UserL10N';
  const APP_LOCALE = 'AppLocale';
  const APP_LANGUAGE = 'AppLanguage';
  const APP_L10N = 'AppL10N';

  /**
   * Static service registration routine.
   *
   * @param IRegistrationContext $context
   *
   * @return void
   */
  public static function register(IRegistrationContext $context):void
  {
    $context->registerServiceAlias(
      'export:bank-bulk-transactions:aqbanking',
      Finance\AqBankingBulkTransactionExporter::class);

    $context->registerService('AppCryptor', function(ContainerInterface $container) {
      return $container->get(EncryptionService::class)->getAppCryptor();
    });
    $context->registerService('AppAsymmetricCryptor', function(ContainerInterface $container) {
      return $container->get(EncryptionService::class)->getAppAsymmetricCryptor();
    });

    $context->registerService(ucfirst(self::MANAGEMENT_GROUP_ID), function(ContainerInterface $container) {
      return $container->get(IConfig::class)->getAppValue(
        $container->get('AppName'),
        'usergroup', // TODO: use class-const from somewhere
        null
      );
    });
    $context->registerServiceAlias(lcfirst(self::MANAGEMENT_GROUP_ID), ucfirst(self::MANAGEMENT_GROUP_ID));

    $context->registerService(ucfirst(self::USER_LOCALE), function(ContainerInterface $container) {
      $appName = $container->get('AppName');
      /** @var IL10NFactory $l10NFactory */
      $l10NFactory = $container->get(IL10NFactory::class);
      $lang = $l10NFactory->findLanguage($appName);
      if (empty($lang) || $lang == 'en') {
        $lang = null;
      }
      $locale = $l10NFactory->findLocale($appName, $lang);
      $lang = $l10NFactory->findLanguageFromLocale($appName, $locale);
      $primary = locale_get_primary_language($locale);
      if ($primary == $locale) {
        $locale = $lang.'_'.strtoupper($lang);
      }
      if (strpos($locale, '.') === false) {
        $locale .= '.UTF-8';
      }
      return $locale;
    });
    $context->registerServiceAlias(lcfirst(self::USER_LOCALE), ucfirst(self::USER_LOCALE));

    $context->registerService(ucfirst(self::USER_LANGUAGE), function(ContainerInterface $container) {
      $locale = $container->get(self::USER_LOCALE);
      return locale_get_primary_language($locale);
    });
    $context->registerServiceAlias(lcfirst(self::USER_LANGUAGE), ucfirst(self::USER_LANGUAGE));

    $context->registerService(ucfirst(self::USER_L10N), function(ContainerInterface $container) {
      return $container->get(IL10N::class);
    });
    $context->registerServiceAlias(lcfirst(self::USER_L10N), ucfirst(self::USER_L10N));

    $context->registerService(ucfirst(self::APP_LOCALE), function(ContainerInterface $container) {
      /** @var EncryptionService $encryptionService */
      $encryptionService = $container->get(EncryptionService::class);
      $appLocale = $encryptionService->getConfigValue('orchestraLocale', $container->get(self::USER_LOCALE));
      if (strpos($appLocale, '.') === false) {
        $appLocale .= '.UTF-8';
      }
      return $appLocale;
    });
    $context->registerServiceAlias(lcfirst(self::APP_LOCALE), ucfirst(self::APP_LOCALE));

    $context->registerService(ucfirst(self::APP_LANGUAGE), function(ContainerInterface $container) {
      $locale = $container->get(self::APP_LOCALE);
      return locale_get_primary_language($locale);
    });
    $context->registerServiceAlias(lcfirst(self::APP_LANGUAGE), ucfirst(self::APP_LANGUAGE));

    $context->registerService(self::APP_L10N, function(ContainerInterface $container) {
      $appName = $container->get('AppName');
      $appLocale = $container->get(self::APP_LOCALE);
      $appLanguage = locale_get_primary_language($appLocale);
      // The following is a hack because get() below does not underst .UTF-8 etc
      $appLocale = $appLanguage . '_' . locale_get_region($appLocale);

      /** @var IL10NFactory $l10NFactory */
      $l10NFactory = $container->get(IL10NFactory::class);
      $appL10n = $l10NFactory->get($appName, $appLanguage, $appLocale);

      return $appL10n;
    });
    $context->registerServiceAlias(lcfirst(self::APP_L10N), ucfirst(self::APP_L10N));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
