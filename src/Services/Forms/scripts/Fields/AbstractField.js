import { addIdRef, removeIdRef } from '../utils.js';
import { message } from '../config.js';

const TEXT_INPUTS = 'input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="date"])';
const ERROR_TARGETS = 'input:not([type="hidden"]), select, textarea';

class AbstractField {
    constructor(dom, form) {
        this.form = form;
        this.dom = dom;

        if (!this.dom.hasAttribute('data-field')) {
            throw new Error('Field must have a data-field attribute');
        }

        this.name = dom.getAttribute('data-field');
        this.inputs = []; // Plusieurs si checkbox ou radio
        this.errors = [];
        this.errorElement = null;
        this.dependsOn = [];
        this.conditionals = [];

        this.abortControllers = {};
        this.init();
    }

    init() {
        this.type = this.resolveType();
        this.initInput();
        this.initConditionals();
    }

    /**
     * data-conditional-field='[{"field":"field_name","value":"value","operator":"="}, [{...} AND {...}]]'
     * The first level is an OR, a nested array is an AND.
     */
    initConditionals() {
        let conditionals;
        if (this.dom.hasAttribute('data-conditional-field')) {
            conditionals = this.dom.getAttribute('data-conditional-field');
        }
        else {
            const element = this.dom.querySelector('[data-conditional-field]');
            conditionals = element ? element.getAttribute('data-conditional-field') : null;
        }

        if (!conditionals) {
            return;
        }

        const setConditionals = (conditional) => {
            if (Array.isArray(conditional)) {
                return conditional.map(setConditionals);
            }

            this.dependsOn.push(conditional.field);
            return {
                field: conditional.field,
                value: conditional.value,
                operator: conditional.operator || '='
            };
        };

        try {
            this.conditionals = JSON.parse(conditionals).map(setConditionals);
        }
        catch (e) {
            console.error(`Forms: invalid data-conditional-field on "${this.name}"`, e);
            this.conditionals = [];
        }
    }

    initInput() {
        switch (this.type) {
        case 'select':
            this.inputs = [this.dom.querySelector('select')];
            break;
        case 'checkbox':
        case 'radio':
            this.inputs = Array.from(this.dom.querySelectorAll(`input[type="${this.type}"]`));
            break;
        case 'textarea':
            this.inputs = [this.dom.querySelector('textarea')];
            break;
        case 'text':
            this.inputs = [this.dom.querySelector(TEXT_INPUTS)];
            break;
        case 'file':
        case 'hidden':
        case 'date':
            this.inputs = [this.dom.querySelector(`input[type="${this.type}"]`)];
            break;
        default:
            this.inputs = [];
            break;
        }
    }

    getInput() {
        return this.inputs[0] || null;
    }

    conditionalIsMet(conditional) {
        const field = this.getForm().getField(conditional.field);
        if (!field) {
            return false;
        }

        const value = field.getValue();
        if (!value) {
            return false;
        }

        const internalValue = Array.isArray(value) ? value.map((v) => v.internal) : value.internal;

        let conditionalValue = conditional.value;
        if (conditionalValue === '') {
            conditionalValue = null;
        }

        switch (conditional.operator) {
        case '=':
            // On a checkbox group, "=" means "the value is checked"
            return Array.isArray(internalValue) ? internalValue.includes(conditionalValue) : internalValue === conditionalValue;
        case '!=':
            return Array.isArray(internalValue) ? !internalValue.includes(conditionalValue) : internalValue !== conditionalValue;
        case '>':
            return internalValue > conditionalValue;
        case '<':
            return internalValue < conditionalValue;
        case '>=':
            return internalValue >= conditionalValue;
        case '<=':
            return internalValue <= conditionalValue;
        case 'contains':
            return internalValue !== null && internalValue.includes(conditionalValue);
        case 'not-contains':
            return internalValue === null || !internalValue.includes(conditionalValue);
        case 'in':
            return Array.isArray(conditionalValue) && conditionalValue.includes(internalValue);
        case 'not-in':
            return !Array.isArray(conditionalValue) || !conditionalValue.includes(internalValue);
        default:
            return false;
        }
    }

