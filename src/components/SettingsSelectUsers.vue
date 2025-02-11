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
 - This file wraps an NcSelect with userSelect option but as input
 - only a flat array of user-ids is provided, and the output is then
 - also just a flat array of selected user ids, while the fancy
 - wrapped user-select uses full-fledged user instances.
 -
 - Finally: the core distribution of NcSelect fails to set the "user:
 - USER_ID" property which is needed to actually fetch the
 - avatar. This should go to a pull-request ...
 -->
<template>
  <SelectWithSubmitButton ref="select"
                          v-bind="$attrs"
                          v-model="inputValObjects"
                          :reduce="reduceUser"
                          label="displayname"
                          :options="usersArray"
                          :options-limit="100"
                          :placeholder="label"
                          :input-label="label"
                          :loading="isLoading"
                          :multiple="true"
                          :close-on-select="false"
                          :disabled="disabled"
                          :user-select="true"
                          v-on="$listeners"
                          @search="findUsers"
  >
    <!--
         Unfortunately, the stock NcSelect seems to be somewhat borken
         and does not set the "user" property which is needed by the
         NcAvatar component. Just doing v-bind="option" leads to a couple of
    -->
    <template #option="option">
      <NcListItemIcon v-tooltip="userInfoPopup(option)"
                      v-bind="toListItemProps(option)"
                      :avatar-size="24"
                      :name="ncSelect ? option[ncSelect.localLabel] : t(appName, 'undefined')"
                      :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcListItemIcon v-tooltip="userInfoPopup(option)"
                      v-bind="toListItemProps(option)"
                      :avatar-size="24"
                      :name="ncSelect ? option[ncSelect.localLabel] : t(appName, 'undefined')"
                      :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>

<script setup lang="ts">
import { appName } from '../config.ts'
import {
  computed,
  ref,
  watch,
} from 'vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import { NcListItemIcon } from '@nextcloud/vue'
import type { NcSelect } from '@nextcloud/vue'
import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.ts'
import { userInfoPopup } from '../util/user-info-popup.ts'
import type { CloudUser } from '../stores/cloud-users-groups.ts'
import { storeToRefs } from 'pinia'

type ValueObject = CloudUser | { id: string, displayname: string, email?: string }

const props = withDefaults(
  defineProps<{
    label: string,
    value?: string[],
    disabled?: boolean,
    loading?: boolean,
    loadingIndicator?: boolean,
  }>(), {
    value: () => [],
    disabled: false,
    loading: false,
    loadingIndicator: true,
  },
)

const store = useCloudUsersGroupsStore()
const { users } = storeToRefs(store)

const inputValObjects = ref<string[]>([])
const ajaxLoading = ref(false)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const usersArray = computed(() => Object.values(users.value))

watch(() => props.value, async (newValue) => {
  if (ajaxLoading.value) {
    return
  }
  if (newValue.length === 0) {
    inputValObjects.value = []
    return
  }
  ajaxLoading.value = true
  for (const userId of newValue) {
    if (!users.value[userId]) {
      await findUsers(userId)
    }
  }
  inputValObjects.value = await getValueObjects()
  ajaxLoading.value = false
})

const reduceUser = (user: ValueObject) => user.id
const getUserObject = async (userId: string): Promise<ValueObject> => {
  return (await getUser(userId)) || { id: userId, displayname: userId }
}

/**
 * Take the current value, fetch the users and again return the
 * same value (array of uids) in most cases. The idea is to fetch
 * the meta-info for each selected user in order to have a nice
 * display in the UI, including meta-info.
 */
const getValueObjects = async () => {
  const validValues: string[] = props.value.filter((userId) => userId !== '' && typeof userId !== 'undefined')
  const result: ValueObject[] = []
  for (const userId of validValues) {
    result.push(await getUserObject(userId))
  }
  return result.map((user) => user.id)
}

const toListItemProps = (user: ValueObject) => {
  return {
    displayName: user.displayname,
    id: user.id,
    user: user.id,
    subname: user.email || undefined,
  }
}

const emit = defineEmits([
  'error',
])

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const errorHandler = <T extends Error>(error: T | any) => emit('error', error)
const getUser = (userId: string) => store.getUser(userId, errorHandler)
const findUsers = (query: string) => store.findUsers(query, errorHandler)

const select = ref<null|typeof SelectWithSubmitButton>(null)
const ncSelect = computed(() => select.value?.ncSelect as (typeof NcSelect|null))
</script>
