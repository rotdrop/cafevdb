/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PmeSelectGroup, PmeSelectOption, PmeSelectValue } from './pme-select.ts';

export type ChosenSelectValue = PmeSelectValue;

export interface ChosenSelectProps {
  options: PmeSelectOption[];
  groups?: PmeSelectGroup[];
  modelValue?: ChosenSelectValue;
  label: string;
  inputId?: string;
  placeholder?: string;
  multiple?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  required?: boolean;
  allowSingleDeselect?: boolean;
  disableSearch?: boolean;
  disableSearchThreshold?: number;
  noResultsText?: string;
  dropup?: boolean;
}

export interface ChosenSelectComponentProps extends ChosenSelectProps {
  onChange?: (value: ChosenSelectValue) => void;
  onOpen?: () => void;
  onClose?: () => void;
}

export interface ChosenSelectControls {
  setState(state: ChosenSelectProps): void;
  getValue(): ChosenSelectValue;
  open(): void;
  close(): void;
  focus(): void;
}
