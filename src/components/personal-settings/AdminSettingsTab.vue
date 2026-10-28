<!--
 - Orchestra member, musician and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@rotdrop.de>
 - @license AGPL-3.0-or-later
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -->
<template>
  <NcSettingsSection :id="props.entry.id" :name="props.entry.name">
    <div v-for="group in props.groups"
         :id="group.id"
         :key="group.id"
         class="settings-subsection"
    >
      <h3>{{ group.name }}</h3>
      <template v-for="field in group.fields" :key="field.key">
        <div v-if="field.heading" class="admin-setting-heading">
          <hr>
          <h4>{{ field.heading }}</h4>
        </div>
        <div class="admin-setting">
          <NcCheckboxRadioSwitch v-if="field.type === 'checkbox'"
                                 :modelValue="values[field.key] === true || values[field.key] === 'on'"
                                 :disabled="saving[field.key]"
                                 @update:modelValue="save(field, $event)"
          >
            {{ field.label }}
          </NcCheckboxRadioSwitch>
          <template v-else-if="field.type === 'folder' || field.type === 'owner'">
            <NcTextField :id="field.key"
                         :label="field.label"
                         :modelValue="String(values[field.key] ?? '')"
                         :disabled="saving[field.key] || (!!saved[field.key] && !force[field.key])"
                         @update:modelValue="values[field.key] = $event"
            />
            <NcCheckboxRadioSwitch v-model="force[field.key]">
              {{ t(appId, 'Force') }}
            </NcCheckboxRadioSwitch>
            <NcButton :disabled="saving[field.key] || (!values[field.key] && field.type === 'owner')"
                      @click="save(field, values[field.key], true)"
            >
              {{ t(appId, field.type === 'folder' || field.type === 'owner' ? 'Check' : 'Set') }}
            </NcButton>
          </template>
          <NcSelect v-else-if="field.type === 'select'"
                    :inputId="field.key"
                    :inputLabel="field.label"
                    :modelValue="selectedOption(field)"
                    :options="field.options ?? []"
                    :clearable="false"
                    :disabled="saving[field.key]"
                    @update:modelValue="save(field, $event?.value)"
          />
          <WysiwygField v-else-if="field.type === 'wysiwyg'"
                        :label="field.label"
                        :modelValue="String(values[field.key] ?? '')"
                        :disabled="saving[field.key]"
                        @update:modelValue="values[field.key] = $event"
                        @blur="save(field, $event)"
                        @error="emit('error', $event)"
          />
          <NcTextArea v-else-if="field.type === 'textarea'"
                      :id="field.key"
                      :label="field.label"
                      :modelValue="String(values[field.key] ?? '')"
                      :disabled="saving[field.key]"
                      rows="4"
                      @update:modelValue="values[field.key] = $event"
                      @blur="save(field, values[field.key])"
          />
          <NcPasswordField v-else-if="field.type === 'password'"
                           :id="field.key"
                           :label="field.label"
                           :modelValue="String(values[field.key] ?? '')"
                           :disabled="saving[field.key]"
                           @update:modelValue="values[field.key] = $event"
                           @blur="save(field, values[field.key])"
          />
          <template v-else>
            <NcButton v-if="field.linkTest"
                      :href="String(values[field.key] ?? '') || undefined"
                      :disabled="!values[field.key]"
                      target="_blank"
                      rel="noopener noreferrer"
            >
              {{ t(appId, 'Test Link') }}
            </NcButton>
            <NcTextField :id="field.key"
                         :label="field.label"
                         :type="field.type === 'number' ? 'number' : 'text'"
                         :modelValue="String(values[field.key] ?? '')"
                         :disabled="saving[field.key]"
                         @update:modelValue="values[field.key] = $event"
                         @blur="save(field, values[field.key])"
            />
          </template>
        </div>
      </template>
      <slot v-if="group.slot" :name="group.slot" />
    </div>
  </NcSettingsSection>
</template>

