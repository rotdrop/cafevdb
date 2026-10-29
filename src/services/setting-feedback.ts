/**
 * @file
 *
 * @license AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n';
import { appName as appId } from '../config.ts';
import { showError, showSuccess, TOAST_PERMANENT_TIMEOUT } from '../toolkit/util/toasts.ts';

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
 * Tell the user that the controller rejected a setting with HTTP 400 and an
 * explanation (JSON data with a non-empty "message" or "messages", each of
 * which may be a string or an array). The concatenated messages, plus any
 * suggestions, are shown as permanent error toast.
 *
 * @param error The error thrown by the request.
 *
 * @return True if the failure was such a rejection and has been reported,
 * false if it is some other error which the caller must forward.
 */
export const showSettingError = (error: unknown): boolean => {
  const response = (error as { response?: { status?: number; data?: FeedbackData & { suggestions?: string[] } } })?.response;
  if (response?.status !== 400) {
    return false;
  }
  const collect = (raw: unknown) => (Array.isArray(raw) ? raw : [raw])
    .filter((message): message is string => typeof message === 'string' && message.trim() !== '');
  const messages = [
    ...collect(response.data?.messages),
    ...collect(response.data?.message),
  ];
  if (messages.length === 0) {
    return false;
  }
  let text = [...new Set(messages)].join(' ');
  const suggestions = response.data?.suggestions ?? [];
  if (suggestions.length > 0) {
    text += ' ' + t(appId, 'Did you mean: {suggestions}?', { suggestions: suggestions.join(', ') });
  }
  showError(text, { timeout: TOAST_PERMANENT_TIMEOUT });
  return true;
};
