import { beforeEach } from 'vitest';

declare global {
  // eslint-disable-next-line camelcase
  var _oc_webroot: string;
}
// This is here because getRootUrl() of @nextcloud/router is broken.
globalThis._oc_webroot = '';

beforeEach(() => {
  const el = document.createElement('div');
  el.id = 'skip-actions';
  document.body.appendChild(el);
});
