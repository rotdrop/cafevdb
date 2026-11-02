<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\EmailForm;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\EmailForm\Composer;

/** Invoice context checks independent of the web form. */
#[Attributes\CoversClass(Composer::class)]
class InvoiceComposerTest extends TestCase
{
  /** @return void */
  public function testInvoiceIsOnlyResolvedForItsDebitor(): void
  {
    $debitor = $this->createStub(Entities\Musician::class);
    $debitor->method('getId')->willReturn(45);
    $other = $this->createStub(Entities\Musician::class);
    $other->method('getId')->willReturn(46);
    $invoice = $this->createStub(Entities\Invoice::class);
    $invoice->method('getDebitor')->willReturn($debitor);

    $composer = new ReflectionClass(Composer::class)->newInstanceWithoutConstructor();
    new ReflectionProperty($composer, 'invoice')->setValue($composer, $invoice);
    $resolve = new ReflectionMethod($composer, 'getCompositePayment');
    $this->assertSame($invoice, $resolve->invoke($composer, $debitor));
    $this->assertNull($resolve->invoke($composer, $other));
    $this->assertNull($resolve->invoke($composer, null));
  }

  /** @return void */
  public function testInvoiceForcesPersonalAttachmentHandling(): void
  {
    $composer = new ReflectionClass(Composer::class)->newInstanceWithoutConstructor();
    new ReflectionProperty($composer, 'personalFileAttachments')->setValue($composer, []);
    $count = new ReflectionMethod($composer, 'activePersonalAttachments');
    $this->assertSame(0, $count->invoke($composer));
    new ReflectionProperty($composer, 'invoice')->setValue($composer, $this->createStub(Entities\Invoice::class));
    $this->assertSame(1, $count->invoke($composer));
  }

  /** @return void */
  public function testDonationReceiptPaymentResolutionIsPreserved(): void
  {
    $musician = $this->createStub(Entities\Musician::class);
    $musician->method('getId')->willReturn(45);
    $payment = $this->createStub(Entities\CompositePayment::class);
    $payment->method('getMusician')->willReturn($musician);
    $receipt = $this->createStub(Entities\DonationReceipt::class);
    $receipt->method('getDonation')->willReturn($payment);
    $composer = new ReflectionClass(Composer::class)->newInstanceWithoutConstructor();
    $resolve = new ReflectionMethod($composer, 'getCompositePayment');
    $this->assertNull($resolve->invoke($composer, $musician));
    new ReflectionProperty($composer, 'donationReceipt')->setValue($composer, $receipt);
    $this->assertSame($payment, $resolve->invoke($composer, $musician));
  }
}
