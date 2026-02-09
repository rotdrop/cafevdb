<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\PageRenderer;

use DOMDocument;

// https://symfony.com/doc/current/components/dom_crawler.html#forms
use Symfony\Component\DomCrawler;

/** Fetch form values as at PHP post-style array. */
trait GetFormValuesTrait
{
  /**
   * @param string $html
   *
   * @param string $selector
   *
   * @return array
   */
  private function getFormValues(string $html, string $selector = 'form.pme-form'): array
  {
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertTrue($domDoc->loadHTML($html, LIBXML_PEDANTIC));
    $crawler = new DomCrawler\Crawler($html, uri: 'https://localhost/cafevdb', baseHref: 'https://localhost');
    // The Symfony form omits non-disabled inputs if there are also disabled
    // inputs with the same name. As disabled inputs are excluded anyway from
    // form values just filter out all disabled elements and only then fetch
    // the form values.
    $crawler->filter('[disabled]')->each(function(DomCrawler\Crawler $crawler) {
      foreach ($crawler as $node) {
        $node->parentNode->removeChild($node);
      }
    });
    $form = $crawler->filter($selector)->form();
    return $form->getPhpValues();
  }
}
