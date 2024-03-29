<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
</script>
<template>
  <div v-tooltip.bottom="tooltip"
       class="name-parts"
  >
    <Highlight class="name-parts__first"
               :text="part1"
               :search="search"
               :highlight="highlight1"
    />
    <Highlight v-if="part2"
               class="name-parts__last"
               :text="part2"
               :search="search"
               :highlight="highlight2"
    />
  </div>
</template>
<script>
import Highlight from '@nextcloud/vue/dist/Components/NcHighlight'
import FindRanges from '../utils/FindRanges.js'
import addressPopup from '../mixins/address-popup.js'

export default {
  name: 'EllipsisedContactOption',

  components: {
    Highlight,
  },

  mixins: [
    addressPopup,
  ],

  props: {
    option: {
      type: [String, Object],
      required: true,
      default: '',
    },
    label: {
      type: String,
      default: '',
    },
    search: {
      type: String,
      default: '',
    },
    name: {
      type: String,
      default: '',
    },
  },

  computed: {
    needsTruncate() {
      return this.name && this.name.length >= 10
    },
    /**
     * Index at which to split the name if it is longer than 10 characters.
     *
     * @returns {bigint} The position at which to split
     */
    split() {
      // leave maximum 10 letters
      return this.name.length - Math.min(Math.floor(this.name.length / 2), 10)
    },
    part1() {
      if (this.needsTruncate) {
        return this.name.substr(0, this.split)
      }
      return this.name
    },
    part2() {
      if (this.needsTruncate) {
        return this.name.substr(this.split)
      }
      return ''
    },
    /**
     * The ranges to highlight. Since we split the string for ellipsising,
     * the Highlight component cannot figure this out itself and needs the ranges provided.
     *
     * @returns {Array} The array with the ranges to highlight
     */
    highlight1() {
      if (!this.search) {
        return []
      }
      return FindRanges(this.name, this.search)
    },
    /**
     * We shift the ranges for the second part by the position of the split.
     * Ranges out of the string length are discarded by the Highlight component,
     * so we don't need to take care of this here.
     *
     * @returns {Array} The array with the ranges to highlight
     */
    highlight2() {
      return this.highlight1.map(range => {
        return {
          start: range.start - this.split,
          end: range.end - this.split,
        }
      })
    },
    tooltip() {
      return this.contactAddressPopup(this.option)
    },
  },
}
</script>
<style lang="scss" scoped>
.name-parts {
  display: flex;
  max-width: 100%;
  &__first {
    overflow: hidden;
    text-overflow: ellipsis;
  }
  &__first,
  &__last {
    // prevent whitespace from being trimmed
    white-space: pre;
    strong {
      font-weight: bold;
    }
  }
}
</style>
