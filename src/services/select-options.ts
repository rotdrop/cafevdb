/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { VueSelectGroup, VueSelectOption } from '../types/vue-select.ts';

const plainTitle = (title: string) => {
  const template = document.createElement('template');
  template.innerHTML = title.replace(/<br\s*\/?>/gi, '\n');
  return template.content.textContent || undefined;
};

export const readSelectOptions = (select: HTMLSelectElement) => {
  const groups: VueSelectGroup[] = [];
  const ids = new Map<HTMLOptGroupElement, string>();
  for (const [index, group] of Array.from(select.querySelectorAll<HTMLOptGroupElement>(':scope > optgroup')).entries()) {
    const value = group.dataset.groupId || (group.classList.contains('free-form') ? 'free-form' : String(index));
    ids.set(group, value);
    if (!groups.some((entry) => entry.value === value)) {
      groups.push({ value, label: group.label, disabled: group.disabled });
    }
  }
  const options = Array.from(select.options)
    .filter((option) => option.value !== '' || option.label !== '' || (option.textContent ?? '') !== '')
    .map((option): VueSelectOption => ({
      value: option.value,
      label: option.label || option.textContent || option.value,
      groups: option.parentElement instanceof HTMLOptGroupElement ? [ids.get(option.parentElement)!] : [],
      disabled: option.disabled,
      title: option.title ? plainTitle(option.title) : undefined,
    }));
  return { options, groups };
};

/**
 * Keep native submission in selection order, splitting interleaved optgroups.
 *
 * @param select Native multiple select.
 * @param values Selected values in submission order.
 */
export const orderSelectOptions = (select: HTMLSelectElement, values: string[]) => {
  const selected = Array.from(select.selectedOptions);
  if (selected.every((option, index) => option.value === values[index])) {
    return;
  }
  const options = Array.from(select.options);
  const positions = new Map(values.map((value, index) => [value, index]));
  options.sort((left, right) =>
    (positions.get(left.value) ?? values.length) - (positions.get(right.value) ?? values.length));
  const groups = new Map<string, HTMLOptGroupElement>();
  const optionGroups = new Map<HTMLOptionElement, string>();
  for (const [index, group] of Array.from(select.querySelectorAll<HTMLOptGroupElement>(':scope > optgroup')).entries()) {
    const id = group.dataset.groupId || (group.classList.contains('free-form') ? 'free-form' : String(index));
    group.dataset.groupId = id;
    if (!groups.has(id)) {
      groups.set(id, group);
    }
    for (const option of Array.from(group.children)) {
      if (option instanceof HTMLOptionElement) {
        optionGroups.set(option, id);
      }
    }
  }
  for (const group of groups.values()) {
    group.replaceChildren();
  }
  const fragment = document.createDocumentFragment();
  const usedGroups = new Set<string>();
  let currentGroup: HTMLOptGroupElement|undefined;
  for (const option of options) {
    const id = optionGroups.get(option);
    if (id === undefined) {
      fragment.append(option);
      currentGroup = undefined;
      continue;
    }
    if (currentGroup?.dataset.groupId !== id) {
      const original = groups.get(id)!;
      currentGroup = usedGroups.has(id) ? original.cloneNode(false) as HTMLOptGroupElement : original;
      if (usedGroups.has(id)) {
        currentGroup.removeAttribute('id');
      }
      fragment.append(currentGroup);
      usedGroups.add(id);
    }
    currentGroup.append(option);
  }
  for (const [id, group] of groups) {
    if (!usedGroups.has(id)) {
      fragment.append(group);
    }
  }
  select.replaceChildren(fragment);
};

export const appendSelectOption = (select: HTMLSelectElement, option: VueSelectOption, groups: VueSelectGroup[]) => {
  const nativeOption = new Option(option.label, option.value);
  nativeOption.disabled = option.disabled ?? false;
  nativeOption.title = option.title ?? '';
  const groupId = option.groups?.[0];
  if (groupId !== undefined) {
    const existing = Array.from(select.querySelectorAll('optgroup'))
      .find((group, index) => (group.dataset.groupId || (group.classList.contains('free-form') ? 'free-form' : String(index))) === groupId);
    const group = existing ?? document.createElement('optgroup');
    if (!existing) {
      group.dataset.groupId = groupId;
      group.label = groups.find((entry) => entry.value === groupId)?.label ?? groupId;
      select.append(group);
    }
    group.append(nativeOption);
  } else {
    select.add(nativeOption);
  }
  return nativeOption;
};

export const selectedOptionData = (select: HTMLSelectElement, values: string[]) =>
  values.flatMap((value) => {
    const option = Array.from(select.options).find((option) => option.value === value);
    return option ? [{ value, html: option.innerHTML, text: option.text }] : [];
  });
