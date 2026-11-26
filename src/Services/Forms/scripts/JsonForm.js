import Form from './Form.js';

/**
 * One step JSON form ([data-form]), Coretik\Services\Forms\JsonForm.
 */
class JsonForm extends Form {
    constructor(form) {
        super(form);
        this.valid = false;
        this.errors = [];
        this.data = [];
        this.submitBtn = this.dom.querySelector('button[type="submit"]');
    }

    loading(isLoading) {
        super.loading(isLoading);

        if (this.submitBtn === null) {
            return;
        }

        if (isLoading) {
            this.submitBtn.setAttribute('disabled', 'disabled');
        }
        else {
            this.submitBtn.removeAttribute('disabled');
        }
        this.submitBtn.classList.toggle('btn--loading', isLoading);
    }

    handleResponse(data) {
        if (data.valid) {
            this.data = data.values || [];
        }
        else {
            (data.errors || []).forEach((error) => {
                this.setValid(false);
                this.addError(error);
            });
        }

        return super.handleResponse(data);
    }

    fetchPayload() {
        const formData = new FormData(this.getForm());
        formData.append('action', this.fetchAction());
        return formData;
    }

    submit(e) {
        if (e) {
            e.preventDefault();
        }

        if (this.isLoading) {
            this.shake();
            return Promise.resolve();
        }

        this.loading(true);
        this.resetErrors();
        this.resetData();
        this.clearMessages();

        return this.fetchPromise()
        .catch(() => {
            this.handleErrors();
            this.shake();
            if (this.submitBtn) {
                this.submitBtn.classList.add('btn--error');
                setTimeout(() => {
                    this.submitBtn.classList.remove('btn--error');
                }, 1000);
            }
        })
        .then(() => {
            this.setValid(false);
        });
    }

    handleErrors() {
        if (!this.hasError()) {
            return;
        }

        const field = this.getField(this.getErrors()[0].field);
        if (field) {
            field.focus();
        }
    }

    shake() {
        this.dom.classList.add('shake');
        setTimeout(() => {
            this.dom.classList.remove('shake');
        }, 400);
    }

    hasError() {
        return this.errors.length > 0;
    }

    getErrors() {
        return this.errors;
    }

    addError(error) {
        const formField = this.getField(error.field);
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
        const formField = this.getField(fieldName);
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
            const field = this.getField(value.field);
            return field ? field.shouldShow() : false;
        });
    }

    getHumanValues() {
        return this.getData()
        .filter((value) => value.value !== '')
        .map((value) => {
            const field = this.getField(value.field);
            return field ? field.humanize(value) : value;
        });
    }

    resetData() {
        this.data = [];
    }

    isValid() {
        return this.getValid() === true && !this.hasError();
    }

    getValid() {
        return this.valid;
    }

    getDom() {
        return this.dom;
    }

    setValid(valid) {
        this.valid = valid;
    }

    setDom(dom) {
        this.dom = dom;
    }
}

export default JsonForm;
