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
 -
 - Ok, at some point we need a link between the legacy page templates
 - and the icons for the navigation bar. We just use thiscomponent in
 - order to implement this link.
 -->
<template>
  <component :is="icon" v-bind="$attrs" v-on="$listeners" />
</template>
<script lang="ts">
import DefaultIcon from 'vue-material-design-icons/Table.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import ProjectsIcon from 'vue-material-design-icons/ListBox.vue'
import ProjectOverviewIcon from 'vue-material-design-icons/InformationOutline.vue'
import ProjectParticipantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
import InstrumentsIcon from 'vue-material-design-icons/Trumpet.vue'
import AllMusiciansIcon from 'vue-material-design-icons/Human.vue'
import ProjectParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import BlogIcon from 'vue-material-design-icons/MessageBadge.vue'

export default {
  name: 'PageTemplateIcon',
  components: {},
  props: {
    pageTemplate: {
      type: String,
      required: true,
    },
  },
  computed: {
    icon() {
      switch (this.pageTemplate) {
      case 'home':
        return HomeIcon
      case 'projects':
        return ProjectsIcon
      case 'project-overview':
        return ProjectOverviewIcon
      case 'project-participant-fields':
        return ProjectParticipantFieldsIcon
      case 'project-participants':
        return ProjectParticipantsIcon
      case 'project-instrumentation-numbers':
        return InstrumentationNumbersIcon
      case 'instruments':
        return InstrumentsIcon
      case 'all-musicians':
        return AllMusiciansIcon
      case 'blog/blog':
        return BlogIcon
      default:
        return DefaultIcon
      }
    },
  },
}
</script>
