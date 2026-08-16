import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';

declare const m: any;

let testing = false;
let testResult: string | null = null;

app.initializers.add('fruiter-ai-moderation', () => {
  app.extensionData
    .for('fruiter-ai-moderation')
    .registerSetting({
      setting: 'ai-moderation.enabled',
      type: 'boolean',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.enabled_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.enabled_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.api_base_url',
      type: 'text',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.api_base_url_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.api_base_url_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.api_key',
      type: 'text',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.api_key_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.api_key_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.model',
      type: 'text',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.model_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.model_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.custom_instructions',
      type: 'textarea',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.custom_instructions_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.custom_instructions_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.action',
      type: 'select',
      options: {
        hide_and_flag: app.translator.trans('fruiter-ai-moderation.admin.settings.action_hide_and_flag'),
        hide: app.translator.trans('fruiter-ai-moderation.admin.settings.action_hide'),
        flag: app.translator.trans('fruiter-ai-moderation.admin.settings.action_flag'),
        reject: app.translator.trans('fruiter-ai-moderation.admin.settings.action_reject'),
      },
      default: 'hide_and_flag',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.action_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.action_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.check_title',
      type: 'boolean',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.check_title_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.check_title_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.max_chars',
      type: 'number',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.max_chars_label'),
    })
    .registerSetting({
      setting: 'ai-moderation.timeout_seconds',
      type: 'number',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.timeout_label'),
    })
    .registerSetting({
      setting: 'ai-moderation.allow_on_error',
      type: 'boolean',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.allow_on_error_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.allow_on_error_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.json_mode',
      type: 'boolean',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.json_mode_label'),
      help: app.translator.trans('fruiter-ai-moderation.admin.settings.json_mode_help'),
    })
    .registerSetting({
      setting: 'ai-moderation.reject_message',
      type: 'text',
      label: app.translator.trans('fruiter-ai-moderation.admin.settings.reject_message_label'),
    })
    .registerSetting(TestButton);
});

function TestButton() {
  return (
    <div className="Form-group">
      <Button className="Button Button--primary" loading={testing} disabled={testing} onclick={runTest}>
        {app.translator.trans('fruiter-ai-moderation.admin.settings.test_button')}
      </Button>
      {testResult && <div className="helpText">{testResult}</div>}
    </div>
  );
}

function runTest() {
  if (testing) return;

  testing = true;
  testResult = null;
  m.redraw();

  app
    .request({
      url: app.forum.attribute('apiUrl') + '/ai-moderation/test',
      method: 'GET',
      errorHandler: () => {},
    })
    .then((res: any) => {
      testing = false;
      testResult = res && res.ok ? 'OK - ' + JSON.stringify(res.data) : 'FAIL - ' + ((res && res.message) || 'unknown error');
      m.redraw();
    })
    .catch((err: any) => {
      testing = false;
      testResult = 'ERROR - ' + String(err);
      m.redraw();
    });
}
