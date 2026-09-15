import {
  beforeAll,
  beforeEach,
  vi,
} from 'vitest';

// This is here because getRootUrl() of @nextcloud/router is broken.
globalThis._oc_webroot = '';
globalThis.OC = globalThis.OC ?? { config: { versionstring: '33.0.0' } };

beforeAll(() => {
  vi.resetModules();
});

beforeEach(() => {
  const el = document.createElement('div');
  el.id = 'skip-actions';
  document.body.appendChild(el);
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
