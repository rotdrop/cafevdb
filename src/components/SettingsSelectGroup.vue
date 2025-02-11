<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
                          :options-limit="100"
                          :placeholder="label"
                          :input-label="label"
                          :loading="isLoading"
                          :hint="hint"
                          :multiple="false"
                          :close-on-select="true"
                          :disabled="disabled"
                          :clearable="clearable"
                          v-on="$listeners"
                          @search="findGroups"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="groupInfoPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appId, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appId, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="groupInfoPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appId, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appId, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>
<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import type { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.ts'
import type { CloudGroup } from '../stores/cloud-users-groups.ts'
import { groupInfoPopup } from '../util/user-info-popup.ts'
import { storeToRefs } from 'pinia'

type ValueObject = CloudGroup | { id: string, displayname: string }

const props = withDefaults(
  defineProps<{
    label: string,
    hint?: string,
    value?: string
    disabled?: boolean,
    // clearable allows deselection of the last item
    clearable?: boolean
    // required blocks the final submit if no value is selected
    required?: boolean
    loading?: boolean,
    loadingIndicator?: boolean,
  }>(), {
    hint: '',
    value: '',
    disabled: false,
    clearable: true,
    required: false,
    loading: false,
    loadingIndicator: true,
  })

const store = useCloudUsersGroupsStore()
const { groups } = storeToRefs(store)

const inputValObject = ref<undefined|ValueObject>(undefined)
const ajaxLoading = ref(false)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const groupsArray = computed(() => Object.values(groups.value))

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

const emit = defineEmits([
  'error',
])

const reduceGroup = (group: ValueObject) => group.id
const getGroupObject = async (id: string) => { return (await getGroup(id)) || { id, displayname: id } }
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const errorHandler = <T extends Error>(error: T | any) => emit('error', error)
const getGroup = (groupId: string) => store.getGroup(groupId, errorHandler)
const findGroups = (query: string) => store.findGroups(query, errorHandler)

const select = ref<null|typeof SelectWithSubmitButton>(null)
const ncSelect = computed(() => select.value?.ncSelect as (typeof NcSelect|null))
</script>
