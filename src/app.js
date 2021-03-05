/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { nonce, webRoot } from './app/globals.js';
import { runReadyCallbacks } from './app/cafevdb.js';
import settings from './app/settings.js';
import appSettings from './app/app-settings.js';
import personalSettings from './app/personal-settings.js';
import exportMode from './app/expertmode.js';
// import backgroundJobs from './app/backgroundjobs.js';
import { documentReady as pageDocumentReady } from './app/page.js';
import { documentReady as projectExtraDocumentReady } from './app/project-extra.js';
import { documentReady as debitNotesDocumentReady } from './app/debit-notes.js';
import { documentReady as musiciansDocumentReady } from './app/musicians.js';
import { documentReady as projectParticipantsDocumentReady } from './app/project-participants.js';
import { documentReady as sepaDebitMandatesDocumentReady } from './app/sepa-debit-mandate.js';
import { documentReady as projectsDocumentReady } from './app/projects.js';
import { documentReady as projectInstrumentationNumbersDocumentReady } from './app/project-instrumentation-numbers.js';
import { documentReady as blogDocumentReady } from './app/blog.js';
import { documentReady as emailDocumentReady } from './app/email.js';
import { documentReady as insurancesDocumentReady } from './app/insurance.js';
import { documentReady as phpMyEditDocumentReady } from './app/pme.js';
import { documentReady as configCheckDocumentReady } from './app/configcheck.js';
import beforeReady from './app/before-ready.js';
import './app/jquery-extensions.js';

jQuery = require('jquery');
require('jquery-ui');

require('navsnapper.css');

__webpack_public_path__ = webRoot;
__webpack_nonce__ = nonce;

$(function() {
  configCheckDocumentReady();
  blogDocumentReady();
  emailDocumentReady();
  musiciansDocumentReady();
  projectParticipantsDocumentReady();
  pageDocumentReady();
  projectsDocumentReady();
  projectExtraDocumentReady();
  projectInstrumentationNumbersDocumentReady();
  debitNotesDocumentReady();
  sepaDebitMandatesDocumentReady();
  insurancesDocumentReady();
  phpMyEditDocumentReady();
  // backgroundJobs();
  settings();
  exportMode();
  appSettings();
  personalSettings();
  beforeReady();
  runReadyCallbacks();
});

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
