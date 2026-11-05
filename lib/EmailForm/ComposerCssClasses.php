<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\EmailForm;

use OCA\RotDrop\DevScripts\PhpToTypeScript;

/** CSS classes shared between the legacy templates, scss and typescript. */
#[PhpToTypeScript\Attributes\TypeScript]
class ComposerCssClasses
{
  public const EMPTY_SELECTION = 'empty-selection';
  public const NO_ATTACHMENTS = 'no-attachments';
  public const STORED_MESSAGES = 'stored-messages';
  public const STORED_MESSAGES_CHOICES = self::STORED_MESSAGES . '-choices';
  public const STORED_MESSAGES_STORAGE = self::STORED_MESSAGES . '-storage';
  public const STORED_MESSAGES_CHOICES_CONTAINER = self::STORED_MESSAGES_CHOICES . '-container';
  public const EMAIL_MESSAGE_SELECTOR = 'email-message-selector';
  public const TEMPLATE_EMAIL_MESSAGE_SELECTOR = 'template-' . self::EMAIL_MESSAGE_SELECTOR;
  public const DRAFT_EMAIL_MESSAGE_SELECTOR = 'draft-' . self::EMAIL_MESSAGE_SELECTOR;
  public const SENT_EMAIL_MESSAGE_SELECTOR = 'sent-' . self::EMAIL_MESSAGE_SELECTOR;
}
