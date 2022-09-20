<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License alogng with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * @param int $fieldId
 * @param string $optionKey
 * @param string $optionValue
 * @param string $subDir
 * @param string $fileBase
 * @param string $fileName
 * @param string $filesAppPath
 * @param string $filesAppTarget
 * @param string $uploadPolicy
 * @param string $participantFolder
 * @param string $filesAppLink
 * @param string $downloadLink
 * @param string $optionValueName
 * @param string $uploadPlaceHolder
 * @param array $toolTips
 * @param string $toolTipsPrefix
 */

echo $this->inc('fragments/participant-fields/attachment-file-upload-row', [
  'dataStorage' => 'cloud',
  'entityField' => 'option-value',
]);
