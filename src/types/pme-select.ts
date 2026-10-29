/**
 * SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export type PmeSelectValue = string|string[]|null;

export interface PmeSelectOption {
  value: string;
  label: string;
  groups?: string[];
  disabled?: boolean;
}

export interface PmeSelectGroup {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface PmeSelectCreateOptions {
  /** App-relative validation endpoint, as in the PHP field configuration. */
  url?: string;
  post?: Record<string, string|number|boolean>;
  inputField?: string;
  valueField?: string;
  labelField?: string;
}

export interface PmeSelectProps {
  options: PmeSelectOption[];
  label: string;
  rootClass?: string;
  modelValue?: PmeSelectValue;
  groups?: PmeSelectGroup[];
  multiple?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  locked?: boolean;
  required?: boolean;
  /** Prevent clearing the last selection, independently of form validation. */
  notEmpty?: boolean;
  sortable?: boolean;
  placeholder?: string;
  inputId?: string;
  /** Optional form field name, including [] if required by the receiver. */
  name?: string;
  create?: boolean|PmeSelectCreateOptions;
  createOnBlur?: boolean;
  persist?: boolean;
}

export interface PmeSelectComponentProps extends PmeSelectProps {
  onChange?: (value: PmeSelectValue) => void;
  onOpen?: () => void;
  onClose?: () => void;
}
