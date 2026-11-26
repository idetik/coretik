import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { server, flush, html } from './setup.mjs';

const {
    default: Forms,
    configure,
    settings,
    JsonForm,
    TunnelForm,
    Fetcher,
    FieldFactory,
    AbstractField,
    Container,
    parseJsonResponse,
    ValidationError
} = await import('../../src/Services/Forms/scripts/index.js');

const defaultMessages = { ...settings.messages };

beforeEach(() => {
    server.reset();
    settings.messages = { ...defaultMessages };
    settings.ajaxUrl = null;
    document.body.innerHTML = '';
});

const contactForm = () => html(`
<form id="form-contact-1" data-form-id="contact" data-form data-ajax-endpoint="/wp-admin/admin-ajax.php" novalidate>
    <input type="hidden" name="coretik-form-contact[form_id]" value="contact">
    <div data-form-errors role="alert"></div>
    <div data-form-notices role="status"></div>
    <div data-form-success data-success-message="Merci !" aria-live="polite"></div>
    <div data-field="email"><input type="email" id="email" name="coretik-form-contact[email]"></div>
    <div data-field="kind">
        <select name="coretik-form-contact[kind]"><option value="">-</option><option value="pro">Pro</option></select>
    </div>
    <div data-field="company" data-conditional-field='[{"field":"kind","value":"pro"}]'>
        <input type="text" name="coretik-form-contact[company]">
    </div>
    <button type="submit">Send</button>
</form>`);

const tunnelForm = () => html(`
<form id="form-registration-1" data-form-id="registration" data-form-tunnel data-current-step="1" data-ajax-endpoint="/ajax" novalidate>
    <div data-form-errors></div>
    <div data-form-notices></div>
    <div data-form-success data-success-message="Done"></div>
    <ol data-form-navigation>
        <li data-form-navigation-item data-form-navigation-step="1"><button type="button" data-go-to-step="1">1</button></li>
        <li data-form-navigation-item data-form-navigation-step="2"><button type="button" data-go-to-step="2" disabled>2</button></li>
        <li data-form-navigation-item data-form-navigation-step="3"><button type="button" data-go-to-step="3" disabled>3</button></li>
    </ol>
    <div data-form-steps>
        <fieldset data-form-step data-step="1"><legend data-step-title>Contact</legend>
            <div data-field="email"><input type="email" name="coretik-form-registration[email]"></div>
        </fieldset>
        <fieldset data-form-step data-step="2" hidden><legend data-step-title>Name</legend>
            <div data-field="name"><input type="text" name="coretik-form-registration[name]"></div>
        </fieldset>
        <fieldset data-form-step data-step="3" data-form-step-summary data-form-step-submit-text="Register" hidden>
            <div data-summary-step="1"><button type="button" data-go-to-step="1">Edit</button></div>
            <div data-summary-step="2"><button type="button" data-go-to-step="2">Edit</button></div>
        </fieldset>
    </div>
    <button type="button" data-previous-step hidden disabled>Back</button>
    <button type="button" class="btn--progress" data-next-step>Next</button>
</form>`);

const posted = (request) => Object.fromEntries(request.body.entries());

test('configure() merges the messages and the ajax URL', () => {
    configure({ ajaxUrl: '/custom-ajax', messages: { next: 'Next' } });
    assert.equal(settings.ajaxUrl, '/custom-ajax');
    assert.equal(settings.messages.next, 'Next');
    assert.equal(settings.messages.submit, defaultMessages.submit);
});

test('parseJsonResponse() returns data, rejects HTTP and WordPress errors', async () => {
    assert.deepEqual(await parseJsonResponse({ ok: true, json: async () => ({ success: true, data: { valid: true } }) }), { valid: true });
    await assert.rejects(async () => parseJsonResponse({ ok: false, status: 500 }), /HTTP 500/);
    await assert.rejects(parseJsonResponse({ ok: true, json: async () => ({ success: false }) }), /Invalid JSON response/);
});

test('Forms.init() builds each form once', async () => {
    contactForm();
    const first = await Forms.init(document);
    const second = await Forms.init(document);
    assert.equal(first.length, 1);
    assert.ok(first[0] instanceof JsonForm);
    assert.equal(second.length, 0);
    assert.equal(Container.get('form-contact-1'), first[0]);
});

test('JSON form: posts the form to its AJAX action', async () => {
    const [form] = await Forms.init(contactForm());
    server.answer({ valid: true, values: [], data: {} });
    document.querySelector('[name="coretik-form-contact[email]"]').value = 'jane@example.org';

    await form.submit();

    assert.equal(server.requests[0].url, '/wp-admin/admin-ajax.php');
    const body = posted(server.requests[0]);
    assert.equal(body.action, 'form-contact');
    assert.equal(body['coretik-form-contact[email]'], 'jane@example.org');
});

