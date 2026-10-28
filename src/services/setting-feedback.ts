/**
 * @file
 *
 * @license AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n';
import { appName as appId } from '../config.ts';
import { showError, showSuccess } from '../toolkit/util/toasts.ts';

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

/**
 * Tell the user that the controller rejected a setting (HTTP 4xx with a
 * message, e.g. a validation failure). Suggestions of the controller are
 * appended.
 *
 * @param error The error thrown by the request.
 *
 * @param label Human-readable name of the setting for the fallback text.
 *
 * @return True if the failure was a controller rejection and has been
 * reported, false if it is some other error which the caller must handle.
 */
export const showSettingError = (error: unknown, label: string): boolean => {
  const response = (error as { response?: { status?: number; data?: FeedbackData & { suggestions?: string[] } } })?.response;
  const status = response?.status ?? 0;
  if (status < 400 || status >= 500) {
    return false;
  }
  const raw = response?.data?.messages ?? response?.data?.message ?? [];
  let text = (Array.isArray(raw) ? raw : [raw])
    .filter((message): message is string => typeof message === 'string' && message.trim() !== '')
    .join(' ');
  if (!text) {
    text = t(appId, 'Setting "{label}" could not be saved.', { label });
  }
  const suggestions = response?.data?.suggestions ?? [];
  if (suggestions.length > 0) {
    text += ' ' + t(appId, 'Did you mean: {suggestions}?', { suggestions: suggestions.join(', ') });
  }
  showError(text);
  return true;
};
