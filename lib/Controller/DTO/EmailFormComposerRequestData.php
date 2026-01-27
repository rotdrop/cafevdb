<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller\DTO;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Controller\EnumEmailFormComposerElement;
use OCA\CAFEVDB\Controller\EnumEmailFormComposerOperation;
use OCA\CAFEVDB\Controller\EnumEmailFormComposerTopic;
use OCA\CAFEVDB\EmailForm\EnumFormStatus;
use OCA\CAFEVDB\EmailForm\EnumFromTag;

/**
 * Response DTO of the email-form controller.
 */
class EmailFormComposerRequestData extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly EnumEmailFormComposerOperation $operation,
    public readonly ?EnumEmailFormComposerTopic $topic,
    public readonly ?string $projectName = null,
    public readonly ?int $projectId = null,
    public readonly ?int $bulkTransactionId = -1,
    public readonly ?bool $errorStatus = false,
    #[TSAttributes\LiteralTypeScriptType('Record<string, unknown>')]
    public readonly ?array $diagnostics = [],
    public readonly ?string $templateEmailOptions = null,
    public readonly ?string $draftEmailOptions = null,
    public readonly ?string $sentEmailOptions = null,
    public readonly ?string $previewData = null,
    /** @var ?array<EnumEmailFormComposerElement> */
    public readonly ?array $formElements = null,
    public readonly mixed $elementData = null,
    public readonly ?string $messageText = null,
    /** @var ?array<string, string> */
    public readonly ?array $messageTextReplacements = null,
    public readonly ?string $subject = null,
    public readonly ?string $composerForm = null,
    public readonly ?string $recipientsForm = null,
    public readonly ?int $messageDraftId = null,
    public readonly ?string $header = null,
    public readonly ?string $recipients = null,
    public readonly ?bool $singleItem = null,
    public readonly ?bool $submitAll = null,
    public readonly mixed $progressToken = null,
    public readonly ?EnumFormStatus $formStatus = null,
    public readonly ?string $fileAttachments = null,
    /** @var ?array<string> */
    public readonly ?array $attachedFiles = null,
    public readonly ?bool $autoSave = null,
    public readonly ?EnumFromTag $fromTag = null,
    public readonly ?string $templateMessagesSelector = null,
    public readonly ?string $draftMessagesSelector = null,
    public readonly ?string $sentMessagesSelector = null,
  ) {
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    if ($formElements ?? null) {
      $formElements = array_map(fn($arg) => EnumEmailFormComposerElement::get($arg), $formElements);
    }
    return new self(
      operation: EnumEmailFormComposerOperation::get($operation),
      topic: $topic === null ? null : EnumEmailFormComposerTopic::get($topic),
      //
      autoSave: $autoSave ?? null,
      fileAttachments: $arachedFiles ?? null,
      bulkTransactionId: $bulkTransactionId ?? -1,
      composerForm: $composerForm ?? null,
      diagnostics: $diagnostics ?? [],
      draftEmailOptions: $draftEmailOptions ?? null,
      elementData: $elementData ?? null,
      errorStatus: $errorStatus ?? false,
      formElements: $formElements ?? null,
      formStatus: $formStatus === null ? null : EnumFormStatus::get($formStatus),
      header: $header ?? null,
      messageText: $messageText ?? null,
      messageTextReplacements: $messageTextReplacements ?? null,
      messageDraftId: empty($messageDraftId) ? null : $messageDraftId,
      previewData: $previewData ?? null,
      progressToken: $progressToken ?? null,
      projectId: ($projectId ?? null) == null ? null : $projectId,
      projectName: $projectName ?? null,
      recipients: $recipients ?? null,
      recipientsForm: $recipientsForm ?? null,
      sentEmailOptions: $sentEmailOptions ?? null,
      singleItem: $singleItem ?? null,
      subject: $subject ?? null,
      submitAll: $submitAll ?? null,
      templateEmailOptions: $templateEmailOptions ?? null,
      fromTag: $fromTag === null ? null : EnumFromTag::get($fromTag),
      templateMessagesSelector: $templateMessagesSelector ?? null,
      draftMessagesSelector: $draftMessagesSelector ?? null,
      sentMessagesSelector: $sentMessagesSelector ?? null,
    );
  }
}