    shouldShow() {
        if (this.conditionals.length === 0) {
            return true;
        }

        const conditionalCallback = (conditional) => {
            if (Array.isArray(conditional)) {
                return conditional.every(conditionalCallback);
            }

            return this.conditionalIsMet(conditional);
        };

        return this.conditionals.some(conditionalCallback);
    }

    mount() {
        this.dependsOn.forEach((fieldName) => {
            const field = this.getForm().getField(fieldName);
            if (!field) {
                console.warn(`Forms: field "${this.name}" depends on unknown field "${fieldName}"`);
                return;
            }
            field.onChange(() => {
                this.ping();
            });
        });

        if (this.type === 'file') {
            this.mountFile();
        }
    }

    mountFile() {
        const input = this.getInput();
        const button = this.dom.querySelector('[data-input-file-button]');
        if (!input || !button) {
            return;
        }

        const emulateClick = (e) => {
            if (e.type === 'click' || (e.type === 'keydown' && (e.key === 'Enter' || e.key === ' '))) {
                e.preventDefault();
                e.stopPropagation();
                input.click();
            }
        };

        button.addEventListener('keydown', emulateClick);
        button.addEventListener('click', emulateClick);
        input.addEventListener('change', () => {
            const filledSpan = this.dom.querySelector('[data-input-file-button-filled]');
            const emptySpan = this.dom.querySelector('[data-input-file-button-empty]');
            const filenameSpan = this.dom.querySelector('[data-input-file-button-filename]');
            const files = Array.from(input.files || []);

            if (filledSpan) {
                filledSpan.style.display = files.length > 0 ? 'inline' : 'none';
            }
            if (emptySpan) {
                emptySpan.style.display = files.length > 0 ? 'none' : 'flex';
            }
            if (filenameSpan) {
                filenameSpan.textContent = files.map((file) => file.name).join(', ');
            }
        });
    }

    ping() {
        if (this.shouldShow()) {
            if (this.isHidden()) {
                this.show();
            }
        }
        else if (!this.isHidden()) {
            this.hide();
        }
    }

    resolveType() {
        if (this.dom.querySelector('select')) {
            return 'select';
        }

        if (this.dom.querySelector('input[type="checkbox"]')) {
            return 'checkbox';
        }

        if (this.dom.querySelector('input[type="radio"]')) {
            return 'radio';
        }

        if (this.dom.querySelector(TEXT_INPUTS)) {
            return 'text';
        }

        if (this.dom.querySelector('textarea')) {
            return 'textarea';
        }

        if (this.dom.querySelector('input[type="file"]')) {
            return 'file';
        }

        if (this.dom.querySelector('input[type="date"]')) {
            return 'date';
        }

        if (this.dom.querySelector('input[type="hidden"]')) {
            return 'hidden';
        }

        return 'unknown';
    }

    labelOf(input) {
        if (!input.id) {
            return input.value;
        }

        const label = this.dom.querySelector(`label[for="${CSS.escape(input.id)}"]`);
        if (!label) {
            return input.value;
        }

        // First text node: ignores the descriptions / icons added in the label
        const textNode = Array.from(label.childNodes).find((node) => node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() !== '');
        return (textNode ? textNode.nodeValue : label.textContent).trim();
    }

    /**
     * @returns {{internal: *, human: *}|Array<{internal: *, human: *}>}
     */
    getValue() {
        const defaultValue = {
            internal: null,
            human: null
        };

        if (!this.shouldShow()) {
            return defaultValue;
        }

        const input = this.getInput();

        switch (this.type) {
        case 'select': {
            const option = input.selectedOptions[0];
            if (!option || option.value === '') {
                return defaultValue;
            }
            return {
                internal: option.value,
                human: option.textContent.trim()
            };
        }
        case 'checkbox':
            return this.inputs.filter((checkbox) => checkbox.checked).map((checkbox) => ({
                internal: checkbox.value,
                human: this.labelOf(checkbox)
            }));
        case 'radio': {
            const checked = this.inputs.find((radio) => radio.checked);
            if (!checked) {
                return defaultValue;
            }
            return {
                internal: checked.value,
                human: this.labelOf(checked)
            };
        }
        case 'date':
            // Human readable: 2015-06-01 -> 01/06/2015
            return {
                internal: input.value,
                human: input.value ? input.value.split('-').reverse().join('/') : input.value
            };
        case 'text':
        case 'textarea':
            return {
                internal: input.value,
                human: input.value
            };
        case 'file': {
            const files = Array.from(input.files || []);
            if (files.length === 0) {
                return defaultValue;
            }
            return {
                internal: files[0].name,
                human: files.map((file) => file.name).join(', ')
            };
        }
        case 'hidden':
            return {
                internal: input.value,
                human: input.value.replace(/./g, '*')
            };
        default:
            return defaultValue;
        }
    }

