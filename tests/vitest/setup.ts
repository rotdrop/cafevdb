/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import {
  // beforeAll,
  beforeEach,
  vi,
} from 'vitest';

// This is here because getRootUrl() of @nextcloud/router is broken.
globalThis._oc_webroot = '';
globalThis.OC = globalThis.OC ?? { config: { versionstring: '33.0.0' } };

// beforeAll(() => {
//   // vi.resetModules();
//   // jsdom.reconfigure({
//   //   url: 'http://localhost',
//   // });
//   // console.info('BEFORE ALL', { document, body: document.body });
// });

beforeEach(() => {
  const el = document.createElement('div');
  el.id = 'skip-actions';
  document.body.appendChild(el);
  document.head.appendChild(document.createElement('title'));
});

const ResizeObserverMock = class {
  observe(): void {
    // void
  }

  unobserve(): void {
    // void
  }

  disconnect(): void {
    // void
  }
};

// Stub the global ResizeObserver API
vi.stubGlobal('ResizeObserver', ResizeObserverMock);

// jsdom does not implement the SVG animated length/rect interfaces which are
// used by DynamicSvgIcon.vue, provide minimal stand-ins.
if (!('SVGLength' in globalThis)) {
  vi.stubGlobal('SVGLength', { SVG_LENGTHTYPE_NUMBER: 1 });
}
if (typeof SVGSVGElement !== 'undefined' && !('width' in SVGSVGElement.prototype)) {
  const animatedLength = () => ({
    baseVal: {
      value: 0,
      newValueSpecifiedUnits(_unitType: number, value: number) {
        this.value = value;
      },
    },
  });
  for (const property of ['width', 'height']) {
    const key = Symbol(property);
    Object.defineProperty(SVGSVGElement.prototype, property, {
      configurable: true,
      get() {
        return this[key] ?? (this[key] = animatedLength());
      },
    });
  }
  const viewBoxKey = Symbol('viewBox');
  Object.defineProperty(SVGSVGElement.prototype, 'viewBox', {
    configurable: true,
    get() {
      return this[viewBoxKey] ?? (this[viewBoxKey] = { baseVal: { x: 0, y: 0, width: 0, height: 0 } });
    },
  });
}