test('JSON form: binds the errors to the fields and shows the global messages as text', async () => {
    const [form] = await Forms.init(contactForm());
    server.answer({ valid: false, errors: [{ field: 'email', messages: ['<b>Invalid</b>'] }], messages: ['Global error'] });

    await form.submit();

    const field = document.querySelector('[data-field="email"]');
    assert.ok(field.classList.contains('form-error'));
    assert.equal(field.querySelector('.form-error__list li').textContent, '<b>Invalid</b>');
    assert.equal(field.querySelector('input').getAttribute('aria-invalid'), 'true');
    assert.equal(document.querySelector('[data-form-errors] li').textContent, 'Global error');
    assert.equal(field.querySelector('b'), null);

    // The error disappears on the first change of the field
    field.querySelector('input').dispatchEvent(new Event('input'));
    assert.ok(!field.classList.contains('form-error'));
});

test('JSON form: an error on a field missing in the DOM is displayed as a global message', async () => {
    const [form] = await Forms.init(contactForm());
    server.answer({ valid: false, errors: [{ field: 'unknown', messages: ['Lost error'] }], messages: [] });

    await form.submit();

    assert.equal(document.querySelector('[data-form-errors] li').textContent, 'Lost error');
});

test('JSON form: success message, success class and event', async () => {
    const dom = contactForm();
    const [form] = await Forms.init(dom);
    let succeeded = false;
    form.onSuccess(() => {
        succeeded = true;
    });
    server.answer({ valid: true, values: [{ field: 'email', label: 'Email', value: 'jane@example.org' }], data: {} });

    await form.submit();

    assert.ok(succeeded);
    assert.ok(dom.classList.contains('form-is-success'));
    assert.equal(dom.querySelector('.form-success__content').textContent, 'Merci !');
});

test('JSON form: the successTpl replaces the success message', async () => {
    const dom = contactForm();
    const [form] = await Forms.init(dom);
    server.answer({ valid: true, values: [], data: { successTpl: '<p class="thanks">Thanks</p>' } });

    await form.submit();

    assert.equal(dom.querySelector('[data-form-success] .thanks').textContent, 'Thanks');
    assert.equal(dom.querySelector('.form-success__content'), null);
});

test('JSON form: follows the redirect_url', async () => {
    const [form] = await Forms.init(contactForm());
    const redirects = [];
    const original = JsonForm.redirect;
    JsonForm.redirect = (url) => redirects.push(url);
    server.answer({ valid: true, redirect_url: 'https://example.test/thanks/', data: {} });

    try {
        await form.submit();
    } finally {
        JsonForm.redirect = original;
    }

    assert.deepEqual(redirects, ['https://example.test/thanks/']);
});

test('JSON form: a network error shows the configured message', async () => {
    configure({ messages: { networkError: 'Network down' } });
    const [form] = await Forms.init(contactForm());
    const error = console.error;
    console.error = () => {};

    try {
        await form.submit();
    } finally {
        console.error = error;
    }

    assert.equal(document.querySelector('[data-form-errors] li').textContent, 'Network down');
});

test('Conditional field: shown when its condition is met', async () => {
    const dom = contactForm();
    await Forms.init(dom);
    const company = dom.querySelector('[data-field="company"]');
    const kind = dom.querySelector('select');

    assert.ok(company.hasAttribute('hidden'));
    kind.value = 'pro';
    kind.dispatchEvent(new Event('change'));
    assert.ok(!company.hasAttribute('hidden'));
});

test('Field factory: a registered field type is used for its elements', async () => {
    class UpperField extends AbstractField {}
    FieldFactory.register((dom) => dom.getAttribute('data-field') === 'email', UpperField);

    const [form] = await Forms.init(contactForm());

    assert.ok(form.getField('email') instanceof UpperField);
    assert.ok(!(form.getField('kind') instanceof UpperField));
});

test('Tunnel: validates a step on the server, then opens the next one', async () => {
    const dom = tunnelForm();
    const [form] = await Forms.init(dom);
    assert.ok(form instanceof TunnelForm);
    assert.equal(form.getCurrentStep().getIndex(), 1);

    server.answer({ valid: true, values: [{ step: 0, field: 'email', label: 'Email', value: 'jane@example.org' }], notices: ['Check your inbox'] });
    dom.querySelector('[data-next-step]').click();
    await flush(10);

    const body = posted(server.requests[0]);
    assert.equal(server.requests[0].url, '/ajax');
    assert.equal(body.action, 'form-registration');
    assert.equal(body['tunnel-action'], 'validate-step');
    assert.equal(body.step, '1');
    assert.equal(form.getCurrentStep().getIndex(), 2);
    assert.equal(dom.querySelector('[data-form-notices] li').textContent, 'Check your inbox');
    assert.ok(!dom.querySelector('[data-previous-step]').hasAttribute('hidden'));
    assert.ok(!dom.querySelector('[data-go-to-step="2"]').hasAttribute('disabled'));
});

