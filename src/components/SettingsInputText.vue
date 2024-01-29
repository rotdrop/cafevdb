<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <form @submit.prevent="">
    <div class="input-wrapper">
      <label :for="id">{{ label }}</label>
      <input :id="id"
             type="text"
             :value="inputVal"
             :disabled="disabled"
             @input="$emit('input', $event.target.value)"
      >
      <input type="submit"
             class="icon-confirm"
             value=""
             @click="$emit('update', inputVal)"
      >
    </div>
    <p v-if="hint !== ''" class="hint">
      {{ hint }}
    </p>
  </form>
</template>
<script>
let uuid = 0
export default {
  name: 'SettingsInputText',
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
  },
  data() {
    return {
      inputVal: this.value,
    }
  },
  computed: {
    id() {
      return 'settings-input-text-' + this.uuid
    },
  },
  watch: {
    value(/* newVal, oldVal */) {
      this.inputVal = this.value
    },
  },
  beforeCreate() {
    this.uuid = uuid.toString()
    uuid += 1
  },
}
</script>

<style scoped>
  .input-wrapper {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    max-width: 400px;
  }

  label {
    width: 100%;
  }

  input[type=text] {
    flex-grow: 1;
  }

  .hint {
    color: var(--color-text-lighter);
    font-size: 80%;
  }
</style>
