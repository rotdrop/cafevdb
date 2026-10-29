import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { h } from 'vue';
import OrchestraSettings from '../../../../src/components/personal-settings/OrchestraSettings.vue';

const mocks = vi.hoisted(() => ({
  settings: {} as Record<string, unknown>,
  setValue: vi.fn(),
  setButtonFactory: vi.fn(),
  pickNodes: vi.fn(),
  copyFile: vi.fn(),
  moveFile: vi.fn(),
  stat: vi.fn(),
  setAdminSetting: vi.fn(),
  showSettingFeedback: vi.fn(),
  handleSettingError: vi.fn(),
  emitFileEvent: vi.fn(),
  operation: 0,
  source: {
    basename: 'letter.odt',
    path: '/Documents/letter.odt',
    root: '/files/test',
    type: 'file',
  },
  destination: {
    basename: 'letter.odt',
    source: 'https://cloud.example/remote.php/dav/files/test/Shared/Templates/letter.odt',
  },
}));

vi.mock('@nextcloud/dialogs', () => ({
  FilePickerClosed: class FilePickerClosed extends Error {},
  getFilePickerBuilder: () => {
    const builder = {
      setMultiSelect: () => builder,
      allowDirectories: () => builder,
      setButtonFactory: (factory: unknown) => {
        mocks.setButtonFactory(factory);
        return builder;
      },
      build: () => ({ pickNodes: mocks.pickNodes }),
    };
    return builder;
  },
}));
vi.mock('@nextcloud/event-bus', () => ({ emit: mocks.emitFileEvent }));
vi.mock('@nextcloud/files', () => ({ FileType: { File: 'file' } }));
vi.mock('@nextcloud/files/dav', () => ({
  defaultRootPath: '/files/test',
  getClient: () => mocks,
  getDefaultPropfind: () => '<propfind/>',
  resultToNode: () => mocks.destination,
}));
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }));
vi.mock('@nextcloud/vue', () => ({
  NcButton: { template: '<button><slot name="icon" /></button>' },
  NcSettingsSection: { template: '<section><slot /></section>' },
  NcTextField: { template: '<input />' },
  NcCheckboxRadioSwitch: {},
  NcPasswordField: {},
  NcSelect: {},
  NcTextArea: {},
}));
vi.mock('../../../../src/components/personal-settings/WysiwygField.vue', () => ({ default: {} }));
vi.mock('../../../../src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('../../../../src/app/settings-urls.ts', () => ({ getAppUrl: vi.fn() }));
vi.mock('../../../../src/services/admin-settings.ts', () => ({ setAdminSetting: mocks.setAdminSetting }));
vi.mock('../../../../src/services/setting-feedback.ts', () => ({
  showSettingFeedback: mocks.showSettingFeedback,
  handleSettingError: mocks.handleSettingError,
}));
vi.mock('../../../../src/stores/tooltips.ts', () => ({
  default: () => ({ provideTooltips: vi.fn(), tooltipsData: {} }),
}));
vi.mock('../../../../src/toolkit/util/axios-file-download.ts', () => ({ default: vi.fn() }));
vi.mock('../../../../src/toolkit/util/initial-state.ts', () => ({ default: () => mocks.settings }));
vi.mock('../../../../src/components/personal-settings/AdminSettingsTab.vue', async () => {
  const { defineComponent, h } = await import('vue');
  return {
    default: defineComponent({
      setup: (_props, { slots }) => () => h('div', ['standardLetter', 'logo', 'invoice'].map((key) =>
        h('div', { 'data-template': key }, slots[`field-${key}`]?.({
          value: mocks.settings[key],
          settings: mocks.settings,
          setValue: mocks.setValue,
        })))),
    }),
  };
});

const render = () => mount(OrchestraSettings, {
  props: { entry: { id: 'orchestra', key: 'orchestra', name: 'Orchestra', adminOnly: true, children: [] } },
  global: { directives: { tooltip: {} } },
});
const cloudButton = (wrapper: ReturnType<typeof render>, template = 'standardLetter') =>
  wrapper.get(`[data-template="${template}"] button[aria-label="Choose a template file from the cloud storage."]`);

describe('Orchestra document-template cloud picker', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mocks.settings = { sharedFolder: 'Shared', documentTemplatesFolder: 'Templates' };
    mocks.operation = 0;
    mocks.source.path = '/Documents/letter.odt';
    mocks.stat.mockResolvedValue({});
    mocks.setAdminSetting.mockResolvedValue({ data: { messages: ['Saved'] } });
    mocks.pickNodes.mockImplementation(async () => {
      const buttons = mocks.setButtonFactory.mock.calls[0][0]([mocks.source]);
      buttons[mocks.operation].callback();
      return [mocks.source];
    });
  });

  it('copies a cloud file, saves its name, and updates the download link', async () => {
    const wrapper = render();
    await cloudButton(wrapper).trigger('click');
    await flushPromises();
    expect(mocks.copyFile).toHaveBeenCalledWith('/files/test/Documents/letter.odt', '/files/test/Shared/Templates/letter.odt');
    expect(mocks.setAdminSetting).toHaveBeenCalledWith('standardLetter', 'letter.odt');
    expect(mocks.setValue).toHaveBeenCalledWith('letter.odt', mocks.destination.source);
    expect(mocks.showSettingFeedback).toHaveBeenCalled();
    expect(mocks.emitFileEvent).toHaveBeenCalledWith('files:node:created', mocks.destination);
    expect(cloudButton(wrapper).attributes('disabled')).toBeUndefined();
  });

  it('offers cloud selection for constant documents such as the logo', async () => {
    const wrapper = render();
    await cloudButton(wrapper, 'logo').trigger('click');
    await flushPromises();
    expect(mocks.setAdminSetting).toHaveBeenCalledWith('logo', 'letter.odt');
    expect(wrapper.find('[data-template="logo"] button[aria-label="Auto-fill test"]').exists()).toBe(false);
  });

  it('moves a file when Move is chosen', async () => {
    mocks.operation = 1;
    const wrapper = render();
    await cloudButton(wrapper).trigger('click');
    await flushPromises();
    expect(mocks.moveFile).toHaveBeenCalled();
    expect(mocks.copyFile).not.toHaveBeenCalled();
    expect(mocks.emitFileEvent).toHaveBeenCalledWith('files:node:updated', mocks.destination);
  });

  it('uses the configured subfolder', async () => {
    mocks.settings.financeFolder = 'Finance';
    const wrapper = render();
    await cloudButton(wrapper, 'invoice').trigger('click');
    await flushPromises();
    expect(mocks.copyFile).toHaveBeenCalledWith('/files/test/Documents/letter.odt', '/files/test/Shared/Templates/Finance/letter.odt');
  });

  it('does not copy an already correctly placed file onto itself', async () => {
    mocks.source.path = '/Shared/Templates/letter.odt';
    const wrapper = render();
    await cloudButton(wrapper).trigger('click');
    await flushPromises();
    expect(mocks.copyFile).not.toHaveBeenCalled();
    expect(mocks.moveFile).not.toHaveBeenCalled();
    expect(mocks.setAdminSetting).toHaveBeenCalled();
  });

  it('disables selection when the destination folders are not configured', () => {
    mocks.settings.sharedFolder = '';
    expect(cloudButton(render()).attributes('disabled')).toBeDefined();
  });

  it('disables picker actions when no file or a folder is selected', async () => {
    const wrapper = render();
    await cloudButton(wrapper).trigger('click');
    await flushPromises();
    const factory = mocks.setButtonFactory.mock.calls[0][0];
    expect(factory([]).every((button: { disabled: boolean }) => button.disabled)).toBe(true);
    expect(factory([{ type: 'folder' }]).every((button: { disabled: boolean }) => button.disabled)).toBe(true);
  });

  it('treats cancellation as normal and releases the busy state', async () => {
    const { FilePickerClosed } = await import('@nextcloud/dialogs');
    mocks.pickNodes.mockRejectedValue(new FilePickerClosed('Closed'));
    const wrapper = render();
    await cloudButton(wrapper).trigger('click');
    await flushPromises();
    expect(mocks.handleSettingError).not.toHaveBeenCalled();
    expect(mocks.setAdminSetting).not.toHaveBeenCalled();
    expect(cloudButton(wrapper).attributes('disabled')).toBeUndefined();
  });

  it.each(['copy', 'save'])('reports %s failures without updating the configured value', async (step) => {
    const error = new Error('Failure');
    (step === 'copy' ? mocks.copyFile : mocks.setAdminSetting).mockRejectedValue(error);
    const wrapper = render();
    await cloudButton(wrapper).trigger('click');
    await flushPromises();
    expect(mocks.handleSettingError).toHaveBeenCalledWith(error, expect.any(Function));
    expect(mocks.setValue).not.toHaveBeenCalled();
    expect(cloudButton(wrapper).attributes('disabled')).toBeUndefined();
  });
});