test('Tunnel: a step in error stays current and shows the errors of the step', async () => {
    const dom = tunnelForm();
    const [form] = await Forms.init(dom);

    server.answer({ valid: false, errors: [{ step: 0, field: 'email', messages: ['Required'] }], messages: [] });
    dom.querySelector('[data-next-step]').click();
    await flush(10);

    assert.equal(form.getCurrentStep().getIndex(), 1);
    assert.equal(dom.querySelector('[data-field="email"] .form-error__list li').textContent, 'Required');
    assert.ok(!form.getStep(2).isOpen());
});

test('Tunnel: the errors bound to no step or field are displayed with the global messages', async () => {
    const dom = tunnelForm();
    await Forms.init(dom);

    server.answer({
        valid: false,
        errors: [{ step: 0, field: 'unknown', messages: ['Field error'] }, { step: 7, field: 'email', messages: ['Step error'] }],
        messages: ['Global error']
    });
    dom.querySelector('[data-next-step]').click();
    await flush(10);

    const messages = Array.from(dom.querySelectorAll('[data-form-errors] li')).map((item) => item.textContent);
    assert.deepEqual(messages, ['Global error', 'Field error', 'Step error']);
});

test('Tunnel: the summary step lists the validated values as text, then submits the whole tunnel', async () => {
    const dom = tunnelForm();
    const [form] = await Forms.init(dom);
    const next = dom.querySelector('[data-next-step]');

    server.answer({ valid: true, values: [{ step: 0, field: 'email', label: 'Email', value: '<i>jane@example.org</i>' }] });
    next.click();
    await flush(10);
    dom.querySelector('[name="coretik-form-registration[name]"]').value = 'Jane';
    server.answer({
        valid: true,
        values: [
            { step: 0, field: 'email', label: 'Email', value: '<i>jane@example.org</i>' },
            { step: 1, field: 'name', label: 'Name', value: 'Jane' }
        ]
    });
    next.click();
    await flush(10);

    assert.equal(form.getCurrentStep().getIndex(), 3);
    assert.equal(dom.querySelector('[data-summary-step="1"] .block-form-summary-step__value').textContent, '<i>jane@example.org</i>');
    assert.equal(dom.querySelector('[data-summary-step="2"] .block-form-summary-step__value').textContent, 'Jane');
    assert.equal(next.type, 'submit');
    assert.equal(next.textContent, 'Register');

    server.answer({ valid: true, values: [], data: {} });
    await form.submit();

    assert.equal(posted(server.requests[2])['tunnel-action'], 'validate-tunnel');
    assert.ok(dom.classList.contains('form-is-success'));
    await flush(10);
    assert.equal(form.getCurrentStep().getIndex(), 1);
});

test('Tunnel: the final submission goes back to the first step in error', async () => {
    const dom = tunnelForm();
    const [form] = await Forms.init(dom);
    form.getStep(1).setValid(true);
    form.getStep(2).setValid(true);
    form.goToStep(3, false);

    server.answer({ valid: false, errors: [{ step: 1, field: 'name', messages: ['Too short'] }], messages: [] });
    await form.submit();
    await flush(10);

    assert.equal(form.getCurrentStep().getIndex(), 2);
    assert.equal(dom.querySelector('[data-field="name"] .form-error__list li').textContent, 'Too short');
});

test('Tunnel: the default step labels are configurable', async () => {
    configure({ messages: { next: 'Next step', stepTitle: 'Step {index} of {total}' } });
    html(`<form data-form-id="t" data-form-tunnel>
        <div data-form-step data-step="1"></div>
        <div data-form-step data-step="2" hidden></div>
    </form>`);
    const [form] = await Forms.init(document);

    assert.equal(form.getStep(1).getSubmitText(), 'Next step');
    assert.equal(form.getStep(2).getSubmitText(), defaultMessages.submit);
    assert.equal(form.getStep(1).getTitle(), 'Step 1 of 2');
});

test('Fetcher: posts the fetch_form action and appends the modals', async () => {
    configure({ ajaxUrl: '/fetch-ajax' });
    html('<div id="modals-container"></div>');
    let loaded = null;
    document.getElementById('modals-container').addEventListener('forms:modals-loaded', (e) => {
        loaded = e.detail.formId;
    });
    server.answer({ form: '<form></form>', modals: '<div class="modal" id="m1"></div>' });

    const data = await new Fetcher().fetch('registration', { camp: '12' });

    assert.equal(server.requests[0].url, '/fetch-ajax');
    const body = posted(server.requests[0]);
    assert.equal(body.action, 'fetch_form');
    assert.equal(body.formId, 'registration');
    assert.equal(body.camp, '12');
    assert.equal(data.form, '<form></form>');
    assert.ok(document.getElementById('m1'));
    assert.equal(loaded, 'registration');
});

test('ValidationError keeps the answer', () => {
    const error = new ValidationError({ valid: false });
    assert.ok(error instanceof Error);
    assert.deepEqual(error.data, { valid: false });
});
