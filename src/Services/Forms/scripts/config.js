/**
 * Settings of the forms scripts, changed with Forms.configure().
 * The default messages are French, as the default strings of the PHP side.
 */
export const settings = {
    // admin-ajax.php URL, used when a form has no data-ajax-endpoint attribute (and by the Fetcher)
    ajaxUrl: null,
    messages: {
        networkError: 'Une erreur est survenue, veuillez réessayer dans quelques instants.',
        successPrefix: 'Information !',
        errorPrefix: 'Attention !',
        submit: 'Envoyer',
        next: 'Continuer',
        stepTitle: 'Étape {index} / {total}'
    }
};

/**
 * @param {{ajaxUrl?: string, messages?: Object<string, string>}} options
 */
export const configure = (options = {}) => {
    if (options.ajaxUrl !== undefined) {
        settings.ajaxUrl = options.ajaxUrl;
    }
    if (options.messages) {
        Object.assign(settings.messages, options.messages);
    }
    return settings;
};

/**
 * Message with its {placeholders} replaced: message('stepTitle', { index: 1, total: 3 })
 */
export const message = (key, vars = {}) => Object.keys(vars).reduce(
    (text, name) => text.split(`{${name}}`).join(String(vars[name])),
    settings.messages[key] || ''
);

export const ajaxUrl = () => settings.ajaxUrl
    || (typeof window !== 'undefined' && window.AppData && window.AppData.ajax_url)
    || '/wp-admin/admin-ajax.php';
