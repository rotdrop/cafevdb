/**
 * @file
 *
 * @license AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n';
import { appName as appId } from '../config.ts';
import { showSuccess } from '../toolkit/util/toasts.ts';

type FeedbackData = { messages?: Array<string|null>|string|null; message?: string|string[]|null };

/**
 * Tell the user that a setting has been sent to the controller and accepted.
 * The message of the controller is used if there is one, otherwise a generic
 * confirmation naming the setting is shown.
 *
 * @param data Response data of the controller.
 *
 * @param label Human-readable name of the setting for the fallback text.
 */
export const showSettingFeedback = (data: FeedbackData|undefined, label: string) => {
  const raw = data?.messages ?? data?.message ?? [];
  const text = (Array.isArray(raw) ? raw : [raw])
    .filter((message): message is string => typeof message === 'string' && message.trim() !== '')
    .join(' ');
  showSuccess(text || t(appId, 'Setting "{label}" saved.', { label }));
};
