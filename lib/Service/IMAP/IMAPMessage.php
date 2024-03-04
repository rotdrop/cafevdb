<?php

declare(strict_types=1);

/**
 * @author Alexander Weidinger <alexwegoo@gmail.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Christoph Wurst <wurst.christoph@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Thomas Mueller <thomas.mueller@tmit.eu>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\CAFEVDB\Service\IMAP;

use Exception;
use Horde_Imap_Client;
use Horde_Imap_Client_DateTime;
use Horde_Mime_Headers_MessageId;
use Horde_Mime_Part;
use JsonSerializable;
use OCA\Mail\AddressList;
use OCP\Files\File;
use OCP\Files\SimpleFS\ISimpleFile;
use ReturnTypeWillChange;
use function in_array;
use function mb_convert_encoding;
use function mb_strcut;
use function trim;

class IMAPMessage implements JsonSerializable
{
  use \OCA\Mail\Model\ConvertAddresses;

  /** @var string[] */
  private array $flags;

  private int $messageId;
  private string $realMessageId;
  private AddressList $from;
  private AddressList $to;
  private AddressList $cc;
  private AddressList $bcc;
  private AddressList $replyTo;
  private string $subject;
  public string $plainMessage;
  public string $htmlMessage;
  public array $attachments;
  public array $inlineAttachments;
  private bool $hasAttachments;
  public array $scheduling;
  private bool $hasHtmlMessage;
  private Horde_Imap_Client_DateTime $imapDate;
  private string $rawReferences;
  private string $dispositionNotificationTo;
  private bool $hasDkimSignature;
  private ?string $unsubscribeUrl;
  private bool $isOneClickUnsubscribe;
  private ?string $unsubscribeMailto;
  private string $rawInReplyTo;
  private bool $isEncrypted;
  private bool $isSigned;
  private bool $signatureIsValid;
  public ?string $rawHeaders;

  public function __construct(
    int $uid,
    string $messageId,
    array $flags,
    AddressList $from,
    AddressList $to,
    AddressList $cc,
    AddressList $bcc,
    AddressList $replyTo,
    string $subject,
    string $plainMessage,
    string $htmlMessage,
    bool $hasHtmlMessage,
    array $attachments,
    array $inlineAttachments,
    bool $hasAttachments,
    array $scheduling,
    Horde_Imap_Client_DateTime $imapDate,
    string $rawReferences,
    string $dispositionNotificationTo,
    bool $hasDkimSignature,
    ?string $unsubscribeUrl,
    bool $isOneClickUnsubscribe,
    ?string $unsubscribeMailto,
    string $rawInReplyTo,
    ?string $rawHeaders,
  ) {
    $this->messageId = $uid;
    $this->realMessageId = $messageId;
    $this->flags = $flags;
    $this->from = $from;
    $this->to = $to;
    $this->cc = $cc;
    $this->bcc = $bcc;
    $this->replyTo = $replyTo;
    $this->subject = $subject;
    $this->plainMessage = $plainMessage;
    $this->htmlMessage = $htmlMessage;
    $this->hasHtmlMessage = $hasHtmlMessage;
    $this->attachments = $attachments;
    $this->inlineAttachments = $inlineAttachments;
    $this->hasAttachments = $hasAttachments;
    $this->scheduling = $scheduling;
    $this->imapDate = $imapDate;
    $this->rawReferences = $rawReferences;
    $this->dispositionNotificationTo = $dispositionNotificationTo;
    $this->hasDkimSignature = $hasDkimSignature;
    $this->unsubscribeUrl = $unsubscribeUrl;
    $this->isOneClickUnsubscribe = $isOneClickUnsubscribe;
    $this->unsubscribeMailto = $unsubscribeMailto;
    $this->rawInReplyTo = $rawInReplyTo;
    $this->rawHeaders = $rawHeaders;
  }

  public static function generateMessageId(): string {
    return Horde_Mime_Headers_MessageId::create('nextcloud-mail-generated')->value;
  }

  /**
   * @return int
   */
  public function getUid(): int {
    return $this->messageId;
  }

  /**
   * @deprecated  Seems unused
   * @return array
   */
  public function getFlags(): array {
    return [
      'seen' => in_array(Horde_Imap_Client::FLAG_SEEN, $this->flags),
      'flagged' => in_array(Horde_Imap_Client::FLAG_FLAGGED, $this->flags),
      'answered' => in_array(Horde_Imap_Client::FLAG_ANSWERED, $this->flags),
      'deleted' => in_array(Horde_Imap_Client::FLAG_DELETED, $this->flags),
      'draft' => in_array(Horde_Imap_Client::FLAG_DRAFT, $this->flags),
      'forwarded' => in_array(Horde_Imap_Client::FLAG_FORWARDED, $this->flags),
      'hasAttachments' => $this->hasAttachments,
      'mdnsent' => in_array(Horde_Imap_Client::FLAG_MDNSENT, $this->flags, true),
      'important' => in_array(Tag::LABEL_IMPORTANT, $this->flags, true)
    ];
  }

  /**
   * @deprecated  Seems unused
   * @param string[] $flags
   *
   * @throws Exception
   *
   * @return void
   */
  public function setFlags(array $flags) {
    // TODO: implement
    throw new Exception('Not implemented');
  }

  public function getRawReferences(): string {
    return $this->rawReferences;
  }

  public function getRawInReplyTo(): string {
    return $this->rawInReplyTo;
  }

  public function getRawHeaders(): ?string {
    return $this->rawHeaders;
  }

  public function getDispositionNotificationTo(): string {
    return $this->dispositionNotificationTo;
  }

  public function getFrom(): AddressList {
    return $this->from;
  }

  /**
   * @param AddressList $from
   *
   * @throws Exception
   *
   * @return void
   */
  public function setFrom(AddressList $from) {
    throw new Exception('IMAP message is immutable');
  }

  public function getTo(): AddressList {
    return $this->to;
  }

  /**
   * @param AddressList $to
   *
   * @throws Exception
   *
   * @return void
   */
  public function setTo(AddressList $to) {
    throw new Exception('IMAP message is immutable');
  }

  public function getCC(): AddressList {
    return $this->cc;
  }

  /**
   * @param AddressList $cc
   *
   * @throws Exception
   *
   * @return void
   */
  public function setCC(AddressList $cc) {
    throw new Exception('IMAP message is immutable');
  }

  public function getBCC(): AddressList {
    return $this->bcc;
  }

  /**
   * @param AddressList $bcc
   *
   * @throws Exception
   *
   * @return void
   */
  public function setBcc(AddressList $bcc) {
    throw new Exception('IMAP message is immutable');
  }

  public function getMessageId(): string {
    return $this->realMessageId;
  }

  public function getSubject(): string {
    return $this->subject;
  }

  /**
   * @param string $subject
   *
   * @throws Exception
   *
   * @return void
   */
  public function setSubject(string $subject) {
    throw new Exception('IMAP message is immutable');
  }

  public function getSentDate(): Horde_Imap_Client_DateTime {
    return $this->imapDate;
  }

  /**
   * @param int $id
   *
   * @return array
   */
  public function getFullMessage(int $id): array {
    $mailBody = $this->plainMessage;
    $data = $this->jsonSerialize();
    if ($this->hasHtmlMessage) {
      $data['hasHtmlBody'] = true;
      $data['body'] = $this->getHtmlBody($id);
      $data['attachments'] = $this->attachments;
    } else {
      $mailBody = $this->htmlService->convertLinks($mailBody);
      [$mailBody, $signature] = $this->htmlService->parseMailBody($mailBody);
      $data['body'] = $mailBody;
      $data['signature'] = $signature;
      $data['attachments'] = array_merge($this->attachments, $this->inlineAttachments);
    }

    return $data;
  }

  #[ReturnTypeWillChange]
  public function jsonSerialize() {
    return [
      'uid' => $this->getUid(),
      'messageId' => $this->getMessageId(),
      'from' => $this->getFrom()->jsonSerialize(),
      'to' => $this->getTo()->jsonSerialize(),
      'cc' => $this->getCC()->jsonSerialize(),
      'bcc' => $this->getBCC()->jsonSerialize(),
      'subject' => $this->getSubject(),
      'dateInt' => $this->getSentDate()->getTimestamp(),
      'flags' => $this->getFlags(),
      'hasHtmlBody' => $this->hasHtmlMessage,
      'dispositionNotificationTo' => $this->getDispositionNotificationTo(),
      'hasDkimSignature' => $this->hasDkimSignature,
      'unsubscribeUrl' => $this->unsubscribeUrl,
      'isOneClickUnsubscribe' => $this->isOneClickUnsubscribe,
      'unsubscribeMailto' => $this->unsubscribeMailto,
      'scheduling' => $this->scheduling,
      'references' => $this->rawReferences,
      'inReplyTo' => $this->rawInReplyTo,
    ];
  }

  /**
   * @param int $id
   *
   * @return string
   */
  public function getHtmlBody(): string {
    return $this->htmlMessage;
  }

  /**
   * @return string
   */
  public function getPlainBody(): string {
    return $this->plainMessage;
  }

  public function getContent(): string {
    return $this->getPlainBody();
  }

  /**
   * @return void
   */
  public function setContent(string $content) {
    throw new Exception('IMAP message is immutable');
  }

  /**
   * @return Horde_Mime_Part[]
   */
  public function getAttachments(): array {
    throw new Exception('not implemented');
  }

  /**
   * @param string $name
   * @param string $content
   *
   * @return void
   */
  public function addRawAttachment(string $name, string $content): void {
    throw new Exception('IMAP message is immutable');
  }

  /**
   * @param string $name
   * @param string $content
   *
   * @return void
   */
  public function addEmbeddedMessageAttachment(string $name, string $content): void {
    throw new Exception('IMAP message is immutable');
  }

  /**
   * @param File $file
   *
   * @return void
   */
  public function addAttachmentFromFiles(File $file) {
    throw new Exception('IMAP message is immutable');
  }

  /**
   * @param LocalAttachment $attachment
   * @param ISimpleFile $file
   *
   * @return void
   */
  public function addLocalAttachment(LocalAttachment $attachment, ISimpleFile $file) {
    throw new Exception('IMAP message is immutable');
  }

  /**
   * @return string|null
   */
  public function getInReplyTo() {
    throw new Exception('not implemented');
  }

  /**
   * @param string $id
   *
   * @return void
   */
  public function setInReplyTo(string $id) {
    throw new Exception('not implemented');
  }

  public function getReplyTo(): AddressList {
    return $this->replyTo;
  }

  /**
   * @param string $id
   *
   * @return void
   */
  public function setReplyTo(string $id) {
    throw new Exception('not implemented');
  }

  public function getUnsubscribeUrl(): ?string {
    return $this->unsubscribeUrl;
  }

  public function isOneClickUnsubscribe(): bool {
    return $this->isOneClickUnsubscribe;
  }
}
