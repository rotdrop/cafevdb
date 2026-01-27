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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/** All the names used by the legacy email composer form template. */
#[TSAttributes\TypeScript]
class ComposerCgiKeys
{
  public const ADDRESS_BOOK_BCC = 'addressBookBCC';
  public const ADDRESS_BOOK_CC = 'addressBookCC';
  public const ATTACHED_EVENTS = 'attachedEvents';
  public const ATTACHED_FILES = 'attachedFiles';
  public const ATTACHMENT_VISIBILITY_TOGGLE = 'attachmentVisibilityToggle';
  public const BCC = 'BCC';
  public const BULK_TRANSACTION_ID = 'bulkTransactionId';
  public const CANCEL = 'cancel';
  public const CC = 'CC';
  public const DELETE_ALL_ATTACHMENTS = 'deleteAllAttachments';
  public const DELETE_MESSAGE = 'deleteMessage';
  public const DISCLOSED_RECIPIENTS = 'disclosedRecipients';
  public const DRAFT_AUTO_SAVE = 'draftAutoSave';
  public const DRAFT_MESSAGES_SELECTOR = 'draftMessagesSelector';
  public const EDIT_SUBJECT_TAG = 'editSubjectTag';
  public const EMAIL_TEMPLATE_NAME = 'emailTemplateName';
  public const FILE_ATTACHMENTS = 'fileAttachments';
  public const FORM_STATUS = 'formStatus';
  public const FROM_TAG = 'fromTag';
  public const IN_REPLY_TO = 'inReplyTo';
  public const MESSAGE_DRAFT_ID = 'messageDraftId';
  public const MESSAGE_EXPORT = 'messageExport';
  public const MESSAGE_TEXT = 'messageText';
  public const MESSAGE_TEXT_REPLACEMENTS = 'messageTextReplacements';
  public const OPERATION = 'operation';
  public const PROJECT_ID = 'projectId';
  public const PROJECT_NAME = 'projectName';
  public const REFERENCING = 'referencing';
  public const SAVE_AS_TEMPLATE = 'saveAsTemplate';
  public const SAVE_FROM_TAG = 'saveFromTag';
  public const SAVE_MESSAGE = 'saveMessage';
  public const SEND = 'send';
  public const SENT_MESSAGES_SELECTOR = 'sentMessagesSelector';
  public const SUBJECT = 'subject';
  public const SUBJECT_TAG = 'subjectTag';
  public const TEMPLATE_MESSAGES_SELECTOR = 'templateMessagesSelector';
  public const TOPIC = 'topic';
}
