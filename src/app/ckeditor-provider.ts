/**
 * @file
 *
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
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
 *
 * @module
 */

import {
  Autoformat,
  BlockQuote,
  Bold,
  CKBox,
  CKFinder,
  CKFinderUploadAdapter,
  ClassicEditor,
  CloudServices,
  Essentials,
  Heading,
  Image,
  ImageCaption,
  ImageStyle,
  ImageToolbar,
  ImageUpload,
  Indent,
  Italic,
  Link,
  List,
  MediaEmbed,
  Paragraph,
  PasteFromOffice,
  PictureEditing,
  Table,
  TableToolbar,
  TextTransformation,
} from 'ckeditor5';

import 'ckeditor5/ckeditor5.css';

class Editor extends ClassicEditor {
  static builtinPlugins = [
    Essentials,
    CKFinderUploadAdapter,
    Autoformat,
    Bold,
    Italic,
    BlockQuote,
    CKBox,
    CKFinder,
    CloudServices,
    Heading,
    Image,
    ImageCaption,
    ImageStyle,
    ImageToolbar,
    ImageUpload,
    Indent,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    PasteFromOffice,
    PictureEditing,
    Table,
    TableToolbar,
    TextTransformation,
  ];

  static defaultConfig = {
    toolbar: {
      items: [
        'undo',
        'redo',
        '|',
        'heading',
        '|',
        'bold',
        'italic',
        '|',
        'link',
        'uploadImage',
        'insertTable',
        'blockQuote',
        'mediaEmbed',
        '|',
        'bulletedList',
        'numberedList',
        'outdent',
        'indent',
      ],
    },
    image: {
      toolbar: [
        'imageStyle:inline',
        'imageStyle:block',
        'imageStyle:side',
        '|',
        'toggleImageCaption',
        'imageTextAlternative',
      ],
    },
    table: {
      contentToolbar: [
        'tableColumn',
        'tableRow',
        'mergeTableCells',
      ],
    },
    language: 'en',
  };
}

export default Editor;
