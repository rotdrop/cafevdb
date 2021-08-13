<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

trait MusicianPhotoTrait
{
  /**
   * Geneate code for a HTML-link for an optional photo.
   */
  public function photoImageLink($musicianId, $action, $imageId)
  {
    if (empty($imageId)) {
      $imageId = ImagesService::IMAGE_ID_ANY;
    }
    switch ($action) {
    case 'add':
      return $this->l->t("Photos or Avatars can only be added to an existing musician's profile; please add the new musician without protrait image first.");
    case 'display':
      $url = $this->urlGenerator()->linkToRoute(
        'cafevdb.images.get',
        [ 'joinTable' => self::MUSICIAN_PHOTO_JOIN_TABLE,
          'ownerId' => $musicianId ]);
      $url .= '?timeStamp='.time();
      $url .= '&imageId='.$imageId;
      $url .= '&requesttoken='.urlencode(\OCP\Util::callRegister());
      $div = ''
        .'<div class="photo image-wrapper single full"><img class="cafevdb_inline_image portrait zoomable" src="'.$url.'" '
        .'title="'.$this->l->t("Photo, if available").'" /></div>';
      return $div;
    case 'change':
      $imageInfo = json_encode([
        'ownerId' => $musicianId,
        'imageId' => $imageId,
        'joinTable' => self::MUSICIAN_PHOTO_JOIN_TABLE,
        'imageSize' => -1,
      ]);
      $photoarea = ''
        .'<div data-image-info=\''.$imageInfo.'\' class="tip musician-portrait portrait propertycontainer tooltip-top cafevdb_inline_image_wrapper image-wrapper single full" title="'
      .$this->l->t("Drop photo to upload (max %s)", [ \OCP\Util::humanFileSize(Util::maxUploadSize()) ]).'"'
        .' data-element="PHOTO">
  <ul class="phototools" class="transparent hidden contacts_property">
    <li><a class="svg delete" title="'.$this->l->t("Delete current photo").'"></a></li>
    <li><a class="svg edit" title="'.$this->l->t("Edit current photo").'"></a></li>
    <li><a class="svg upload" title="'.$this->l->t("Upload new photo").'"></a></li>
    <li><a class="svg cloud icon-cloud" title="'.$this->l->t("Select photo from Cloud").'"></a></li>
  </ul>
</div>'; // contact_photo

      return $photoarea;
    default:
      return $this->l->t("Internal error, don't know what to do concerning photos in the given context.");
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
