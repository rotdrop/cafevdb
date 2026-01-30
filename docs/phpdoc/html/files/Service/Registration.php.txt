<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2026 Claus-Justus Heine
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

use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Service\L10N\AppL10N;
use OCA\CAFEVDB\Service\L10N\L10NFactory;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/** Register some utiltiy services in order to ease dependency injection. */
class Registration
{
  public const MANAGEMENT_GROUP_ID = 'ManagementGroupId';
  public const LOCALE = 'Locale';
  public const LANGUAGE = 'Language';
  public const USER_LOCALE = 'UserLocale';
  public const USER_LANGUAGE = 'UserLanguage';
  public const USER_L10N = 'UserL10N';
  public const APP_LOCALE = 'AppLocale';
  public const APP_LANGUAGE = 'AppLanguage';
  public const APP_L10N = 'AppL10N';

  /**
   * Backwards compatibility support after converting stuff to camel case.
   *
   * @param string $key
   *
   * @return ?string
   */
  protected static function oldConfigKey(string $key): ?string
  {
    $oldConfigKey = null;
    if (!empty(OldSettingsKeys::APP_KEYS[$key]) && OldSettingsKeys::APP_KEYS[$key] != $key) {
      $oldConfigKey = OldSettingsKeys::APP_KEYS[$key];
    } elseif (strtolower($key) != $key) {
      $oldConfigKey = strtolower($key);
    }
    return $oldConfigKey;
  }

  /**
   * Static service registration routine.
   *
   * @param IRegistrationContext $context
   *
   * @return void
   */
  public static function register(IRegistrationContext $context):void
  {
    $context->registerService(
      MigrationsServiceInterface::class,
      function(ContainerInterface $container) {
        /** @var DoctrineMigrationsService $doctrineMigrationsService */
        $doctrineMigrationsService = $container->get(DoctrineMigrationsService::class);
        if (count($doctrineMigrationsService->getApplied()) > 0) {
          return $doctrineMigrationsService;
        } else {
          return $container->get(LegacyMigrationsService::class);
        }
      }
    );

    $context->registerServiceAlias(
      Finance\SepaBulkTransactionService::EXPORT_SERVICE_ALIAS . 'aqbanking',
      Finance\AqBankingBulkTransactionExporter::class);

    $context->registerServiceAlias(
      Finance\SepaBulkTransactionService::EXPORT_BALANCING_ITEMS_SERVICE_ALIAS . 'gnucash',
      Finance\GnuCashBulkTransactionBalancingItemsExporter::class);

    $context->registerService('AppCryptor', function(ContainerInterface $container) {
      return $container->get(EncryptionService::class)->getAppCryptor();
    });
    $context->registerService('AppAsymmetricCryptor', function(ContainerInterface $container) {
      return $container->get(EncryptionService::class)->getAppAsymmetricCryptor();
    });

    $context->registerService(self::MANAGEMENT_GROUP_ID, function(ContainerInterface $container) {
      $cloudConfig = $container->get(IConfig::class);
      $appName = $container->get('AppName');
      $default = null;
      $oldConfigKey = self::oldConfigKey(ConfigConstants::USER_GROUP_KEY);
      if ($oldConfigKey) {
        $default = $cloudConfig->getAppValue($appName, $oldConfigKey, $default);
      }
      $result = $cloudConfig->getAppValue($appName, ConfigConstants::USER_GROUP_KEY, $default);
      return $result;
    });
    $context->registerServiceAlias(lcfirst(self::MANAGEMENT_GROUP_ID), self::MANAGEMENT_GROUP_ID);

    $context->registerService(self::USER_LOCALE, function(ContainerInterface $container) {
      $appName = $container->get('AppName');
      /** @var L10NFactory $l10NFactory */
      $l10NFactory = $container->get(L10NFactory::class);
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
    $context->registerServiceAlias(lcfirst(self::USER_LOCALE), self::USER_LOCALE);
    $context->registerServiceAlias(self::LOCALE, self::USER_LOCALE);
    $context->registerServiceAlias(lcfirst(self::LOCALE), self::USER_LOCALE);

    $context->registerService(self::USER_LANGUAGE, function(ContainerInterface $container) {
      $locale = $container->get(self::USER_LOCALE);
      return locale_get_primary_language($locale);
    });
    $context->registerServiceAlias(lcfirst(self::USER_LANGUAGE), self::USER_LANGUAGE);
    $context->registerServiceAlias(self::LANGUAGE, self::USER_LANGUAGE);
    $context->registerServiceAlias(lcfirst(self::LANGUAGE), self::USER_LANGUAGE);

    $context->registerService(self::USER_L10N, function(ContainerInterface $container) {
      return $container->get(IL10N::class);
    });
    $context->registerServiceAlias(lcfirst(self::USER_L10N), self::USER_L10N);

    $context->registerService(self::APP_LOCALE, function(ContainerInterface $container) {
      /** @var EncryptionService $encryptionService */
      $encryptionService = $container->get(EncryptionService::class);
      $appLocale = $encryptionService->getConfigValue(ConfigConstants::ORCHESTRA_LOCALE_KEY, $container->get(self::USER_LOCALE));
      if (strpos($appLocale, '.') === false) {
        $appLocale .= '.UTF-8';
      }
      return $appLocale;
    });
    $context->registerServiceAlias(lcfirst(self::APP_LOCALE), self::APP_LOCALE);

    $context->registerService(ucfirst(self::APP_LANGUAGE), function(ContainerInterface $container) {
      $locale = $container->get(self::APP_LOCALE);
      return locale_get_primary_language($locale);
    });
    $context->registerServiceAlias(lcfirst(self::APP_LANGUAGE), self::APP_LANGUAGE);

    $context->registerServiceAlias(self::APP_L10N, AppL10N::class);
    $context->registerServiceAlias(lcfirst(self::APP_L10N), self::APP_L10N);
  }
}
