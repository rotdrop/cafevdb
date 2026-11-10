/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EmailWebFormResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts';

export type EmailFormTab = 'recipients' | 'composer' | 'status';

export interface EmailFormEnvelopeProps {
  form: EmailWebFormResponse;
  title: string;
  single: boolean;
}

export type EmailFormCommand =
  | { action: 'show' }
  | { action: 'title'; title: string }
  | { action: 'blocked'; blocked: boolean }
  | { action: 'select-tab'; tab: EmailFormTab }
  | { action: 'enable-recipients' };

export type EmailFormEvent =
  | { action: 'ready' | 'cleanup'; container: HTMLElement }
  | { action: 'close' }
  | { action: 'before-tab-change' | 'tab-changed'; previous: EmailFormTab; tab: EmailFormTab; panel: HTMLElement };
