/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { SelectizeAdapterOptions, SelectizeService } from './mountable-select.ts';

import { getMountedSelectize, mountSelectize } from './mountable-select.ts';

const scopes = new WeakMap<HTMLElement, MountedSelectScope>();

export class MountedSelectScope {
  private readonly controls = new Set<SelectizeService>();
  private disposed = false;

  constructor(private readonly container: HTMLElement) {}

  async mount(select: HTMLSelectElement, options: SelectizeAdapterOptions = {}) {
    if (this.disposed) {
      throw new Error('Cannot mount a select in a disposed scope.');
    }
    const request = mountSelectize(select, options);
    const pending = getMountedSelectize(select);
    if (pending) {
      this.controls.add(pending);
    }
    const [control] = await request;
    if (this.disposed || !this.container.contains(select)) {
      control.destroy();
    }
    return control;
  }

  destroy() {
    if (this.disposed) {
      return;
    }
    this.disposed = true;
    this.controls.forEach((control) => control.destroy());
    this.controls.clear();
    scopes.delete(this.container);
  }
}

export const mountedSelectScope = (container: HTMLElement) => {
  let scope = scopes.get(container);
  if (!scope) {
    scope = new MountedSelectScope(container);
    scopes.set(container, scope);
  }
  return scope;
};

export const destroyMountedSelects = (container: HTMLElement) => {
  scopes.get(container)?.destroy();
  for (const select of container.querySelectorAll('select')) {
    getMountedSelectize(select)?.destroy();
  }
};
