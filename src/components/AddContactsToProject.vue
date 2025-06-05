<!--
 * Orchestra member, musicion and project management application.
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
 -->
<template>
  <div class="container flex-container">
    <NcAppSidebar :name="project ? t(appName, '{name} - Add Contacts', { name: project.name }) : t(appName, 'Add Contacts')"
                  :force-menu="false"
                  @close="handleClose"
    >
      <NcAppSidebarTab>
        <!-- div class="flex-container flex-center flex-column" -->
        <SelectContacts v-model="contacts"
                        :tooltip="contacts.length ? undefined : hints['templates:cloud:integration:recipients:contacts']"
                        :label="t(appName, 'Contacts')"
                        :placeholder="t(appName, 'e.g. Bilbo Baggins')"
                        :multiple="true"
                        :clear-action="true"
                        :only-address-books="onlyAddressBooks"
                        :all-address-books="allAddressBooks"
                        :disabled="false"
                        :select-all-option="false"
                        :submit-button="false"
                        search-scope="contacts"
        />
        <SelectAddressBooks v-model="onlyAddressBooks"
                            :tooltip="hints['templates:cloud:integration:address-books']"
                            :label="t(appName, 'Address-Books')"
                            :multiple="true"
                            :reset-button="true"
                            :clear-button="false"
                            :disabled="false"
                            @update:address-books="(books) => allAddressBooks = books"
        />
        <!-- /div -->
      </NcAppSidebarTab>
    </NcAppSidebar>
  </div>
</template>
<script setup lang="ts">
import { appName } from '../config.ts'
import {
  // computed,
  onBeforeMount,
  reactive,
  ref,
} from 'vue'
import {
  useRoute,
  useRouter,
  onBeforeRouteUpdate,
} from 'vue-router/composables'
import type {
  RouteRecord,
} from 'vue-router'
import {
  NcAppSidebar,
  NcAppSidebarTab,
} from '@nextcloud/vue'
import SelectContacts from '../components/SelectContacts.vue'
import SelectAddressBooks from '../components/SelectAddressBooks.vue'
import Console from '../util/console.ts'
import { tooltips } from '../util/tooltips.ts'
import type { Contact, AddressBook } from '../types/address-book.d.ts'
import type { Project } from '../stores/app-data.ts'
import { ADD_CONTACTS_TO_PROJECT_NAME } from '../router/add-contacts-to-project.ts'
import useAppDataStore from '../stores/app-data.ts'

const COMPONENT_NAME = ADD_CONTACTS_TO_PROJECT_NAME
const logger = new Console(COMPONENT_NAME)

const props = withDefaults(defineProps<{
  projectName: string,
}>(), {})

const appData = useAppDataStore()

const router = useRouter()
const currentRoute = useRoute()

let prev: undefined|RouteRecord
for (const match of currentRoute.matched) {
  if (match.name === ADD_CONTACTS_TO_PROJECT_NAME) {
    break
  }
  prev = match
}
const origin = prev
  ? {
    location: {
      name: prev.name,
      param: currentRoute.params,
      query: currentRoute.query,
    },
    transition: currentRoute.transition,
  }
  : {
    location: {
      name: 'home',
    },
    transition: 'unknown',
  }

onBeforeMount(() => {
  logger.debug('CURRENT ROUTE', { currentRoute: { ...currentRoute } })
})
onBeforeRouteUpdate((to, from, next) => {
  logger.debug('ON BEFORE ROUTE UPDATE', {
    to: { ...to },
    from: { ...from },
    origin: { ...(origin || {}) },
  })
  if (origin.location.query && to.query.hash) {
    origin.location.query.hash = to.query.hash
  }
  next()
})

const handleClose = () => {
  if (origin?.transition === 'push') {
    router.go(-1) // maybe we want to avoid this altogether ...
  } else if (origin) {
    router.push(origin.location)
  }
}

const project = ref<null|Project>(null)

const contacts = ref<Contact[]>([])
const allAddressBooks = ref<Record<string, AddressBook> >({})
const onlyAddressBooks = ref<AddressBook[]>([])

// const addressBookUris = computed(() => {
//   const uris = {}
//   for (const book of onlyAddressBooks.value) {
//     uris[book.key] = book.uri
//   }
//   return uris
// })

const hints = reactive({
  'templates:cloud:integration:recipients:contacts': '',
  'templates:cloud:integration:address-books': '',
})

const getData = async () => {
  project.value = await appData.getProject(props.projectName) || null
  logger.info('PROJECT', { project: project.value })
  Object.assign(hints, await tooltips(Object.keys(hints)))
  logger.info('TOOLTIPS', { tooltips })
}

onBeforeMount(async () => {
  await getData()
})

</script>
<style scoped lang="scss">
@use "sass:list";
@use '../../style/mixins/flex.scss';

@include flex.flexRules;
</style>
