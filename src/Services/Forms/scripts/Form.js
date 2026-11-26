import { Factory } from './Fields/index.js';
import { Container } from './Container.js';
import { ValidationError, parseJsonResponse } from './utils.js';
import { message, ajaxUrl } from './config.js';

/**
 * Base of the JSON forms. JSON answer (Coretik\Services\Forms\Core\JsonResponse):
 * {
 *   success: true,
 *   data: {
 *     valid: bool,
 *     values: [{ field, label, private, value, step? }],  // when valid
 *     errors: [{ field, messages: [], step? }],           // when not valid (step: server index, 0-based)
 *     messages: [],                                       // global errors (not bound to a field)
 *     notices: [],                                        // non blocking warnings
 *     redirect_url?: string,                              // the browser goes there once valid
 *     data: { successTpl? }                               // additional data on success
 *   }
 * }
 */
class Form {
    constructor(dom) {
        this.id = dom.getAttribute('id');
        this.dom = dom;
        this.fields = [];
        this.isLoading = false;
        this.successContainer = this.dom.querySelector('[data-form-success]');
        this.messagesContainer = this.dom.querySelector('[data-form-errors]');
        this.noticesContainer = this.dom.querySelector('[data-form-notices]');

        this.buildFields();

        this.dom.addEventListener('submit', this.submit.bind(this));
    }

    buildFields() {
        this.fields = Array.from(this.dom.querySelectorAll('[data-field]')).map((field) => Factory.createField(field, this));
        this.fields.forEach((field) => {
            field.mount();
            field.ping();
        });
    }

    getFields() {
        return this.fields;
    }

    getField(name) {
        return this.getFields().find((field) => field.name === name);
    }

    getForm() {
        return this.dom;
    }

    getFormName() {
        return this.getForm().getAttribute('data-form-id');
    }

    setForm(form) {
        this.dom = form;
    }

