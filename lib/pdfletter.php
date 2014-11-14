<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /** Din 5008 format B
   */
  class PDFLetter extends \TCPDF {
    const PAGE_WIDTH = 210;
    const PAGE_HEIGHT = 297;
    const LOGO_SIZE = 60;
    const SUB_LOGO_WIDTH = 80;
    const TOP_MARGIN = 10;
    const LEFT_MARGIN = 20;
    const LEFT_TEXT_MARGIN = 25;
    const RIGHT_TEXT_MARGIN = 20;
    const ADDRESS_TOP = 45;
    const ADDRESS_WIDTH = 80;
    const ADDRESS_HEIGHT = 45;
    const FONT_SIZE = 12;
    const FONT_SENDER = 8;
    const FONT_FOOTER = 8;
    const FONT_HEADER = 10;
    const FONT_RECIPIENT = 12;
    const FOOTER_HEIGHT = 20;
    const FIRST_FOOTER_HEIGHT = 25;
    const PT = 0.3527777777777777;

    /**Return the font-size converted to mm. */
    public function fontSize($ptSize = self::FONT_SIZE) {
      return $ptSize * self::PT;
    }
    
    public function Header() {
      //		$this->setJPEGQuality(90);
      // $this->Image('logo.png', 120, 10, 75, 0, 'PNG', 'http://www.finalwebsites.com');
    }

    public function frontHeader($executiveMember)
    {
      $addr1 = Config::getValue('streetAddressName01');
      $addr2 = Config::getValue('streetAddressName02');
      $street = Config::getValue('streetAddressStreet');
      $street .= ' '.Config::getValue('streetAddressHouseNumber');
      $ZIP = Config::getValue('streetAddressZIP');
      $city = Config::getValue('streetAddressCity');
      
      $executiveMember = $addr1.'<br>'.$executiveMember;
      $this->SetCellPadding(0);
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_HEADER);
      $this->writeHtmlCell(self::ADDRESS_WIDTH, self::ADDRESS_TOP-self::TOP_MARGIN,
                           self::LEFT_MARGIN, self::TOP_MARGIN,
                           $executiveMember,
                           0 /*border*/);
      $this->Image(__DIR__.'/../img/'.'logo-greyf1024x1024.png',
                   self::PAGE_WIDTH - self::RIGHT_TEXT_MARGIN - self::LOGO_SIZE, self::TOP_MARGIN,
                   self::LOGO_SIZE);

      $this->writeHtmlCell(self::SUB_LOGO_WIDTH, 0,
                           self::PAGE_WIDTH - self::RIGHT_TEXT_MARGIN - self::SUB_LOGO_WIDTH,
                           self::TOP_MARGIN + self::LOGO_SIZE + 5,
                           $addr1.'<br>'.
                           'Vereinssitz: '.$addr2.'<br>'.
                           $street.', '.$ZIP.' '.$city,
                           0, // border
                           0, // ln
                           false, // fill
                           true, // reset
                           'R');
    }

    public function Footer() {
      $this->SetCellPadding(0);

      $this->SetFont(PDF_FONT_NAME_MAIN, 'I', self::FONT_FOOTER);
      $fontHeight = $this->getStringHeight(0, 'Camerata');
      $textWidth = self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN;

      $page = $this->getAliasNumPage();
      $total = $this->getAliasNbPages();
      $pages = L::t('page').' '.$page.' '.L::t('of').' '.$total;
      $pagesDummy = L::t('page').' '.'9'.' '.L::t('of').' '.'9';
      $pagesWidth = $this->GetStringWidth($pagesDummy);

      if ($this->getPage() == 1) {

        $this->Line(self::LEFT_MARGIN, self::PAGE_HEIGHT-self::FIRST_FOOTER_HEIGHT,
                    self::LEFT_MARGIN+$textWidth, self::PAGE_HEIGHT-self::FIRST_FOOTER_HEIGHT);

        // TODO: read bank from data-base
        $this->SetXY(self::LEFT_MARGIN, -(self::FIRST_FOOTER_HEIGHT-(0.5)*$fontHeight));
        $this->Cell(60, 0, 'Camerata Academica Freiburg e.V.', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN, -(self::FIRST_FOOTER_HEIGHT-(1.5)*$fontHeight));
        $this->Cell(60, 0, 'Amtsgericht Freiburg, VR 3604', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN, -(self::FIRST_FOOTER_HEIGHT-(2.5)*$fontHeight));
        $this->Cell(60, 0, 'Mitglied im BDLO, 1087C', 0, false, 'L');

        $this->SetXY(self::LEFT_MARGIN+60, -(self::FIRST_FOOTER_HEIGHT-(0.5)*$fontHeight));
        $this->Cell(60, 0, 'IBAN: DE95680900000020213507', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+60, -(self::FIRST_FOOTER_HEIGHT-(1.5)*$fontHeight));
        $this->Cell(60, 0, 'BIC: GENODE61FR1', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+60, -(self::FIRST_FOOTER_HEIGHT-(2.5)*$fontHeight));
        $this->Cell(60, 0, 'Volksbank Freiburg e.G.', 0, false, 'L');

        $this->SetXY(self::LEFT_MARGIN+120, -(self::FIRST_FOOTER_HEIGHT-(0.5)*$fontHeight));
        $this->Cell(60, 0, 'IBAN: DE08680501010010114285', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+120, -(self::FIRST_FOOTER_HEIGHT-(1.5)*$fontHeight));
        $this->Cell(60, 0, 'BIC: FRSPDE66XXX', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+120, -(self::FIRST_FOOTER_HEIGHT-(2.5)*$fontHeight));
        $this->Cell(60, 0, 'Sparkasse Freiburg', 0, false, 'L');
        
        $this->SetXY(
          self::LEFT_MARGIN + 0.5*($textWidth-$pagesWidth-10),
          - self::FIRST_FOOTER_HEIGHT - 0.5*$fontHeight
          );
        $this->SetFillColor(255);
        $this->Cell($pagesWidth+10, $fontHeight, '', 0, false, 'C', true);
        $this->SetXY(
          self::LEFT_MARGIN + 0.5*($textWidth-$pagesWidth),
          - self::FIRST_FOOTER_HEIGHT - 0.5*$fontHeight
          );
        $this->Cell($textWidth, $fontHeight, $pages, 0, false, 'L', false);
      } else {
        $this->Line(self::LEFT_MARGIN, self::PAGE_HEIGHT-self::FOOTER_HEIGHT,
                    self::LEFT_MARGIN+$textWidth, self::PAGE_HEIGHT-self::FOOTER_HEIGHT);

        $this->SetXY(self::LEFT_MARGIN, -(self::FOOTER_HEIGHT-(1)*$fontHeight));
        $this->Cell($textWidth, 0, $pages, 0, false, 'C');
      }

      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_SIZE);
    }

    public function addressFieldSender($sender)
    {
      $this->SetCellPadding(0);
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_SENDER);
      $fontHeight = self::FONT_SENDER*self::PT;

      $this->SetXY(self::LEFT_MARGIN, self::ADDRESS_TOP+$fontHeight);
      $this->Cell($this->GetStringWidth($sender), 0, $sender, 'B');
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_SIZE);
    }

    public function addressFieldRecipient($recipient)
    {
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_RECIPIENT);
      $fontHeight = self::PT * self::FONT_RECIPIENT;
      if (false)
        $this->writeHtmlCell(self::ADDRESS_WIDTH, self::ADDRESS_HEIGHT-3*$fontHeight,
                             self::LEFT_MARGIN, self::ADDRESS_TOP+3*$fontHeight,
                             $recipient, 1);
      $this->SetXY(self::LEFT_MARGIN, self::ADDRESS_TOP+4*$fontHeight);
      $this->MultiCell(self::ADDRESS_WIDTH, self::ADDRESS_HEIGHT-4*$fontHeight,
                       $recipient, 0, 'L');
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_SIZE);
    }

    /**Folding marks. */
    public function foldingMarks()
    {
      // Falzmarken
      $oldLineWidth = $this->GetLineWidth();
      $this->SetLineWidth(0.3);
      $this->Line(3,105,6,105);
      $this->Line(3,148,8,148);
      $this->Line(3,210,6,210);
      $this->SetLineWidth($oldLineWidth);
    }
    
    public function date($date)
    {
      $this->SetCellPadding(0);
      $this->SetXY(125, 100 - 1*$this->fontSize());
      $this->Cell(65, $this->fontSize(), $date, 0, false, 'R');
    }

    public function subject($subject)
    {
      $this->SetCellPadding(0);
      $this->SetXY(self::LEFT_TEXT_MARGIN, 100);
      $this->SetFont(PDF_FONT_NAME_MAIN, 'B', self::FONT_SIZE);
      $this->Cell(self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN,
                  $this->fontSize(),
                  $subject, 0, false, 'L');        
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_SIZE);
    }

    public function letterOpen($startFormula)
    {
      $textWidth = self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN;
      $this->SetCellPadding(0);
      $this->SetXY(self::LEFT_TEXT_MARGIN, 100+2*$this->fontSize());
      $this->Cell($textWidth, $this->fontSize(), $startFormula, 0, false, 'L');
    }

    public function letterClose($endFormula, $signature, $signatureImage = false)
    {
      $this->startTransaction();
      $startPage = $this->getPage();
      
      $textWidth = self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN;
      //$this->SetXY(self::LEFT_TEXT_MARGIN, $this->GetY() + $this->fontSize());
      //$this->Cell($textWidth, $this->fontSize(), $endFormula, 0, false, 'L');
      $this->writeHtmlCell($textWidth, $this->fontSize(),
                           self::LEFT_TEXT_MARGIN, $this->GetY() + $this->fontSize(),
                           $endFormula, '', 1);
      $y = $this->GetY();
      if ($signatureImage !== false /*&& file_exists($signatureImage)*/) {
        $this->Image($signatureImage,
                     self::LEFT_TEXT_MARGIN+10,
                     $this->GetY()+1*$this->fontSize(),
                     0, 4*$this->fontSize());                     
      }
      $this->SetXY(self::LEFT_TEXT_MARGIN, $y + 4*$this->fontSize());
      $this->Cell($textWidth, $this->fontSize(), $signature, 0, false, 'L');

      $endPage = $this->getPage();

      if ($startPage != $endPage) {
        $this->rollbackTransaction(true);
        $this->addPage();
        $this->letterClose($endFormula, $signature, $signatureImage);
      } else {
        $this->commitTransaction();
      }
    }

    public function letter($startFormula, $body, $endFormula, $signature)
    {
      $this->letterOpen($startFormula);
      $this->writeHtmlCell(self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN,
                           300,
                           self::LEFT_TEXT_MARGIN, 100+4*$this->fontSize(),
                           $body);
      $this->letterClose($endFormular, $signature);
    }

    static public function testLetter($name = 'test.pdf', $dest = 'S')
    {
      // create a PDF object
      $pdf = new PDFLetter(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
 
      // set document (meta) information
      $pdf->SetCreator(PDF_CREATOR);
      $pdf->SetAuthor('Claus-Justus Heine');
      $pdf->SetTitle('Camerate Academica Freiburg e.V. Rechnungsvorlage');
      $pdf->SetSubject('DIN 5008 RTFM');
      $pdf->SetKeywords('TCPDF, PDF, invoice, CAF, CAFeV');
 
      // add a page
      $pdf->addPage();

      // Falzmarken
      $pdf->Line(3,105,6,105);
      $pdf->Line(3,148,8,148);
      $pdf->Line(3,210,6,210);
 
      // Address record
      $pdf->frontHeader(
        'c/o Claus-Justus Heine<br>'.
        'Engelboldstr. 97<br>'.
        '70569 Stuttgart<br>'.
        'Phon: 0151-651 6666 0<br>'.
        'M@il: Kassenwart@CAFeV.DE'
        );
      $pdf->addressFieldSender('C.-J. Heine, Engelboldstr. 97, 70569 Stuttgart');
      $pdf->addressFieldRecipient(
        'Claus-Justus Heine
Engelboldstr. 97
70569 Stuttgart'
        );
      $pdf->date('30.12.1999');
      $pdf->subject('Whatever');
      $pdf->letter('Sehr geehrtes Arschloch', 'Du kannst mich mal!', 'Ohne Blah', 'Claus-Justus Heine');

      // add a page; there will always be two pages, seems to be bug. Delete one of them
      $pdf->addPage();

      $pdf->deletePage($pdf->getNumPages());

      $pdf->addPage();

      $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH-PDFLetter::LEFT_TEXT_MARGIN-PDFLetter::RIGHT_TEXT_MARGIN,
                          150,
                          PDFLetter::LEFT_TEXT_MARGIN, 100+4*$pdf->fontSize(),
                          '<table>
  <tr><td>Blah</td></tr>
</table>');

      //Close and output PDF document
      return $pdf->Output($name, $dest);
    }
  } // class PDFLetter
    
} // namespace CAFEVDB

?>