    /**
     * Replaces the server value ({ field, label, value }) by its human readable label
     * when it matches the value currently entered. Returns a new object.
     */
    humanize(value) {
        const valueInForm = this.getValue();
        if (!valueInForm) {
            return value;
        }

        let valueInFormToCompare;
        let humanValueInForm;
        if (Array.isArray(valueInForm)) {
            valueInFormToCompare = valueInForm.map((val) => val.internal).toString();
            humanValueInForm = valueInForm.map((val) => val.human).join(', ');
        }
        else {
            valueInFormToCompare = valueInForm.internal;
            humanValueInForm = valueInForm.human;
        }

        let valueToCompare;
        if (Array.isArray(value.value)) {
            valueToCompare = value.value.toString();
        }
        else if (value.value && typeof value.value === 'object' && value.value.type === 'file') {
            valueToCompare = value.value.name;
        }
        else {
            valueToCompare = value.value;
        }

        if (valueInFormToCompare === valueToCompare) {
            return { ...value, value: humanValueInForm };
        }

        return value;
    }

    setValue(value) {
        switch (this.type) {
        case 'select': {
            const select = this.getInput();
            const oldValue = select.value;
            select.value = value;
            if (oldValue !== select.value) {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            break;
        }
        case 'checkbox':
            this.inputs.forEach((checkbox) => {
                const wasChecked = checkbox.checked;
                checkbox.checked = [].concat(value).includes(checkbox.value);

                if (wasChecked !== checkbox.checked) {
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            break;
        case 'radio':
            this.inputs.forEach((radio) => {
                const wasChecked = radio.checked;
                radio.checked = radio.value === value;

                if (radio.checked && wasChecked !== radio.checked) {
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            break;
        default: {
            const input = this.getInput();
            if (input && input.type !== 'file') {
                input.value = value;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            break;
        }
        }
    }

    getInputs() {
        return this.inputs;
    }

    /**
     * Callback called on each change of the value.
     * options: { once, signal } (see addEventListener)
     */
    onChange(callback, options = {}) {
        const listenerOptions = { ...options };
        let controller = null;

        if (!listenerOptions.signal) {
            controller = new AbortController();
            listenerOptions.signal = controller.signal;
        }

        // "once" is handled here: several event types share the same signal
        const once = listenerOptions.once === true;
        delete listenerOptions.once;

        const innerCallback = (e) => {
            callback(e);

            // With a given signal, the caller aborts (see printErrors / killController)
            if (once && controller) {
                controller.abort();
            }
        };

        switch (this.type) {
        case 'text':
        case 'textarea':
            this.addEventListener('input', innerCallback, listenerOptions);
            this.addEventListener('change', innerCallback, listenerOptions);
            break;

        default:
            this.addEventListener('change', innerCallback, listenerOptions);
            break;
        }
    }

    show() {
        const wasHidden = this.isHidden();
        this.dom.removeAttribute('hidden');

        if (wasHidden) {
            this.dom.dispatchEvent(new Event('transition_hidden_to_visible'));
        }
        this.dispatchRenderingEvent();
    }

    hide() {
        const wasHidden = this.isHidden();
        this.dom.setAttribute('hidden', 'hidden');

        if (!wasHidden) {
            this.dom.dispatchEvent(new Event('transition_visible_to_hidden'));
        }
        this.dispatchRenderingEvent();
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

    isValid() {
        return !this.hasError();
    }

    hasError() {
        return this.errors.length > 0;
    }

    setErrors(errors) {
        this.resetErrors();
        this.errors = errors;
        this.printErrors();
    }

    addError(error) {
        this.setErrors(this.errors.concat(error));
    }

    hasAbortController(name) {
        return Object.prototype.hasOwnProperty.call(this.abortControllers, name);
    }

    getAbortController(name) {
        if (!this.hasAbortController(name)) {
            this.abortControllers[name] = new AbortController();
        }

        return this.abortControllers[name];
    }

    killController(name) {
        if (this.hasAbortController(name)) {
            this.abortControllers[name].abort();
            delete this.abortControllers[name];
        }
    }

    errorId() {
        const base = this.form.id || this.form.getFormName() || 'form';
        return `${base}-${this.name.replace(/[^\w-]/g, '_')}-error`;
    }

    printErrors() {
        if (!this.hasError()) {
            return;
        }

        // The errors disappear on the first change of the field
        this.onChange(() => {
            this.resetErrors();
        }, { once: true, signal: this.getAbortController('resetErrors').signal });

        const errorId = this.errorId();
        this.dom.classList.add('form-error');
        this.dom.querySelectorAll(ERROR_TARGETS).forEach((input) => {
            input.setAttribute('aria-invalid', 'true');
            addIdRef(input, 'aria-describedby', errorId);
        });

        if (!this.errorElement) {
            this.errorElement = document.createElement('div');
            this.errorElement.id = errorId;
            this.errorElement.className = 'form-error__content';

            const icon = document.createElement('span');
            icon.className = 'icon icon-important form-error__icon';
            icon.setAttribute('aria-hidden', 'true');

            const prefix = document.createElement('span');
            prefix.className = 'visuallyhidden';
            prefix.textContent = message('errorPrefix');

            const list = document.createElement('ul');
            list.className = 'form-error__list';

            this.errorElement.append(icon, prefix, list);
            this.dom.appendChild(this.errorElement);
        }

        const list = this.errorElement.querySelector('.form-error__list');
        list.replaceChildren();
        this.errors.forEach((error) => {
            const item = document.createElement('li');
            item.textContent = error;
            list.appendChild(item);
        });

        this.dispatchRenderingEvent();
    }

    resetErrors() {
        this.killController('resetErrors');
        this.errors = [];
        this.dom.classList.remove('form-error');

        const errorId = this.errorId();
        this.dom.querySelectorAll(ERROR_TARGETS).forEach((input) => {
            input.removeAttribute('aria-invalid');
            removeIdRef(input, 'aria-describedby', errorId);
        });

        if (this.errorElement) {
            this.errorElement.remove();
            this.errorElement = null;
        }

        this.dispatchRenderingEvent();
    }

    reset() {
        this.resetErrors();
        const input = this.getInput();

        switch (this.type) {
        case 'select':
            input.value = '';
            if (input.selectedIndex === -1) {
                input.selectedIndex = 0;
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
            break;
        case 'checkbox':
        case 'radio':
            this.inputs.forEach((item) => {
                if (item.checked) {
                    item.checked = false;
                    item.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            break;
        case 'text':
        case 'textarea':
        case 'file':
        case 'date':
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
            break;
        default:
            // The hidden fields (ids, context...) are kept
            break;
        }
    }

    isFocusable() {
        return !this.isHidden() && this.inputs.length > 0 && this.type !== 'hidden';
    }

    focus(options = {}) {
        const input = this.type === 'radio' ? (this.inputs.find((radio) => radio.checked) || this.getInput()) : this.getInput();
        if (input) {
            input.focus(options);
        }
    }

    shake() {
        this.dom.classList.add('shake');
        setTimeout(() => {
            this.dom.classList.remove('shake');
        }, 400);
    }

    disable() {
        this.dom.querySelectorAll('input, select, textarea').forEach((input) => {
            input.setAttribute('disabled', 'disabled');
        });
    }

    enable() {
        this.dom.querySelectorAll('input, select, textarea').forEach((input) => {
            input.removeAttribute('disabled');
        });
    }

    getForm() {
        return this.form;
    }

    getDom() {
        return this.dom;
    }

    /**
     * Listens to an event on the inputs of the field (or on its container when the field has no input).
     */
    addEventListener(event, callback, options = {}) {
        const targets = this.inputs.length > 0 ? this.inputs : [this.dom];
        targets.forEach((target) => {
            target.addEventListener(event, callback, options);
        });
    }

    /**
     * Dispatch a rendering event on a visual change
     */
    dispatchRenderingEvent() {
        this.dom.dispatchEvent(new Event('rendering'));
    }

    static create(dom, form) {
        return new this(dom, form);
    }
}

export default AbstractField;
