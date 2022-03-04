<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

/**
 * Generate some needed procedures and functions. MySQL specific.
 */
class EnlargeTranslationKeyPhrase extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE `TranslationKeys` DROP INDEX IF EXISTS `UNIQ_F15EDA49A24BE60C`",
      "ALTER TABLE TranslationKeys CHANGE phrase phrase LONGTEXT NOT NULL COMMENT 'Keyword to be translated. Normally the untranslated text in locale en_US, but could be any unique tag'",
      "ALTER TABLE TranslationKeys ADD phrase_hash CHAR(32) DEFAULT NULL",
      "CREATE UNIQUE INDEX UNIQ_F15EDA495A875D0C ON TranslationKeys (phrase_hash)",
      "CREATE TABLE MissingTranslations (locale VARCHAR(5) NOT NULL, translation_key_id INT NOT NULL, INDEX IDX_DBBA64EAD07ED992 (translation_key_id), PRIMARY KEY(translation_key_id, locale)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB",
      "ALTER TABLE MissingTranslations ADD CONSTRAINT FK_DBBA64EAD07ED992 FOREIGN KEY (translation_key_id) REFERENCES TranslationKeys (id) ON DELETE CASCADE",
    ],
    self::TRANSACTIONAL => [
      "UPDATE TranslationKeys SET phrase_hash = MD5(phrase)",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Use "text" for translation phrase in order to allow for larger phrases.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
