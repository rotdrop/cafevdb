/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// import type { RawEditorOptions as MCEEditorOptions, Editor as MCEEditor } from 'tinymce';
// import { type RawEditorOptions, type Editor } from 'tinymce';

type TooltipOptions = Tooltip.Options & {
  cssclass: string[];
  timestamp?: number;
};

type TooltipMethods =
  'show'
  | 'hide'
  | 'toggle'
  | 'enable'
  | 'disable'
  | 'toggleEnabled'
  | 'update'
  | 'setContent'
  | 'dispose';

interface LockUnlockOptions {
  position: 'bottom'|'middle'|'top';
  locked: boolean;
  hardLocked: boolean;
  cssClass?: string;
}
type LockUnlockCommands = 'disable'|'enable'|'lock'|'hardlock'|'destroy'|'checkbox'|'label'|'options';

type HTMLFormElement = HTMLButtonElement
  | HTMLFieldSetElement
  | HTMLInputElement
  | HTMLObjectElement
  | HTMLOutputElement
  | HTMLSelectElement
  | HTMLTextAreaElement;

interface JQuery<TElement = HTMLElement> {
  cafevDialog: JQuery<TElement>['dialog'];
  cafevTooltip: {
    (config?: |Partial<TooltipOptions>|TooltipMethods): JQuery<TElement>;
    enable: () => void;
    disable: () => void;
    remove: () => void;
    hide: () => void;
  };
  tooltip: Tooltip.jQueryInterface;
  hasVerticalScrollbar: () => boolean;
  elements: () => [number, TElement][];
  fileupload(options: Record<string, unknown>|'option', option?: string, value?: unknown): JQuery<TElement>;
  iFrameResize(options: iframeResizer.IFrameOptions): JQuery<TElement>;
  datetimepicker(argument: 'destroy'|Record<string, unknown>, ...rest: unknown): JQuery<TElement>;
  readonly(state?: boolean|string): JQuery<TElement>;
  lockUnlock(argument?: LockUnlockCommands|Partial<LockUnlockOptions>, value?: null|undefined|boolean|string): JQuery<TElement>;
  octemplate<T extends Record<string, unknown>>(vars: T, options?: { escapeFunction?: (x: string) => string }): JQuery<HTMLElement>;
  avatar(user: string, size: number): void;
  bootstrapDualListbox(arg: Record<string, unknown>|'refresh', value?: boolean): void;
  tinymce(): undefined|Editor;
  tinymce(options: RawEditorOptions): Promise<Editor[]>;
}

interface JQueryStatic {
  datetimepicker: {
    setLocale(locale: string): void;
  };
}

declare namespace Chosen {
  interface Options {
    title_attributes?: string[];
  }
}

declare namespace JQueryUI {
  interface ButtonOptions {
    icons?: never;
    text?: string; // just the "text" attribute of the button element
    html?: string; // just the "html" attribute of the button element
    icon?: string;
    iconPosition?: 'beginning'|'end'|'top'|'bottom';
    showLabel?: boolean; // ... but will just do nothin
    showText?: boolean; // ... from the docu, but is also an no-op
    label?: string;
    click?: (event?: JQuery.TriggeredEvent) => void;
    // aditional HTML attributes
    [key: string]: string|unknown;
  }
  interface WidgetCommonProperties {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    _super(...args: unknown[]): any;
  }
  interface Progressbar {
    _percentage(): number;
    valueDiv: JQuery<HTMLDivElement>;
  }
}

// declare namespace Selectize {
//   interface SelectizeControl<T = string, U = { input: string }> {
//     items: T[];
//     options: Record<string|number, U>;
//     $wrapper: JQuery;
//     $input: JQuery;
//     optgroups: Record<string, unknown>;
//     registerOptionGroup(arg: { $order: number; label: string; value: string; disable: boolean }): void;
//   }
//   interface IOptions<T = string, U = { input: string }> {
//     inputClass?: string;
//     //
//     onBeforeDropdownOpen?: (this: SelectizeControl<T, U>, $dropdown: JQuery) => void;
//     onDropdownOpen?: (this: SelectizeControl<T, U>, $dropdown: JQuery) => void;
//     onDropdownClose?: (this: SelectizeControl<T, U>, $dropdown: JQuery) => void;
//     onChange?: (this: SelectizeControl<T, U>, value: T) => void;
//     onClear?: (this: SelectizeControl<T, U>) => void;
//     onOptionsRefresh?: (this: SelectizeControl<T, U>, $dropdown: JQuery) => void;
//     onOptionAdd?: (this: SelectizeControl<T, U>, value: T, data: U) => void;
//     onInitialize?: (this: SelectizeControl<T, U>) => void;
//     create?: boolean|((this: SelectizeControl<T, U>, input: T, setterCallback: (arg: false|U) => void) => void);
//   }
// }
