/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { defineStore } from 'pinia';
import axios from '@nextcloud/axios';
import { generateOcsUrl } from '@nextcloud/router';
import { confirmPassword } from '@nextcloud/password-confirmation';
import { set as vueSet /* , del as vueDelete */ } from 'vue';
// import '@nextcloud/password-confirmation/style.css' // Required for dialog styles

const storeId = 'cloud-user-groups';

export const useCloudUsersGroupsStore = defineStore(storeId, {
  state: () => {
    return {
      groups: {},
      users: {},
      loadingPromise: Promise.resolve(true),
    };
  },
  actions: {
    info(...args) {
      console.info(storeId, ...args);
    },
    error(...args) {
      console.info(storeId, ...args);
    },
    trace(...args) {
      console.trace(storeId, ...args);
    },
    handleError(error, errorHandler) {
      this.error('findUsers', error);
      if (typeof errorHandler === 'function') {
        errorHandler(error);
      }
    },
    async getUser(uid, errorHandler) {

      let promise;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (!this.users[uid]) {
        await (this.loadingPromise = this.findUsers(uid, errorHandler));
      }
      return this.users[uid];
    },
    async getGroup(gid, errorHandler) {

      let promise;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (!this.groups[gid]) {
        await (this.loadingPromise = this.findGroups(gid, errorHandler));
      }
      return this.groups[gid];
    },
    async createGroup(gid, displayName, errorHandler) {
      const group = await this.getGroup(gid);
      if (group) {
        return group;
      }
      try {
        await (this.loadingPromise = axios.post(generateOcsUrl('cloud/groups', 2), { groupid: gid, displayname: displayName }));
        return this.getGroup(gid);
      } catch (error) {
        if (error?.response?.data?.ocs?.meta?.statuscode === 403) {
          try {
            await confirmPassword();
          } catch (error) {
            this.handleError(error, errorHandler);
            return;
          }
          return this.createGroup(gid, displayName, errorHandler);
        }
        this.handleError(error, errorHandler);
      }
    },
    async getGroupUsers(gid, errorHandler) {

      let promise;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (this.groups?.[gid]?.users) {
        return this.groups[gid].users;
      }
      try {
        const response = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/groups/${gid}/users`, 2 /* API version */)));
        const uids = response?.data?.ocs?.data?.users;
        if (Array.isArray(uids) && this.groups[gid]) {
          this.groups[gid].users = uids;
        }
        return uids;
      } catch (error) {
        this.handleError(error, errorHandler);
      }
    },
    async getGroupUsersDetails(gid, errorHandler) {

      let promise;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (this.groups[gid]) {
        if (this.groups[gid].usersDetails) {
          return this.groups[gid].usersDetails;
        } else if (this.groups[gid].users) {
          const usersDetails = {};
          for (const uid of this.groups[gid].users) {
            if (this.users[uid]) {
              usersDetails[uid] = this.users[uid];
            } else {
              break;
            }
          }
          if (Object.values(usersDetails).length === this.groups[gid].users) {
            this.groups[gid].usersDetails = usersDetails;
            return usersDetails;
          }
        }
      }
      try {
        const response = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/groups/${gid}/users/details`, 2 /* API version */)));
        const usersDetails = response?.data?.ocs?.data?.users;
        if (usersDetails) {
          for (const [uid, user] of Object.entries(usersDetails)) {
            this.users[uid] = user;
          }
          if (this.groups[gid]) {
            this.groups[gid].usersDetails = usersDetails;
          }
        }
        return usersDetails;
      } catch (error) {
        this.handleError(error, errorHandler);
      }
    },
    async findGroups(query, errorHandler) {
      query = typeof query === 'string' ? encodeURI(query) : '';
      try {
        const limit = 10;
        let count = 0;
        let offset = 0;
        while (count < limit) {
          const response = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/groups/details?search=${query}&limit=${limit}&offset=${offset}`, 2 /* API version */)));

          for (const group of response.data.ocs.data.groups) {
            if (!group.id) {
              // if we were not a group admin, an empty entry is returned in order to enable paging
              continue;
            }
            ++count;
            const gid = group.id;
            const oldGroup = this.groups[gid];
            if (!oldGroup) {
              group.getUsers = (errorHandler) => this.getGroupUsers(group.id, errorHandler);
              group.getUsersDetails = (errorHandler) => this.getGroupUsersDetails(group.id, errorHandler);
              // this.groups[gid] = group;
              vueSet(this.groups, gid, group);
            } else if (JSON.stringify(this.groups[gid]) !== JSON.stringify(group)) {
              // replace in order to keep the references from groups to user-details
              for (const [key, value] of Object.entries(group)) {
                if (oldGroup?.[key] !== value) {
                  oldGroup[key] = value;
                }
                for (const key of Object.keys(oldGroup)) {
                  if (group?.[key] === undefined) {
                    delete group[key];
                  }
                }
              }
            }
          }
          if (Object.keys(response.data.ocs.data.groups).length < limit) {
            break;
          }
          offset += limit;
        }
      } catch (error) {
        this.handleError(error, errorHandler);
      }
    },
    async findUsers(query, errorHandler) {
      query = typeof query === 'string' ? encodeURI(query) : '';
      try {
        const limit = 10;
        let count = 0;
        let offset = 0;
        while (count < limit) {
          const response = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/users/details?search=${query}&limit=${limit}&offset=${offset}`, 2 /* API version */)));

          for (const [uid, user] of Object.entries(response.data.ocs.data.users)) {
            ++count;
            const oldUser = this.users[uid];
            if (!oldUser) {
              vueSet(this.users, uid, user);
              // this.users[uid] = user;
            } else if (JSON.stringify(oldUser) !== JSON.stringify(user)) {
              // replace in order to keep the references from groups to user-details
              for (const [key, value] of Object.entries(user)) {
                if (oldUser?.[key] !== value) {
                  oldUser[key] = value;
                }
                for (const key of Object.keys(oldUser)) {
                  if (user?.[key] === undefined) {
                    delete user[key];
                  }
                }
              }
            }
          }
          if (Object.keys(response.data.ocs.data.users).length < limit) {
            break;
          }
          offset += limit;
        }
      } catch (error) {
        this.handleError(error, errorHandler);
      }
    },
  },
});
