<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**@file
 * Support for inline-image data and stuff (with db-storage).
 */

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB {

  class InlineImage
  {
    const TABLE = 'ImageData';
    const IMAGE_DATA = 1;
    const IMAGE_META_DATA = 2;

    protected $itemTable;
    protected $imageData;
    protected $itemId;

    function __construct($table)
    {
      $this->itemTable = $table;
      $this->imageData = null;
      $this->itemId = -1;
    }

    public function placeHolder()
    {
      return strtolower($this->itemTable).'-placeholder.png';
    }

    /**Fetch the corresponding row from the image-data table. If none
     * is found false is returned.
     */
    public function fetch($itemId, $fieldSelector = self::IMAGE_DATA|self::IMAGE_META_DATA, $handle = false)
    {
      $this->imageData = null;
      $this->itemId = -1;

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $fields = array();
      if ($fieldSelector & self::IMAGE_META_DATA) {
        $fields[] = 'MimeType';
        $fields[] = 'MD5';
      }
      if ($fields & self::IMAGE_DATA) {
        $fields[] = 'Data';
      }
      if (empty($fields)) {
        $imageData = array(); // not an error, but useless
      } else {
        $fields = "`".implode("`,`", $fields)."`";

        $query = "SELECT ".$fields." FROM `".self::TABLE."`
 WHERE `ItemId` = ".$itemId." AND `ItemTable` LIKE '".$this->itemTable."'";

        $result = mySQL::query($query, $handle);

        if ($result !== false && mySQL::numRows($result) == 1) {
          $this->imageData = mySQL::fetch($result);
          $this->itemId = $itemId;
        }
      }

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $this->imageData;
    }

    /**Take a BASE64 encoded photo and store it in the DB.
     *
     * @param[in] integer $itemId Unique Id in respective DB-table.
     *
     * @param[in] string $imageData Raw image data or a data-uri (only
     * base64 is supported, and only image URIs).
     *
     * @param[in] string $mimeType The mime-type. Will be determined
     * from @a $imageData if not given.
     *
     * @param[in] resource $handle Data-base handle. A new connection
     * will be opened (and closed) if unset.
     *
     * @return boolean @c true in case of success.
     */
    public function store($itemId, $imageData, $mimeType = null, $handle = false)
    {
      if (!isset($imageData) || $imageData == '') {
        return false;
      }

      // first check whether $imageData is a data-uri.
      if (preg_match('|^data:(image/[^;]+);base64\\\?,|', $row['Photo'], $matches)) {
        $mimeType = $matches[1];
        $imageData = substr($row['Photo'], strlen($matches[0]));
      } else if (!$mimeType) {
        $image = new \OC_Image();
        if (!$image->loadFromBase64($imageData)) {
          return false;
        }
        $mimeType = $image->mimeType();
      }

      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $md5 = md5($imageData);
      $query = "INSERT INTO `".self::TABLE."`
  (`ItemId`,`ItemTable`,`MimeType`,`MD5`,`Data`)
  VALUES
  (".$itemId.",'".$this->itemTable."','".$mimeType."','".$md5."','".$imageData."')
  ON DUPLICATE KEY UPDATE
  `MimeType` = '".$mimeType."', `MD5` = '".$md5."', `Data` = '".$imageData."';";

      $result = mySQL::query($query, $handle) && mySQL::storeModified($itemId, $this->itemTable, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

    public function delete($itemId, $handle = false)
    {
      $ownConnection = $handle === false;
      if ($ownConnection) {
        Config::init();
        $handle = mySQL::connect(Config::$pmeopts);
      }

      $query = "DELETE IGNORE FROM `".self::TABLE."`
 WHERE `ItemTable` = '".$this->itemTable."' AND `ItemId` = ".$itemId;

      $result = mySQL::query($query, $handle) && mySQL::storeModified($itemId, $this->itemTable, $handle);

      if ($ownConnection) {
        mySQL::close($handle);
      }

      return $result;
    }

  };

} // namespace

?>
