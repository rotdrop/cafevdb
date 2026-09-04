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

import Selectize from 'selectize';
import $ from '~/src/app/jquery.ts';
import * as SelectUtils from '~/src/app/select-utils.ts';
import {
  beforeEach,
  describe,
  expect,
  it,
} from 'vitest';

beforeEach(() => {
  document.body.innerHTML = `<select id="single">
  <optgroup>
    <option value="one">One</option>
    <option value="two">Two</option>
    <option value="three">Three</option>
  </optgroup>
</select>
<select id="multiple" multiple>
  <option value="one">One</option>
  <option value="two">Two</option>
  <option value="three">Three</option>
</select>
`;
});

describe('Handle selectized select element', () => {
  it('detects selectize', () => {
    const $single = $<HTMLSelectElement>('#single');
    const $multiple = $<HTMLSelectElement>('#multiple');
    $single.selectize();
    expect(SelectUtils.selectizeActive($single)).toBeTruthy();
    expect(SelectUtils.selectizeActive($multiple)).toBeFalsy();
    expect(SelectUtils.isSelectizedJQuery($single)).toBeTruthy();
    expect(SelectUtils.isSelectizedJQuery($multiple)).toBeFalsy();
    expect(SelectUtils.getSelectize($single) instanceof Selectize).toBeTruthy();
    expect(SelectUtils.getSelectize($multiple)).toBeUndefined();
    expect(SelectUtils.getControlObject($single) instanceof Selectize).toBeTruthy();
    expect(SelectUtils.getControlObject($multiple)).toBeUndefined();
  });
  it('finds all children and options', () => {
    const $single = $<HTMLSelectElement>('#single');
    const $multiple = $<HTMLSelectElement>('#multiple');
    $single.selectize();
    expect(SelectUtils.children($single).length).toBe(1);
    expect(SelectUtils.children($multiple).length).toBe(3);
    expect(SelectUtils.options($single).length).toBe(3);
    expect(SelectUtils.options($multiple).length).toBe(3);
  });
  it('can determine the option values', () => {
    const $single = $<HTMLSelectElement>('#single');
    const $multiple = $<HTMLSelectElement>('#multiple');
    $single.selectize();
    expect(SelectUtils.optionValues($single).sort()).toEqual(['one', 'three', 'two']);
    expect(SelectUtils.optionValues($multiple).sort()).toEqual(['one', 'three', 'two']);
  });
  it('can set select value(s)', () => {
    const $single = $<HTMLSelectElement>('#single');
    const $multiple = $<HTMLSelectElement>('#multiple');
    $multiple.selectize();
    expect(SelectUtils.selected($single, 'two')).toEqual('one');
    expect(SelectUtils.selected($single)).toEqual('two');
    expect(SelectUtils.selected($multiple, ['two', 'three'])).toEqual([]);
    expect(SelectUtils.selected($multiple)).toEqual(['two', 'three']);
    expect(SelectUtils.selectedOptions($single).length).toEqual(1);
    expect(SelectUtils.selectedOptions($multiple).length).toEqual(2);
  });
  it('can determine the widget', () => {
    const $single = $<HTMLSelectElement>('#single');
    const $multiple = $<HTMLSelectElement>('#multiple');
    $multiple.selectize();
    expect(SelectUtils.widget($single)[0]).toBeInstanceOf(HTMLSelectElement);
    expect(SelectUtils.widget($multiple)[0]).toBeInstanceOf(HTMLDivElement);

    expect(SelectUtils.selectFromWidget(SelectUtils.widget($single))).toEqual($single);
    expect(SelectUtils.selectFromWidget(SelectUtils.widget($multiple))[0]).toEqual($multiple[0]);
  });
});