it('updates the shared field value and link together, including after deletion', async () => {
  const { default: AdminSettingsTab } = await vi.importActual<typeof AdminSettingsTabModule>(
    '../../../../src/components/personal-settings/AdminSettingsTab.vue',
  );
  const wrapper = mount(AdminSettingsTab, {
    props: {
      entry: { id: 'orchestra', key: 'orchestra', name: 'Orchestra', adminOnly: true, children: [] },
      groups: [{
        id: 'templates',
        name: 'Templates',
        fields: [{ key: 'standardLetter', label: 'Letter', type: 'text', downloadLinkKey: 'standardLetterDownloadLink' }],
      }],
    },
    slots: {
      'field-standardLetter': ({ setValue }: { setValue: (value: string, link?: string) => void }) => [
        h('button', { id: 'set', onClick: () => setValue('letter.odt', mocks.destination.source) }),
        h('button', { id: 'clear', onClick: () => setValue('') }),
      ],
    },
  });
  await wrapper.get('#set').trigger('click');
  expect(wrapper.get('a').attributes('href')).toBe(mocks.destination.source);
  expect(wrapper.get('a').attributes('download')).toBe('letter.odt');
  expect(wrapper.find('input').exists()).toBe(false);
  await wrapper.get('#clear').trigger('click');
  expect(wrapper.find('a').exists()).toBe(false);
  expect(wrapper.find('input').exists()).toBe(true);
});
import type * as AdminSettingsTabModule from '../../../../src/components/personal-settings/AdminSettingsTab.vue';
