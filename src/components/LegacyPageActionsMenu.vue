<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div class="container">
    <NcActions v-if="positioned"
               :force-menu="true"
               :manual-open="true"
               @click="moveToAnchor"
    >
      <NcActionSeparator v-show="false" />
    </NcActions>
    <NcActions ref="actions"
               :class="[{ positioned }, cssClass]"
               :force-menu="true"
               force-semantic-type="menu"
               :open.sync="open"
               @opened="handleOpenedEvent"
               @closed="handleClosedEvent"
    >
      <NcActionCaption v-if="showMenuCaption"
                       :class="[cssClass, 'menu-caption']"
                       :name="menuCaption"
      />
      <NcActionSeparator v-if="showMenuCaption" class="menu-caption" />
      <NcActionButton v-if="enableOverviewItem"
                      :name="t(appName, 'Overview')"
                      :class="[cssClass]"
                      :close-after-click="true"
                      @click="openOverview"
      >
        <template #icon>
          <IconOverview />
        </template>
      </NcActionButton>
      <NcActionSeparator v-if="enableOverviewItem" class="overview" />
      <slot name="actions" />
    </NcActions>
  </div>
</template>
<script setup lang="ts">
import {
  NcActions,
  NcActionButton,
  NcActionCaption,
  NcActionSeparator,
} from '@nextcloud/vue'
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import IconOverview from 'vue-material-design-icons/InformationOutline.vue'
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts'
import { PAGE_TEMPLATE_ACTION_MENU } from '../event-bus-events.ts'
import { closeNavigation } from '../services/navigation.ts'
import {
  computed,
  nextTick,
  onMounted,
  ref,
  watch,
} from 'vue'
import * as BusEvents from '../event-bus-events.ts'
import Console from '../util/console.ts'

const COMPONENT_NAME = 'LegacyPageActionsMenu'

const logger = new Console(COMPONENT_NAME)

type NcButtonType = {
  ref?: string,
  $el: HTMLElement,
}
type NcActionsType = {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  closeMenu(returnFocus?: boolean):Promise<any>,
  $refs: {
    popover: { $refs: { popover: { $refs: { reference: HTMLElement, } } } },
    triggerButton: NcButtonType,
  },
}

const props = withDefaults(defineProps<{
  enableOverviewItem?: boolean,
  entityId: number,
  menuCaption?: string,
  projectId?: number,
  projectName?: string,
  template: string,
}>(), {
  enableOverviewItem: true,
  menuCaption: undefined,
  projectId: undefined,
  projectName: undefined,
})

// data
const open = ref(false)
const referenceElement = ref<null|HTMLElement>(null)
const triggerButton = ref<null|NcButtonType>(null)
const positioned = ref(false)
const cssClass = computed(() => appName + '-legacy-page-actions')
const showMenuCaption = computed(() => props.menuCaption && positioned.value)

// watchers
watch(open, (state, oldState) => {
  if (!state && positioned.value) {
    // logger.debug('WATCHER CLOSE MENU')
    // this.closeMenu()
  }
  logger.debug('OPEN CHANGED', { state, oldState })
})

const openOverview = () => {
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.LEGACY_RECORD_POPUP, {
    entityId: props.entityId,
    projectId: props.projectId,
    projectName: props.projectName,
    template: props.template,
  })
}
const setPosition = (x?: number, y?: number) => {
  if (x !== undefined && y !== undefined) {
    referenceElement.value!.style.position = 'fixed'
    referenceElement.value!.style.left = x + 'px'
    referenceElement.value!.style.top = y + 'px'

    positioned.value = true
  } else if (positioned.value) {
        referenceElement.value!.style.position = ''
    referenceElement.value!.style.left = ''
    referenceElement.value!.style.top = ''

    positioned.value = false
  }
}
let ignoreClosedEvent = false
const handleClosedEvent = () => {
  if (ignoreClosedEvent) {
    ignoreClosedEvent = false
  } else {
    closeMenu()
  }
}
const closeMenu = async () => {
  logger.debug('-> closeMenu()')
  if (open.value) {
    open.value = false
    await nextTick()
  }
  if (positioned.value) {
    // the open trigger was a context menu click, so there is not
    // point to return the focus to the menu button.
    triggerButton.value?.$el.blur()
  }
  for (let i = 0; i < 2; ++i) {
    await nextFrame()
    await nextTick()
  }
  setPosition()
  logger.debug('<- closeMenu()')
}
const nextFrame = () => {
  return new Promise(resolve => requestAnimationFrame(() => {
    requestAnimationFrame(resolve)
  }))
}
const openedPromise = Promise.withResolvers()
openedPromise.resolve()
const handleOpenedEvent = () => {
  openedPromise.resolve()
}
const openMenu = async (x?: number, y?: number) => {
  logger.debug('-> openMenu()', x, y, positioned.value)
  if (!open.value) {
    Object.assign(openedPromise, { ...Promise.withResolvers() })
  }
  setPosition(x, y)
  open.value = true
  if (positioned.value) {
    await nextTick()
    triggerButton.value?.$el.blur()
  }
  await openedPromise.promise
  logger.debug('<- openMenu()', x, y, positioned.value)
}
const moveToAnchor = async (event?: MouseEvent) => {
  if (!open.value || !positioned.value) {
    return
  }
  logger.debug('-> moveToAnchor()')
  event?.preventDefault()
  ignoreClosedEvent = true
  await closeMenu()
  await nextTick()
  openMenu()
  logger.debug('<- moveToAnchor()')
}

const isOpen = () => {
  logger.debug('OPEN STATE', open.value)
  return open.value
}

// we need to expose some methods in order to allow legacy code to
// open, close and position the menu.
defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

// onBeforeMount(async () => {
//   await syncProjectData(props.projectId)
// })

const actions = ref<null|NcActionsType>(null)

onMounted(() => {
  const origCloseMenu = actions.value!.closeMenu
  actions.value!.closeMenu = (returnFocus) => origCloseMenu(positioned.value ? false : returnFocus)
  referenceElement.value = actions.value!.$refs.popover.$refs.popover.$refs.reference
  triggerButton.value = actions.value!.$refs.triggerButton
  asyncSubscribe(PAGE_TEMPLATE_ACTION_MENU, (event) => {
    if (event.template !== props.template) {
      return
    }
    const entityId = event.entityId
    const newOpenState = event.open
    if (!newOpenState
      && open.value
      && +entityId !== -props.entityId
      && (+entityId <= 0 || +entityId === +props.entityId)) {
      closeMenu()
    } else if (newOpenState && entityId === props.entityId) {
      openMenu(event?.x || undefined, event?.y || undefined)
    }
  })
})

</script>
<style lang="scss" scoped>
@use '../../style/variables.scss' as *;
.container {
  display: flex;
  :deep(.button-vue__icon) svg {
    width: 28px;
    height: 28px;
  }
  .action-item.action-item--open.positioned {
    &, ::v-deep * {
      width: 0 !important;
      height: 0 !important;
      min-width: 0 !important;
      min-height: 0 !important;
      max-width: 0 !important;
      max-height: 0 !important;
      overflow: hidden;
    }
  }
}
.#{$appName}-legacy-page-actions.menu-caption.app-navigation-caption {
  font-weight: bold;
  color: blue;
  font-style: italic;
  text-align: center;
  display: inline-block;
  margin: auto;
  width: 100%;
  padding: 0;
}
</style>
