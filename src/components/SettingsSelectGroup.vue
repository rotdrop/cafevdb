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
<script lang="ts">
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import groupInfoPopup from '../mixins/user-info-popup.ts'
import consoleMixin from '../mixins/console.ts'
import l10nMixin from '../mixins/l10n.ts'
import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.ts'
import type { CloudGroup } from '../stores/cloud-users-groups.ts'

type ValueObject = CloudGroup | { id: string, displayname: string }

export default {
  name: 'SettingsSelectGroup',
  components: {
    SelectWithSubmitButton,
    NcEllipsisedOption,
  },
  mixins: [
    groupInfoPopup,
    consoleMixin,
    l10nMixin,
  ],
  inheritAttrs: false,
  props: {
    label: {
      type: String,
      required: true,
    },
    hint: {
      type: String,
      default: '',
    },
    value: {
      type: String,
      default: '',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    // clearable allows deselection of the last item
    clearable: {
      type: Boolean,
      default: true,
    },
    // required blocks the final submit if no value is selected
    required: {
      type: Boolean,
      default: false,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    loadingIndicator: {
      type: Boolean,
      default: true,
    },
  },
  setup() {
    const store = useCloudUsersGroupsStore()
    return { store, groups: store.groups }
  },
  data() {
    return {
      inputValObject: null as null|ValueObject,
      ajaxLoading: false,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ncSelect: null as any,
    }
  },
  computed: {
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
    groupsArray() {
      return Object.values(this.groups)
    },
    groupId() {
      return this.inputValObject?.id || ''
    },
  },
  watch: {
    /**
     * This watcher catches changed property values and promotes the
     * changed value to the wrapped select.
     *
     * @param newValue New GID set from outside
     */
    async value(newValue) {
      if (this.ajaxLoading) {
        return
      }
      if (!newValue) {
        this.inputValObject = null
        return
      }
      this.ajaxLoading = true
      this.inputValObject = await this.getGroupObject(newValue)
      this.ajaxLoading = false
    },
  },
  mounted() {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    this.ncSelect = (this.$refs!.select! as any).ncSelect
  },
  methods: {
    reduceGroup: (group: ValueObject) => group.id,
    async getGroupObject(id: string) {
      return (await this.getGroup(id)) || { id, displayname: id }
    },
    getGroup(groupId: string) {
      return this.store.getGroup(groupId, this.errorHandler)
    },
    findGroups(query: string) {
      return this.store.findGroups(query, this.errorHandler)
    },
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    errorHandler<T extends Error>(error: T | any) {
      this.$emit('error', error)
    },
  },
}
</script>
