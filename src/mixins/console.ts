/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

const mixin = {
  methods: {
    trace(...args: any[]) {
      // @ts-expect-error Type of this unknown
      console.trace(this.$options.name, ...args);
    },
    error(...args: any[]) {
      // @ts-expect-error Type of this unknown
      console.error(this.$options.name, ...args);
    },
    warn(...args: any[]) {
      // @ts-expect-error Type of this unknown
      console.warn(this.$options.name, ...args);
    },
    info(...args: any[]) {
      // @ts-expect-error Type of this unknown
      console.info(this.$options.name, ...args);
    },
    debug(...args: any[]) {
      // @ts-expect-error Type of this unknown
      console.debug(this.$options.name, ...args);
    },
  },
};

export default mixin;
