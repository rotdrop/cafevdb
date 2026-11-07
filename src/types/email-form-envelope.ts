/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface EmailFormEnvelopeProps {
  contents: string;
  title: string;
  onReady?: (container: HTMLElement) => void;
  onClose?: () => void;
  onCleanup?: (container: HTMLElement) => void;
}

export interface EmailFormEnvelopeControls {
  setTitle: (title: string) => void;
  setBlocked: (blocked: boolean) => void;
  show: () => void;
}
