import { parseJsonResponse } from './utils.js';
import { ajaxUrl } from './config.js';

/**
 * Loads the HTML of a form through the admin-ajax action "fetch_form" (Coretik\Services\Forms\Fetcher).
 * The modals answered are appended to #modals-container (Coretik modals service), then the "forms:modals-loaded"
 * event is dispatched for the theme to initialize its components.
 *
 *   new Fetcher().fetch('registration').then(({ form }) => { container.innerHTML = form; Forms.init(container); });
 */
class Fetcher {
    constructor(url = null, options = {}) {
        this.url = url;
        this.options = { action: 'fetch_form', ...options };
    }

    fetch(formId, params = {}) {
        const formData = new FormData();
        formData.append('formId', formId);
        formData.append('action', this.options.action);
        Object.keys(params).forEach((key) => {
            formData.append(key, params[key]);
        });

        return fetch(this.url || ajaxUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(parseJsonResponse)
        .then((data) => {
            if (data.modals) {
                const modalsContainer = document.getElementById('modals-container');
                if (modalsContainer) {
                    modalsContainer.insertAdjacentHTML('beforeend', data.modals);
                    modalsContainer.dispatchEvent(new CustomEvent('forms:modals-loaded', {
                        bubbles: true,
                        detail: { formId }
                    }));
                }
            }
            return data;
        });
    }
}

export default Fetcher;
