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

export type ObjectEntries<T, const K extends keyof T = keyof T> =
     (K extends unknown ? [K, T[K]] : never)[]

export type PickByValue<T, V> = Pick<T, { [K in keyof T]: T[K] extends V ? K : never }[keyof T]>

// export type ObjectEntries<T> = {
//     [K in keyof T]: [keyof PickByValue<T, T[K]>, T[K]]
// }[keyof T][];

export type NumberTuple = number[]|[];
export type Zero = [];
export type NonNegInt<N extends number, T extends NumberTuple = Zero> = T['length'] extends N ? T : NonNegInt<N, [...T, T['length']]>;
export type Increment<N extends NumberTuple> = [...N, N['length']];
export type Decrement<N extends NumberTuple> = N extends [...infer Decrement, number] ? NonNegInt<Decrement['length'], Decrement> : never;
export type DecToZero<N extends NumberTuple> = Decrement<N> extends never ? NonNegInt<0> : Decrement<N>;

export type ArrayElement<A> = A extends readonly (infer T)[] ? T : never;
type DeepWriteable<T, Limit extends NonNegInt<999> > = {
  -readonly [P in keyof T]: Limit extends NonNegInt<0> ? T[P] : DeepWriteable<T[P], Decrement<Limit> >;
};
type ShallowWriteable<T> = { -readonly [P in keyof T]: T[P] };
export type Cast<X, Y> = X extends Y ? X : Y
type FromEntries<T> = T extends [infer Key, any][]
  ? { [K in Cast<Key, string>]: Extract<ArrayElement<T>, [K, any]>[1]}
  : { [key in string]: any }

export type FromEntriesWithReadOnly<T> = FromEntries<DeepWriteable<T, NonNegInt<1> > >

declare global {
   interface ObjectConstructor {
     fromEntries<T>(obj: T): FromEntriesWithReadOnly<T>
  }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const isOne = (x: any): x is 1 => x === 1;

/**
 * Helper to check if a type is undefined
 */
export type IsUndefined<T> = [T] extends [undefined] ? true : false

export type NullableIf<B, T> = true extends B ? null|T : T;
