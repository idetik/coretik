import { ValidationError, parseJsonResponse, elementIsVisibleInViewport } from './utils.js';
import { message } from './config.js';

/**
 * Step of a tunnel ([data-form-step][data-step="n"], n 1-based).
 */
class TunnelFormStep {
    constructor(step, form) {
        this.dom = step;
        this.index = parseInt(step.getAttribute('data-step'), 10);
        this.valid = false;
        this.errors = [];
        this.data = [];
        this.current = false;
        this.form = form;

        this.getFields().forEach((field) => {
            field.whenRendering(() => {
                if (this.isCurrent()) {
                    this.setHeight();
                }
            });
        });

        this.form.whenRendering(() => {
            if (this.form.isHidden()) {
                return;
            }

            // Checked again in the callback: the step may have changed meanwhile (navigation after validation)
            setTimeout(() => {
                if (this.isCurrent()) {
                    this.setHeight();
                }
            }, 10);
        });

        // The height of the steps container is set in pixels: set again when the current step is resized
        // (block revealed, error message...), otherwise its content overflows the buttons.
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => {
                if (this.isCurrent() && !this.dom.hasAttribute('hidden')) {
                    this.setHeight();
                }
            }).observe(this.dom);
        }
    }

    /**
     * Validates the step on the server. Resolves when the step is valid, rejects otherwise
     * (ValidationError or network error).
     */
    validate() {
        this.resetErrors();
        this.resetData();
        this.form.clearMessages();
        this.form.loading(true);

        const formDom = this.form.getForm();
        if (!elementIsVisibleInViewport(formDom, true)) {
            formDom.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        this.form.dispatchEvent(new CustomEvent('validating-step', {
            detail: {
                step: this
            }
        }));

        const formData = new FormData(formDom);
        formData.append('tunnel-action', 'validate-step');
        formData.append('step', this.index);
        formData.append('action', this.form.fetchAction());

        return fetch(this.form.fetchEndpoint(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(parseJsonResponse)
        .then((data) => {
            this.form.showMessages(this.form.globalMessages(data));
            this.form.showNotices(data.notices || []);

            if (data.valid) {
                this.setValid(true);
                this.data = (data.values || []).filter((value) => value.step === this.index - 1);
                this.form.dispatchEvent(new CustomEvent('step-is-valid', {
                    detail: {
                        step: this
                    }
                }));
                return;
            }

            this.setValid(false);
            (data.errors || []).forEach((error) => {
                const step = this.form.getStep(error.step + 1);
                if (step) {
                    step.setValid(false);
                    step.addError(error);
                }
            });
            this.form.dispatchEvent(new CustomEvent('step-is-invalid', {
                detail: {
                    step: this
                }
            }));
            throw new ValidationError(data);
        })
        .catch((error) => {
            this.setValid(false);
            if (!(error instanceof ValidationError)) {
                console.error('Forms: step validation failed', error);
                this.form.showMessages([message('networkError')]);
            }
            throw error;
        })
        .finally(() => {
            this.form.loading(false);
            this.form.dispatchEvent(new CustomEvent('step-validated', {
                detail: {
                    step: this
                }
            }));
        });
    }

    previous() {
        if (this.isFirst()) {
            return null;
        }

        return this.form.getStep(this.index - 1);
    }

    next() {
        if (this.isLast()) {
            return null;
        }

        return this.form.getStep(this.index + 1);
    }

    isOpen() {
        if (this.isFirst()) {
            return true;
        }

        const previous = this.previous();
        return previous ? previous.getValid() : false;
    }

    clearAnimation() {
        clearTimeout(this.visibilityTimer);
        this.dom.classList.remove('fade-out--to-left', 'fade-out--to-right', 'fade-in--from-left', 'fade-in--from-right');
    }

    hide(to = 'left') {
        this.clearAnimation();
        this.dom.classList.add(`fade-out--to-${to}`);
        // Waits for the end of the leaving animation
        this.visibilityTimer = setTimeout(() => {
            this.dom.setAttribute('hidden', '');
            this.dom.classList.remove(`fade-out--to-${to}`);
        }, 350);
    }

    show(from = 'right') {
        // Cancels a running hiding (quick return to the step)
        this.clearAnimation();
        this.dom.classList.add(`fade-in--from-${from}`);
        this.dom.removeAttribute('hidden');

        this.setHeight();

        // Waits for the end of the entering animation
        this.visibilityTimer = setTimeout(() => {
            this.dom.classList.remove(`fade-in--from-${from}`);
        }, 400);
    }

    setHeight() {
        const container = this.form.getForm().querySelector('[data-form-steps], .block-form-fields');
        if (container) {
            container.style.height = `${this.dom.offsetHeight}px`;
        }
    }

    shake() {
        this.dom.classList.add('shake');
        setTimeout(() => {
            this.dom.classList.remove('shake');
        }, 400);
    }

    smoothScroll() {
        const navigation = this.form.navigation ? this.form.navigation.getNavigation() : null;

        (navigation || this.dom).scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    focus(options = {}) {
        const field = this.getFields().find((item) => item.isFocusable());
        if (field) {
            field.focus(options);
        }
    }

    isFirst() {
        return this.index === this.form.getFirstStep().getIndex();
    }

    isCurrent() {
        return this.current;
    }

    isLast() {
        return this.index === this.form.getLastStep().getIndex();
    }

    getFields() {
        const stepFields = this.dom.querySelectorAll('[data-field]');
        return Array.from(stepFields)
        .map((field) => this.form.getField(field.getAttribute('data-field')))
        .filter(Boolean);
    }

    hasError() {
        return this.errors.length > 0;
    }

    getErrors() {
        return this.errors;
    }

    addError(error) {
        const formField = this.form.getField(error.field);
        if (!formField) {
            // Field missing in the DOM: the message is displayed at the form level (Form.globalMessages())
            return;
        }

        this.errors.push(error);
        (error.messages || []).forEach((text) => {
            formField.addError(text);
        });
    }

    resetError(fieldName) {
        const formField = this.form.getField(fieldName);
        this.errors = this.errors.filter((error) => error.field !== fieldName);
        if (formField) {
            formField.resetErrors();
        }
    }

    resetErrors() {
        [...this.errors].forEach((error) => {
            this.resetError(error.field);
        });
    }

    getData() {
        return this.data.filter((value) => {
            const field = this.form.getField(value.field);
            return field ? field.shouldShow() : false;
        });
    }

    getHumanValues() {
        return this.getData()
        .filter((value) => value.value !== '' && value.value !== null)
        .map((value) => {
            const field = this.form.getField(value.field);
            return field ? field.humanize(value) : value;
        });
    }

    resetData() {
        this.data = [];
    }

    isValid() {
        return this.getValid() === true && !this.hasError();
    }

    isSummary() {
        return this.dom.hasAttribute('data-form-step-summary');
    }

    getForm() {
        return this.form;
    }

    getValid() {
        return this.valid;
    }

    getIndex() {
        return this.index;
    }

    getTitle() {
        const title = this.dom.querySelector('[data-step-title]');
        if (!title) {
            return message('stepTitle', { index: this.index, total: this.form.getSteps().length });
        }

        return title.textContent
        .replace(/[+*\n]|^\d+/g, ' ')
        .replace(/[ ]{2,}/g, ' ')
        .trim();
    }

    getDom() {
        return this.dom;
    }

    getSubmitText() {
        if (this.dom.hasAttribute('data-form-step-submit-text')) {
            return this.dom.getAttribute('data-form-step-submit-text');
        }

        if (this.isSummary() || this.isLast()) {
            return message('submit');
        }

        return message('next');
    }

    setValid(valid) {
        this.valid = valid;

        if (!valid) {
            const nextStep = this.next();
            if (nextStep) {
                nextStep.setValid(false);
            }
        }
    }

    setIndex(index) {
        this.index = index;
    }

    setDom(dom) {
        this.dom = dom;
    }

    setAsCurrent() {
        if (this.isSummary()) {
            this.summarize();
        }

        const direction = this.form.getCurrentStep().getIndex() < this.getIndex() ? 'right' : 'left';
        this.getSiblings().forEach((step) => step.setAsNotCurrent(
            this.getIndex() < step.getIndex() ? 'right' : 'left'
        ));
        this.show(direction);
        this.current = true;
        return this;
    }

    setAsNotCurrent(direction = 'left') {
        if (this.isCurrent() || !this.dom.hasAttribute('hidden')) {
            this.hide(direction);
        }
        this.current = false;
        return this;
    }

    getSiblings() {
        return this.form.getSteps().filter((step) => step.getIndex() !== this.getIndex());
    }

    static valueToText(value) {
        if (Array.isArray(value)) {
            return value.join(', ');
        }
        if (value && typeof value === 'object') {
            return value.name || '';
        }
        return value === null || value === undefined ? '' : String(value);
    }

    /**
     * Builds the summary ([data-summary-step]) from the values validated by the server.
     * Everything is inserted as plain text: the values come from the user input.
     */
    summarize() {
        const summaryLines = this.dom.querySelectorAll('[data-summary-step]');
        const steps = this.form.getSteps();

        summaryLines.forEach((line) => {
            line.querySelectorAll('.block-form-summary-step__value, .block-form-summary-step__values').forEach((value) => value.remove());

            const stepIndex = parseInt(line.getAttribute('data-summary-step'), 10);
            const step = steps.find((stepItem) => stepItem.getIndex() === stepIndex);
            if (!step) {
                return;
            }

            const data = step.getHumanValues();
            const before = line.querySelector('button');
            if (data.length > 1) {
                const list = document.createElement('ul');
                list.classList.add('block-form-summary-step__values');
                data.forEach((value) => {
                    const item = document.createElement('li');
                    const key = document.createElement('span');
                    key.className = 'block-form-summary-step__key';
                    key.textContent = value.label;
                    const val = document.createElement('span');
                    val.className = 'block-form-summary-step__value';
                    val.textContent = TunnelFormStep.valueToText(value.value);
                    item.append(key, val);
                    list.appendChild(item);
                });
                line.insertBefore(list, before);
            }
            else if (data.length === 1) {
                const val = document.createElement('span');
                val.className = 'block-form-summary-step__value';
                val.textContent = TunnelFormStep.valueToText(data[0].value);
                line.insertBefore(val, before);
            }
        });
    }

    static create(step, form) {
        return new TunnelFormStep(step, form);
    }
}

export default TunnelFormStep;
