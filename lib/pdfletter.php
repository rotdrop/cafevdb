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
    const TOP_MARGIN = 10;
    const LEFT_MARGIN = 20;
    const LEFT_TEXT_MARGIN = 25;
    const RIGHT_TEXT_MARGIN = 20;
    const ADDRESS_TOP = 45;
    const ADDRESS_WIDTH = 80;
    const ADDRESS_HEIGHT = 45;
    const FONT_SIZE = 10;
    const FONT_SENDER = 8;
    const FONT_FOOTER = 8;
    const FONT_HEADER = 10;
    const FOOTER_HEIGHT = 20;
    const PT = 0.3527777777777777;

    public function Header() {
      //		$this->setJPEGQuality(90);
      // $this->Image('logo.png', 120, 10, 75, 0, 'PNG', 'http://www.finalwebsites.com');
    }

    public function frontHeader($executiveMember)
    {
      $executiveMember = 'Camerate Academica Freiburg e.V<br>'.$executiveMember;
      $this->SetCellPadding(0);
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_HEADER);
      $this->writeHtmlCell(self::ADDRESS_WIDTH, self::ADDRESS_TOP-self::TOP_MARGIN,
                           self::LEFT_MARGIN, self::TOP_MARGIN,
                           $executiveMember,
                           0 /*border*/);
      $this->Image(\OCP\Util::imagePath(Config::APP_NAME, 'logo-greyf1024x1024.png'),
                   130, self::TOP_MARGIN, self::LOGO_SIZE);

      $this->writeHtmlCell(65, 0, 125, self::TOP_MARGIN+self::LOGO_SIZE+5,
                           'Camerata Academica Freiburg e.V.<br>'.
                           'Vereinssitz: c/o Katharina Puff<br>'.
                           'Schlehenrain 19, 79108 Freiburg i.Br.',
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

      $this->Line(self::LEFT_MARGIN, self::PAGE_HEIGHT-self::FOOTER_HEIGHT,
                  self::LEFT_MARGIN+$textWidth, self::PAGE_HEIGHT-self::FOOTER_HEIGHT);

      if ($this->getPage() == 1) {
        $this->SetXY(self::LEFT_MARGIN, -(self::FOOTER_HEIGHT-(1)*$fontHeight));
        $this->Cell(60, 0, 'Camerata Academica Freiburg e.V.', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN, -(self::FOOTER_HEIGHT-(2)*$fontHeight));
        $this->Cell(60, 0, 'Amtsgericht Freiburg, VR 3604', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN, -(self::FOOTER_HEIGHT-(3)*$fontHeight));
        $this->Cell(60, 0, 'Mitglied im BDLO, 1087C', 0, false, 'L');

        $this->SetXY(self::LEFT_MARGIN+60, -(self::FOOTER_HEIGHT-(1)*$fontHeight));
        $this->Cell(60, 0, 'IBAN: DE95680900000020213507', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+60, -(self::FOOTER_HEIGHT-(2)*$fontHeight));
        $this->Cell(60, 0, 'BIC: GENODE61FR1', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+60, -(self::FOOTER_HEIGHT-(3)*$fontHeight));
        $this->Cell(60, 0, 'Volksbank Freiburg e.G.', 0, false, 'L');

        $this->SetXY(self::LEFT_MARGIN+120, -(self::FOOTER_HEIGHT-(1)*$fontHeight));
        $this->Cell(60, 0, 'IBAN: DE08680501010010114285', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+120, -(self::FOOTER_HEIGHT-(2)*$fontHeight));
        $this->Cell(60, 0, 'BIC: FRSPDE66XXX', 0, false, 'L');
        $this->SetXY(self::LEFT_MARGIN+120, -(self::FOOTER_HEIGHT-(3)*$fontHeight));
        $this->Cell(60, 0, 'Sparkasse Freiburg', 0, false, 'L');
      } else {
        $page = $this->getAliasNumPage();
        $total = $this->getAliasNbPages();
        $this->SetXY(self::LEFT_MARGIN, -(self::FOOTER_HEIGHT-(1)*$fontHeight));
        $this->Cell($textWidth, 0, 'Seite '.$page.' von '.$total, 0, false, 'C');
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
      $fontHeight = self::PT * self::FONT_SENDER;
      if (false)
        $this->writeHtmlCell(self::ADDRESS_WIDTH, self::ADDRESS_HEIGHT-3*$fontHeight,
                             self::LEFT_MARGIN, self::ADDRESS_TOP+3*$fontHeight,
                             $recipient, 1);
      $this->SetXY(self::LEFT_MARGIN, self::ADDRESS_TOP+4*$fontHeight);
      $this->MultiCell(self::ADDRESS_WIDTH, self::ADDRESS_HEIGHT-4*$fontHeight,
                       $recipient, 0, 'L');
    }

    public function date($date)
    {
      $this->SetCellPadding(0);
      $this->SetXY(125, 100 - self::FONT_SIZE);
      $this->Cell(65, self::FONT_SIZE, $date, 0, false, 'R');
    }

    public function subject($subject)
    {
      $this->SetCellPadding(0);
      $this->SetXY(self::LEFT_TEXT_MARGIN, 100);
      $this->SetFont(PDF_FONT_NAME_MAIN, 'B', self::FONT_SIZE);
      $this->Cell(self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN,
                  self::FONT_SIZE,
                  $subject, 0, false, 'L');        
      $this->SetFont(PDF_FONT_NAME_MAIN, '', self::FONT_SIZE);
    }

    public function letterOpen($startFormula)
    {
      $textWidth = self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN;
      $this->SetCellPadding(0);
      $this->SetXY(self::LEFT_TEXT_MARGIN, 100+1*self::FONT_SIZE);
      $this->Cell($textWidth, self::FONT_SIZE, $startFormula, 0, false, 'L');
    }

    public function letterClose($endFormula, $signature)
    {
      $textWidth = self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN;
      $this->SetXY(self::LEFT_TEXT_MARGIN, $this->GetY() + self::FONT_SIZE);
      $this->Cell($textWidth, self::FONT_SIZE, $endFormula, 0, false, 'L');
      $this->SetXY(self::LEFT_TEXT_MARGIN, $this->GetY() + 2*self::FONT_SIZE);
      $this->Cell($textWidth, self::FONT_SIZE, $signature, 0, false, 'L');
    }

    public function letter($startFormula, $body, $endFormula, $signature)
    {
      $this->letterOpen($startFormula);
      $this->writeHtmlCell(self::PAGE_WIDTH-self::LEFT_TEXT_MARGIN-self::RIGHT_TEXT_MARGIN,
                           300,
                           self::LEFT_TEXT_MARGIN, 100+4*self::FONT_SIZE,
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
                          PDFLetter::LEFT_TEXT_MARGIN, 100+4*PDFLetter::FONT_SIZE,
                          '<table>
  <tr><td>Blah</td></tr>
</table>');

      //Close and output PDF document
      return $pdf->Output($name, $dest);
    }
  } // class PDFLetter
    
} // namespace CAFEVDB

?>
