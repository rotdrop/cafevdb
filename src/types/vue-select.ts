/**
 * SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export type VueSelectValue = string|string[]|null;

export interface VueSelectOption {
  value: string;
  label: string;
  groups?: string[];
  disabled?: boolean;
  title?: string;
}

export interface VueSelectGroup {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface VueSelectCreateOptions {
  /** App-relative validation endpoint, as in the PHP field configuration. */
  url?: string;
  post?: Record<string, string|number|boolean>;
  inputField?: string;
  valueField?: string;
  labelField?: string;
  resolve?: (input: string, signal: AbortSignal) => Promise<VueSelectOption>;
}

export interface VueSelectProps {
  options: VueSelectOption[];
  label: string;
  rootClass?: string;
  modelValue?: VueSelectValue;
  groups?: VueSelectGroup[];
  multiple?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  locked?: boolean;
  required?: boolean;
  /** Prevent clearing the last selection, independently of form validation. */
  notEmpty?: boolean;
  sortable?: boolean;
  /** Show sorting buttons always (default), only on keyboard focus, or never. */
  showMoveButtons?: boolean|'focus';
  placeholder?: string;
  inputId?: string;
  /** Optional form field name, including [] if required by the receiver. */
  name?: string;
  create?: boolean|VueSelectCreateOptions;
  createOnBlur?: boolean;
  persist?: boolean;
  closeAfterSelect?: boolean;
  restoreOnBackspace?: boolean;
  allowSingleDeselect?: boolean;
  disableSearch?: boolean;
  disableSearchThreshold?: number;
  noResultsText?: string;
  /** Auto prefers down, flipping up only when there is more space above. */
  dropup?: boolean|'auto';
  hideSelected?: boolean;
  removeOnBackspace?: boolean;
  /** Keep values in native option order rather than selection order. */
  nativeOrder?: boolean;
  /** Treat the native blank option as a placeholder, not a choice. */
  blankPlaceholder?: boolean;
  /** Preserve values whose options are temporarily unavailable. */
  retainMissingValues?: boolean;
  searchValues?: boolean;
  labelOutside?: boolean;
  multiplePlaceholder?: boolean;
}

export interface VueSelectComponentProps extends VueSelectProps {
  onChange?: (value: VueSelectValue) => void;
  onOpen?: () => void;
  onClose?: () => void;
  onCreate?: (option: VueSelectOption) => void;
}

export interface VueSelectControls {
  setState(state: VueSelectProps): void;
  setRequired(value: boolean): void;
  getValue(): VueSelectValue;
  getOptions(): VueSelectOption[];
  setValue(value: VueSelectValue, silent?: boolean): void;
  setOptions(options: VueSelectOption[], groups?: VueSelectGroup[]): void;
  clear(silent?: boolean): void;
  moveItem(index: number, offset: number): void;
  createItem(input: string): Promise<boolean>;
  open(): void;
  close(): void;
  focus(): void;
  lock(): void;
  unlock(): void;
  disable(): void;
  enable(): void;
  isLocked: boolean;
  isDisabled: boolean;
  creating: boolean;
}
