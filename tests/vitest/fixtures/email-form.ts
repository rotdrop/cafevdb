/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EmailFormEnvelopeProps } from '~/src/types/email-form-envelope.ts';

export const emailForm: EmailFormEnvelopeProps['form'] = {
  recipients: '<input name="emailRecipientsFilter[selectedRecipients][]" value="42">',
  composer: '<input name="emailComposer[subject]" value="Original"><textarea>Message</textarea>',
  formData: '<input type="hidden" name="requesttoken" value="csrf-token">',
  uploadAction: '/upload',
  uploadMaxHumanFilesize: '512 MB',
  requestToken: 'csrf-token',
  wikiNamespace: 'orchestra',
  projectId: 0,
  projectName: null,
  filterHistory: { historySize: 1, historyPosition: 0 },
};
