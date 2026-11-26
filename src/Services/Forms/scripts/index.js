/**
 * Scripts of the Coretik JSON forms (JsonForm, [data-form]) and tunnel forms (TunnelForm, [data-form-tunnel]).
 * ES modules without dependency, imported from the theme build:
 *
 *   import Forms from '<vendor>/idetik/coretik/src/Services/Forms/scripts/index.js';
 *   Forms.configure({ ajaxUrl: window.AppData.ajax_url });
 *   Forms.init(document);
 */
import { ready, ValidationError, parseJsonResponse } from './utils.js';
import { configure, settings } from './config.js';
import FormFactory from './FormFactory.js';
import Form from './Form.js';
import JsonForm from './JsonForm.js';
import TunnelForm from './TunnelForm.js';
import TunnelFormStep from './TunnelFormStep.js';
import TunnelFormNavigation from './TunnelFormNavigation.js';
import FormContainer, { Container } from './Container.js';
import Fetcher from './Fetcher.js';
import { Factory as FieldFactory, AbstractField, PinField } from './Fields/index.js';

class Forms {
    /**
     * Initializes the JSON forms ([data-form]) and the tunnels ([data-form-tunnel]) of the context.
     * Can be called again on a fragment loaded through AJAX: the forms already initialized are skipped.
     *
     * @param {Document|HTMLElement} context
     * @returns {Promise<Array>} the forms initialized by this call
     */
    static init(context = document) {
        return new Promise((resolve) => {
            ready(() => {
                const forms = [];
                const doms = Array.from(context.querySelectorAll('[data-form-tunnel], [data-form]'));
                if (context instanceof Element && context.matches('[data-form-tunnel], [data-form]')) {
                    doms.unshift(context);
                }
                doms.forEach((dom) => {
                    if (!FormFactory.isInitialized(dom)) {
                        forms.push(FormFactory.createForm(dom));
                    }
                });
                resolve(forms);
            });
        });
    }

    /**
     * @param {{ajaxUrl?: string, messages?: Object<string, string>}} options
     */
    static configure(options = {}) {
        return configure(options);
    }

    /**
     * Loads the HTML of a form allowed by Coretik\Services\Forms\Fetcher: { form, modals }
     */
    static fetch(formId, params = {}) {
        return new Fetcher().fetch(formId, params);
    }
}

Forms.container = Container;

export default Forms;

export {
    Forms,
    settings,
    configure,
    FormFactory,
    Form,
    JsonForm,
    TunnelForm,
    TunnelFormStep,
    TunnelFormNavigation,
    FormContainer,
    Container,
    Fetcher,
    FieldFactory,
    AbstractField,
    PinField,
    ValidationError,
    parseJsonResponse
};
