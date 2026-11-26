/**
 * Registry of the forms initialized in the page (Forms.container).
 */
class FormContainer extends EventTarget {
    constructor() {
        super();
        this.forms = [];
        this.factory = null;
    }

    hasForms() {
        return this.forms.length > 0;
    }

    has(id) {
        return this.forms.some((form) => form.id === id);
    }

    add(form) {
        this.forms.push(form);
        this.dispatchEvent(new CustomEvent('form-added', { detail: form }));
    }

    get(id) {
        return this.forms.find((form) => form.id === id);
    }

    getAll() {
        return this.forms;
    }

    remove(id) {
        this.forms = this.forms.filter((form) => form.id !== id);
    }

    /**
     * Set by FormFactory: avoids the circular import Form <-> FormFactory.
     */
    setFactory(factory) {
        this.factory = factory;
    }

    createForm(dom) {
        if (!this.factory) {
            throw new Error('Forms: no factory registered');
        }
        return this.factory(dom);
    }
}

export const Container = new FormContainer();

export default FormContainer;
