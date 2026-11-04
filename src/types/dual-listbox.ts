/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface DualListboxOption {
  value: string;
  label: string;
  disabled?: boolean;
  title?: string;
}

export interface DualListboxProps {
  options: DualListboxOption[];
  modelValue: string[];
  inputId?: string;
  disabled?: boolean;
  readonly?: boolean;
}

export interface DualListboxComponentProps extends DualListboxProps {
  onChange?: (values: string[]) => void;
}

export interface DualListboxControls {
  setState(state: DualListboxProps): void;
}
