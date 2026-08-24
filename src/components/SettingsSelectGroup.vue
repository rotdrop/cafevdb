<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 -
 - @file
 - Wrap an NcSelect into a coponent with submit button.
 -->
<template>
  <SelectWithSubmitButton ref="select"
                          v-bind="$attrs"
                          v-model="inputValObject"
                          label="displayname"
                          :reduce="reduceGroup"
                          :options="groupsArray"
                          :optionsLimit="100"
                          :placeholder="label"
                          :inputLabel="label"
                          :loading="isLoading"
                          :hint="hint"
                          :multiple="false"
                          :closeOnSelect="true"
                          :disabled="disabled"
                          :clearable="clearable"
                          @search="findGroups"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="groupInfoPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="groupInfoPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>

<script setup lang="ts">
import type { NcSelect } from '@nextcloud/vue'
import type { CloudGroup } from '../stores/cloud-users-groups.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcEllipsisedOption } from '@nextcloud/vue'
import { storeToRefs } from 'pinia'
import {
  computed,
  ref,
  useTemplateRef,
  watch,
} from 'vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import { appName } from '../config.ts'
import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.ts'
import { groupInfoPopup } from '../util/user-info-popup.ts'

type ValueObject = CloudGroup | { id: string, displayname: string }

const props = withDefaults(
  defineProps<{
    label: string
    hint?: string
    value?: string
    disabled?: boolean
    // clearable allows deselection of the last item
    clearable?: boolean
    loading?: boolean
    loadingIndicator?: boolean
  }>(),
  {
    hint: '',
    value: '',
    disabled: false,
    // eslint-disable-next-line vue/no-boolean-default
    clearable: true,
    loading: false,
    // eslint-disable-next-line vue/no-boolean-default
    loadingIndicator: true,
  },
)

const emit = defineEmits([
  'error',
])

const store = useCloudUsersGroupsStore()
const { groups } = storeToRefs(store)

const inputValObject = ref<undefined|ValueObject>(undefined)
const ajaxLoading = ref(false)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const groupsArray = computed(() => Object.values(groups.value))

const reduceGroup = (group: ValueObject) => group.id
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const errorHandler = <T extends Error>(error: T | any) => emit('error', error)
const getGroup = (groupId: string) => store.getGroup(groupId, errorHandler)
const getGroupObject = async (id: string) => {
  return (await getGroup(id)) || { id, displayname: id }
}
const findGroups = (query: string) => store.findGroups(query, errorHandler)

/**
 * This watcher catches changed property values and promotes the
 * changed value to the wrapped select.
 *
 * @param newValue New GID set from outside
 */
watch(() => props.value, async (newValue) => {
  if (ajaxLoading.value) {
    return
  }
  if (!newValue) {
    inputValObject.value = undefined
    return
  }
  ajaxLoading.value = true
  inputValObject.value = await getGroupObject(newValue)
  ajaxLoading.value = false
})

const select = useTemplateRef<typeof SelectWithSubmitButton>('select')
const ncSelect = computed(() => select.value?.ncSelect as (typeof NcSelect|null))
</script>
