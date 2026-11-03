/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { PmeSelectGroup, PmeSelectOption } from '../types/pme-select.ts';

const plainTitle = (title: string) => {
  const template = document.createElement('template');
  template.innerHTML = title.replace(/<br\s*\/?>/gi, '\n');
  return template.content.textContent || undefined;
};

export const readSelectOptions = (select: HTMLSelectElement) => {
  const groups: PmeSelectGroup[] = [];
  const ids = new Map<HTMLOptGroupElement, string>();
  for (const [index, group] of Array.from(select.querySelectorAll<HTMLOptGroupElement>(':scope > optgroup')).entries()) {
    const value = group.dataset.groupId || (group.classList.contains('free-form') ? 'free-form' : String(index));
    ids.set(group, value);
    groups.push({ value, label: group.label, disabled: group.disabled });
  }
  const options = Array.from(select.options, (option): PmeSelectOption => ({
    value: option.value,
    label: option.label || option.textContent || option.value,
    groups: option.parentElement instanceof HTMLOptGroupElement ? [ids.get(option.parentElement)!] : [],
    disabled: option.disabled,
    title: option.title ? plainTitle(option.title) : undefined,
  }));
  return { options, groups };
};

export const appendSelectOption = (select: HTMLSelectElement, option: PmeSelectOption, groups: PmeSelectGroup[]) => {
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
