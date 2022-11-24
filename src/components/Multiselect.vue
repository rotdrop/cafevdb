<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div class="component-wrapper">
    <NextcloudMultiselect v-bind="$attrs"
                          v-on="$listeners"
    />
  </div>
</template>
<script>
import NextcloudMultiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {
  name: 'MultiSelectWrapper',
  components: {
    NextcloudMultiselect,
  },
}
</script>
<style lang="scss" scoped>
.component-wrapper {
  div.multiselect::v-deep {
    &:not(.multiselect--active) {
      height:35.2px;
    }
    flex-grow:1;
    &:hover .multiselect__tags {
      border-color: var(--color-primary-element);
      outline: none;
    }
    &:hover + .clear-button {
      border-color: var(--color-primary-element) !important;
      border-left-color: transparent !important;
      z-index: 2;
    }
    &.multiselect--active + .clear-button {
      display:none;
    }
    + .clear-button {
      &:disabled {
        background-color: var(--color-background-dark) !important;
      }
      margin-left: -8px !important;
      border-left-color: transparent !important;
      border-radius: 0 var(--border-radius) var(--border-radius) 0 !important;
      background-clip: padding-box;
      background-color: var(--color-main-background) !important;
      opacity: 1;
      padding: 7px 6px;
      height:35.2px;
      width:35.2px;
      margin-right:0;
      z-index:2;
      &:hover, &:focus {
        border-color: var(--color-primary-element) !important;
        border-radius: var(--border-radius) !important;
      }
    }

    &.multiselect--single {
      .multiselect__content-wrapper li > span {
        &::before {
          background-image: var(--icon-checkmark-000);
          display:block;
        }
        &:not(.multiselect__option--selected):hover::before {
          visibility:hidden;
        }
      }
    }
    &.multiselect--multiple {
      .multiselect__content-wrapper li > span {
        &:not(.multiselect__option--selected):hover::before {
          visibility:hidden;
        }
      }
    }

    .multiselect__tag {
      position: relative;
      padding-right: 18px;
      .multiselect__tag-icon {
        cursor: pointer;
        margin-left: 7px;
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        font-weight: 700;
        font-style: initial;
        width: 22px;
        text-align: center;
        line-height: 22px;
        transition: all 0.2s ease;
        border-radius: 5px;
      }

      .multiselect__tag-icon:after {
        content: "×";
        color: #266d4d;
        font-size: 14px;
      }
    }
  }
}
</style>
