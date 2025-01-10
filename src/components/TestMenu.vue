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
  <NcActions>
    <NcActionButton>
      One
    </NcActionButton>
    <NcActionButton>
      Two
    </NcActionButton>
  </NcActions>
</template>
<script>
import {
  NcActions,
  NcActionButton,
} from '@nextcloud/vue'
import Vue from 'vue'

export default Vue.extend({
  name: 'TestMenu',
  components: {
    NcActionButton,
    NcActions,
  },
  props: {
    testProp: {
      type: String,
      required: true,
    },
  },
  mounted() {
    console.info('mounted', this.testProp)
  },
})

</script>