<script setup lang="ts">
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch, NcPasswordField, NcSelect, NcSettingsSection, NcTextArea, NcTextField } from '@nextcloud/vue'
import { NcButton } from '@nextcloud/vue'
import { reactive } from 'vue'
import WysiwygField from './WysiwygField.vue'
import { appName as appId } from '../../config.ts'
import { setAdminSetting } from '../../services/admin-settings.ts'
import { showSettingError, showSettingFeedback } from '../../services/setting-feedback.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'

export type AdminSettingOption = { value: string|number|boolean, label: string }
export type AdminSettingField = {
  key: string
  label: string
  type: 'text'|'number'|'textarea'|'checkbox'|'select'|'password'|'wysiwyg'|'folder'|'owner'
  options?: AdminSettingOption[]
  initialValueKey?: string
  heading?: string
  linkTest?: boolean
  initialValue?: unknown
}
export type AdminSettingGroup = {
  id: string
  name: string
  fields: AdminSettingField[]
  slot?: string
}

const props = defineProps<{ entry: TabEntry, groups: AdminSettingGroup[] }>()
const emit = defineEmits<{ error: [error: Error], saved: [key: string, value: unknown] }>()

const initialState = getInitialState<{ [key: string]: unknown }>({
  section: 'adminSettings',
  defaults: {},
})
const initialValues: Record<string, unknown> = { ...(initialState ?? {}) }
for (const field of props.groups.flatMap(({ fields }) => fields)) {
  if (field.initialValueKey) {
    initialValues[field.key] = initialState?.[field.initialValueKey]
  } else if (field.initialValue !== undefined) {
    initialValues[field.key] = field.initialValue
  }
}
const values = reactive<Record<string, unknown>>(initialValues)
const saved = reactive<Record<string, unknown>>({ ...initialValues })
const saving = reactive<Record<string, boolean>>({})
const force = reactive<Record<string, boolean>>({})

const selectedOption = (field: AdminSettingField) =>
  field.options?.find(({ value }) => String(value) === String(values[field.key] ?? '')) ?? null

const save = async (field: AdminSettingField, value: unknown, forceSubmit = false) => {
  const previous = saved[field.key]
  if (Object.is(previous, value) && !forceSubmit) {
    return
  }
  values[field.key] = value
  saving[field.key] = true
  try {
    let payload: unknown = value
    if (field.type === 'folder') {
      payload = {
        [field.key]: value,
        [`${field.key}-saved`]: previous ?? '',
        [`${field.key}-force`]: !!force[field.key],
      }
    } else if (field.type === 'owner') {
      payload = {
        [field.key]: value,
        'shareowner-saved': previous ?? '',
        'shareowner-force': !!force[field.key],
      }
    }
    const response = await setAdminSetting(field.key, payload)
    if (response.data.value !== undefined) {
      const responseValue = response.data.value
      if (typeof responseValue === 'object' && responseValue !== null
        && 'name' in responseValue && typeof responseValue.name === 'string') {
        values[field.key] = responseValue.name
      } else {
        values[field.key] = responseValue
      }
    } else if (response.data.number !== undefined) {
      values[field.key] = response.data.number
    }
    if (field.type === 'password') {
      values[field.key] = ''
      saved[field.key] = ''
    } else {
      saved[field.key] = values[field.key]
    }
    force[field.key] = false
    showSettingFeedback(response.data, field.label)
    emit('saved', field.key, values[field.key])
  } catch (error) {
    values[field.key] = previous
    if (!showSettingError(error, field.label)) {
      emit('error', error instanceof Error ? error : new Error(String(error)))
    }
  } finally {
    saving[field.key] = false
  }
}
</script>

<style lang="scss" scoped>
.settings-subsection {
  margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.admin-setting-heading {
  margin-block-start: calc(var(--default-grid-baseline) * 4);

  hr {
    border: none;
    border-block-start: 1px solid var(--color-border-dark);
  }

  h4 {
    margin-block: calc(var(--default-grid-baseline) * 2) 0;
    font-weight: bold;
  }
}

.admin-setting {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  margin-block: calc(var(--default-grid-baseline) * 2);

  :deep(.input-field),
  :deep(.select) {
    flex: 1 1 18rem;
    min-width: 12rem;
  }
}
</style>
