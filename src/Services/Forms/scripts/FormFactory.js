import { Container } from './Container.js';
import JsonForm from './JsonForm.js';
import TunnelForm from './TunnelForm.js';

const initialized = new WeakSet();

/**
 * Builds the form object of a <form>: TunnelForm for [data-form-tunnel], JsonForm otherwise.
 * The classes can be replaced (FormFactory.classes.json = MyJsonForm) to extend them.
 */
class FormFactory {
    static createForm(dom) {
        const FormClass = dom.hasAttribute('data-form-tunnel') ? FormFactory.classes.tunnel : FormFactory.classes.json;
        const formSystem = FormClass.create(dom);
        initialized.add(dom);

        formSystem.onSuccess((form) => {
            form.classList.add('form-is-success');
        });

        Container.add(formSystem);

        return formSystem;
    }

    static isInitialized(dom) {
        return initialized.has(dom);
    }
}

FormFactory.classes = {
    json: JsonForm,
    tunnel: TunnelForm
};

Container.setFactory(FormFactory.createForm);

export default FormFactory;