    loading(isLoading) {
        this.isLoading = isLoading;
        this.dom.classList.toggle('form-is-loading', isLoading);
        this.dom.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    reset() {
        this.getFields().forEach((field) => field.reset());
    }

    focus(options = {}) {
        const field = this.getFields().find((item) => item.isFocusable());
        if (field) {
            field.focus(options);
        }
    }

    show() {
        const wasHidden = this.isHidden();
        this.dom.removeAttribute('hidden');

        if (wasHidden) {
            this.dom.dispatchEvent(new Event('transition_hidden_to_visible'));
        }
        this.dispatchRenderingEvent();

        return this;
    }

    hide() {
        const wasHidden = this.isHidden();
        this.dom.setAttribute('hidden', 'hidden');

        if (!wasHidden) {
            this.dom.dispatchEvent(new Event('transition_visible_to_hidden'));
        }
        this.dispatchRenderingEvent();

        return this;
    }

    whenShowing(callback, options = {}) {
        this.dom.addEventListener('transition_hidden_to_visible', callback, options);
    }

    whenHiding(callback, options = {}) {
        this.dom.addEventListener('transition_visible_to_hidden', callback, options);
    }

    whenRendering(callback, options = {}) {
        this.dom.addEventListener('rendering', callback, options);
    }

    isHidden() {
        return this.dom.hasAttribute('hidden');
    }

    dispatchRenderingEvent() {
        this.dom.dispatchEvent(new Event('rendering'));
    }

    dispatchEvent(event) {
        this.dom.dispatchEvent(event);
    }

    on(event, callback, options = {}) {
        this.dom.addEventListener(event, callback, options);
    }

    /**
     * Plain text list of messages in a container (global errors, notices)
     */
    static renderList(container, messages, visibleClass) {
        if (!container) {
            return;
        }

        container.replaceChildren();
        container.classList.remove(visibleClass);
        if (messages.length === 0) {
            return;
        }

        const list = document.createElement('ul');
        list.classList.add('form-message-list');
        messages.forEach((text) => {
            const item = document.createElement('li');
            item.textContent = text;
            list.appendChild(item);
        });
        container.appendChild(list);
        container.classList.add(visibleClass);
    }

    /**
     * Global messages (not bound to a field), displayed in [data-form-errors].
     */
    showMessages(messages = []) {
        Form.renderList(this.messagesContainer, messages, 'form-error--visible');
        this.dispatchRenderingEvent();
    }

    clearMessages() {
        Form.renderList(this.messagesContainer, [], 'form-error--visible');
    }

    /**
     * Non blocking warnings, displayed in [data-form-notices].
     */
    showNotices(notices = []) {
        Form.renderList(this.noticesContainer, notices, 'form-notice--visible');
        this.dispatchRenderingEvent();
    }

    clearNotices() {
        Form.renderList(this.noticesContainer, [], 'form-notice--visible');
    }

    /**
     * Displays the success message ([data-form-success][data-success-message]) in the aria-live region,
     * unless a success template is already injected.
     */
    showSuccess() {
        if (!this.successContainer) {
            return;
        }

        this.successContainer.removeAttribute('hidden');

        const successMessage = this.successContainer.getAttribute('data-success-message');
        if (!successMessage || this.successContainer.childElementCount > 0) {
            return;
        }

        const paragraph = document.createElement('p');
        paragraph.className = 'form-success';

        const icon = document.createElement('span');
        icon.className = 'icon icon-info form-success__icon';
        icon.setAttribute('aria-hidden', 'true');

        const prefix = document.createElement('span');
        prefix.className = 'visuallyhidden';
        prefix.textContent = message('successPrefix');

        const content = document.createElement('span');
        content.className = 'form-success__content';
        content.textContent = successMessage;

        paragraph.append(icon, prefix, content);
        this.successContainer.appendChild(paragraph);
    }

    /**
     * The success template is HTML built by the server (never unescaped user input).
     * When it holds a form, this one is initialized and replaces the current form once submitted.
     */
    successTpl(inner) {
        const template = document.createElement('template');
        template.innerHTML = `<div>${inner}</div>`;

        const node = template.content.firstChild;
        const nestedForm = node.querySelector('form');
        if (nestedForm) {
            const form = nestedForm.cloneNode(true);
            form.setAttribute('data-parent-form', this.id);
            nestedForm.remove();
            const FormSystem = Container.createForm(form);
            FormSystem.onSuccess((inheritForm) => {
                const parent = document.getElementById(inheritForm.getAttribute('data-parent-form'));
                if (!parent) {
                    return;
                }
                parent.classList.add('fadeOutUp');
                setTimeout(() => {
                    parent.remove();
                }, 750);
            });
            this.dom.insertAdjacentElement('afterend', FormSystem.getForm());
        }

        if (this.successContainer) {
            this.successContainer.innerHTML = node.innerHTML;
            this.successContainer.removeAttribute('hidden');
        }

        this.dom.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    fetchAction() {
        if (this.getForm().hasAttribute('data-ajax-action')) {
            return this.getForm().getAttribute('data-ajax-action');
        }

        return `form-${this.getFormName()}`;
    }

    fetchEndpoint() {
        return this.getForm().getAttribute('data-ajax-endpoint') || ajaxUrl();
    }

    /**
     * Sends the form. The returned promise is rejected on a validation error (ValidationError)
     * as on a network / server error: the caller handles the display.
     */
    fetchPromise() {
        this.dispatchEvent(new CustomEvent('submitting', {
            detail: {
                form: this
            }
        }));

        return fetch(this.fetchEndpoint(), {
            method: 'POST',
            body: this.fetchPayload(),
            credentials: 'same-origin'
        })
        .then(parseJsonResponse)
        .then((data) => this.handleResponse(data))
        .then((data) => {
            if (data.redirect_url) {
                this.dispatchSuccess();
                this.constructor.redirect(data.redirect_url);
                return data;
            }

            if (data.successTpl) {
                this.successTpl(data.successTpl);
            }
            else {
                this.showSuccess();
            }
            this.dispatchSuccess();
            this.reset();
            return data;
        })
        .catch((error) => {
            if (!(error instanceof ValidationError)) {
                console.error('Forms: submission failed', error);
                this.showMessages([message('networkError')]);
            }
            this.dispatchError();
            throw error;
        })
        .finally(() => {
            this.loading(false);
            this.dispatchEvent(new Event('submitted'));
        });
    }

    /**
     * Handles the JSON answer: returns data.data (with redirect_url) when valid, throws a ValidationError otherwise.
     * Completed by the child classes to bind the errors to the fields / steps.
     */
    handleResponse(data) {
        this.showMessages(this.globalMessages(data));
        this.showNotices(data.notices || []);

        if (data.valid) {
            const extra = { ...(data.data || {}) };
            if (data.redirect_url) {
                extra.redirect_url = data.redirect_url;
            }
            return extra;
        }

        throw new ValidationError(data);
    }

    /**
     * Messages displayed in [data-form-errors]: the global messages of the answer, and the messages of the errors
     * bound to nothing in the form (field missing in the DOM...).
     */
    globalMessages(data = {}) {
        const orphans = (data.errors || [])
        .filter((error) => this.isOrphanError(error))
        .flatMap((error) => error.messages || []);

        return [...(data.messages || []), ...orphans];
    }

    isOrphanError(error) {
        return !this.getField(error.field);
    }

    /**
     * Redirection after a valid submission answering a redirect_url (JsonForm::redirectTo()).
     */
    static redirect(url) {
        window.location.assign(url);
    }

    dispatchSuccess() {
        const event = new CustomEvent('submitting-success', {
            detail: {
                form: this.getForm()
            }
        });
        this.dom.dispatchEvent(event);
    }

    dispatchError() {
        const event = new CustomEvent('submitting-error', {
            detail: {
                form: this.getForm()
            }
        });
        this.dom.dispatchEvent(event);
    }

    onSuccess(callback) {
        this.dom.addEventListener('submitting-success', (e) => {
            callback(e.detail.form);
        });
    }

    onError(callback) {
        this.dom.addEventListener('submitting-error', (e) => {
            callback(e.detail.form);
        });
    }

    static create(dom) {
        return new this(dom);
    }
}

export default Form;
