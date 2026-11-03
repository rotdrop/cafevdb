/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface PmeNavigationOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface PmeNavigationSelectProps {
  /** Button caption, e.g. "Go to" or "Rows/Page". */
  caption: string;
  options: PmeNavigationOption[];
  modelValue?: string|null;
  inputId?: string;
  /** Shown instead of a value when nothing is selected. */
  placeholder?: string;
  disabled?: boolean;
  /** Search becomes available when there are more options than this. */
  searchThreshold?: number;
}

export interface PmeNavigationSelectComponentProps extends PmeNavigationSelectProps {
  onChange?: (value: string) => void;
}

export interface PmeNavigationSelectControls {
  setState(state: PmeNavigationSelectProps): void;
  getValue(): string|null;
  open(): void;
  close(): void;
  focus(): void;
}
