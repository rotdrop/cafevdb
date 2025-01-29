/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import type { AxiosResponse } from 'axios'
import type { OCSResponse } from '@nextcloud/typings/ocs';

const storeId = 'cloud-user-groups';

type ErrorHandler = <E extends Error>(error: E|any) => void;

export type CloudUser = {
  id: string,
  enabled: boolean,
  displayname: string,
  backend: string,
  lastLogin: number,
  groups: string[],
  // ... and more but we don't need more ...
}

export type CloudGroup = {
  id: string,
  displayname: string,
  usercount: number,
  disabled: boolean,
  canAdd: boolean,
  canRemove: boolean,
  backends: string[],
  users?: string[],
  usersDetails: Record<string, CloudUser>,
  getUsers: (errorHandler: null|ErrorHandler) => Promise<any>,
  getUsersDetails: (errorHandler: null|ErrorHandler) => Promise<any>,
}

type GroupUsersDetailsResponse = AxiosResponse<OCSResponse<{ users: Record<string, CloudUser> }> >
type GroupDetailsResponse = AxiosResponse<OCSResponse<{ groups: CloudGroup[] }> >

export const useCloudUsersGroupsStore = defineStore(storeId, {
  state: () => {
    return {
      groups: {} as Record<string, CloudGroup>,
      users: {} as Record<string, CloudUser>,
      loadingPromise: Promise.resolve(true) as Promise<any>,
    };
  },
  actions: {
    debug(...args: any[]) {
      console.debug(storeId, ...args);
    },
    info(...args: any[]) {
      console.info(storeId, ...args);
    },
    error(...args: any[]) {
      console.error(storeId, ...args);
    },
    trace(...args: any[]) {
      console.trace(storeId, ...args);
    },
    handleError<E extends Error>(error: E|any, errorHandler: null|ErrorHandler) {
      this.error('findUsers', error);
      if (typeof errorHandler === 'function') {
        errorHandler(error);
      }
    },
    async getUser(uid: string, errorHandler: null|ErrorHandler): Promise<CloudUser|undefined> {

      let promise: Promise<any>;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (!this.users[uid]) {
        await (this.loadingPromise = this.findUsers(uid, errorHandler));
      }
      return this.users[uid] || undefined;
    },
    async getGroup(gid: string, errorHandler: null|ErrorHandler): Promise<CloudGroup|undefined> {

      let promise: Promise<any>;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (!this.groups[gid]) {
        await (this.loadingPromise = this.findGroups(gid, errorHandler));
      }
      return this.groups[gid] || undefined;
    },
    async createGroup(gid: string, displayName: string, errorHandler: null|ErrorHandler): Promise<CloudGroup|null|undefined> {
      const group = await this.getGroup(gid, errorHandler);
      if (group) {
        return group;
      }
      try {
        await (this.loadingPromise = axios.post(generateOcsUrl('cloud/groups'), { groupid: gid, displayname: displayName }));
        return await this.getGroup(gid, errorHandler);
      } catch (error: any) {
        const data: null|OCSResponse = error?.response?.data || null;
        if (data && data.ocs.meta.statuscode === 403) {
          try {
            await confirmPassword();
          } catch (error) {
            this.handleError(error, errorHandler);
            return;
          }
          return await this.createGroup(gid, displayName, errorHandler);
        }
        this.handleError(error, errorHandler);
      }
    },
    async getGroupUsers(gid: string, errorHandler: null|ErrorHandler) {

      let promise: Promise<any>;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (this.groups?.[gid]?.users) {
        return this.groups[gid].users;
      }
      try {
        const response = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/groups/${gid}/users`)));
        const uids: null|string[] = response?.data?.ocs?.data?.users;
        if (Array.isArray(uids) && this.groups[gid]) {
          this.groups[gid].users = uids;
        }
        return uids;
      } catch (error) {
        this.handleError(error, errorHandler);
      }
    },
    async getGroupUsersDetails(gid: string, errorHandler: null|ErrorHandler) {

      let promise: Promise<any>;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (this.groups[gid]) {
        if (this.groups[gid].usersDetails) {
          return this.groups[gid].usersDetails;
        } else if (this.groups[gid].users) {
          const usersDetails = {} as Record<string, CloudUser> ;
          for (const uid of this.groups[gid].users) {
            if (this.users[uid]) {
              usersDetails[uid] = this.users[uid];
            } else {
              break;
            }
          }
          if (Object.values(usersDetails).length === this.groups[gid].users.length) {
            this.groups[gid].usersDetails = usersDetails;
            return usersDetails;
          }
        }
      }
      try {
        const response: GroupUsersDetailsResponse = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/groups/${gid}/users/details`)));
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
    async findGroups(query: null|string, errorHandler: null|ErrorHandler) {
      query = typeof query === 'string' ? encodeURI(query) : '';
      try {
        const limit = 10;
        let count = 0;
        let offset = 0;
        while (count < limit) {
          const response: GroupDetailsResponse = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/groups/details?search=${query}&limit=${limit}&offset=${offset}`)));

          for (const group of response.data.ocs.data.groups) {
            if (!group.id) {
              // if we were not a group admin, an empty entry is returned in order to enable paging
              continue;
            }
            ++count;
            const gid = group.id;
            const oldGroup = this.groups[gid];
            if (!oldGroup) {
              group.getUsers = (errorHandler: null|ErrorHandler) => this.getGroupUsers(group.id, errorHandler);
              group.getUsersDetails = (errorHandler: null|ErrorHandler) => this.getGroupUsersDetails(group.id, errorHandler);
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
    async findUsers(query: null|string, errorHandler: null|ErrorHandler) {
      query = typeof query === 'string' ? encodeURI(query) : '';
      try {
        const limit = 10;
        let count = 0;
        let offset = 0;
        while (count < limit) {
          const response = await (this.loadingPromise = axios.get(generateOcsUrl(`cloud/users/details?search=${query}&limit=${limit}&offset=${offset}`)));

          for (const [uid, user] of (Object.entries(response.data.ocs.data.users) as [string, any][])) {
            ++count;
            const oldUser = this.users[uid];
            if (!oldUser) {
              vueSet(this.users, uid, user);
              // this.users[uid] = user;
            } else if (JSON.stringify(oldUser) !== JSON.stringify(user)) {
              // replace in order to keep the references from groups to user-details
              for (const [key, value] of Object.entries(user as object)) {
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
