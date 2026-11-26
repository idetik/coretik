// DOM for the forms scripts tests (node --test): jsdom globals, set before the scripts are imported.
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://example.test/page/' });
const { window } = dom;

for (const name of ['window', 'document', 'EventTarget', 'Node', 'Element', 'HTMLElement', 'HTMLFormElement', 'Event', 'CustomEvent', 'AbortController', 'AbortSignal', 'FormData', 'CSS']) {
    Object.defineProperty(globalThis, name, { value: name === 'window' ? window : window[name], configurable: true, writable: true });
}
globalThis.CSS = globalThis.CSS || { escape: (value) => String(value).replace(/[^\w-]/g, (c) => `\\${c}`) };
window.HTMLElement.prototype.scrollIntoView = () => {};

/**
 * Answers of the stubbed fetch(), in order. Each request is recorded: { url, body (FormData) }.
 */
export const server = {
    requests: [],
    answers: [],
    reset() {
        this.requests = [];
        this.answers = [];
    },
    answer(data, { ok = true, status = 200, success = true } = {}) {
        this.answers.push({ ok, status, json: { success, data } });
    }
};

globalThis.fetch = async (url, options = {}) => {
    server.requests.push({ url, body: options.body });
    const answer = server.answers.shift();
    if (!answer) {
        throw new TypeError('Network error');
    }
    return { ok: answer.ok, status: answer.status, json: async () => answer.json };
};

/**
 * Lets the pending promises and the short timers run.
 */
export const flush = (ms = 0) => new Promise((resolve) => {
    setTimeout(resolve, ms);
});

export const html = (markup) => {
    document.body.innerHTML = markup;
    return document.body.firstElementChild;
};

export { window };
