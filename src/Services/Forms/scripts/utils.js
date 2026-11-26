/**
 * Internal helpers of the forms scripts.
 */

export const ready = (callback) => {
    if (document.readyState !== 'loading') {
        callback();
    }
    else {
        document.addEventListener('DOMContentLoaded', callback);
    }
};

export const throttle = (callback, delay) => {
    let timer = null;

    return (...args) => {
        if (timer === null) {
            callback(...args);
            timer = setTimeout(() => {
                timer = null;
            }, delay);
        }
    };
};

export const elementIsVisibleInViewport = (el, partiallyVisible = false) => {
    const {
        top, left, bottom, right
    } = el.getBoundingClientRect();
    const { innerHeight, innerWidth } = window;

    return partiallyVisible
        ? ((top > 0 && top < innerHeight) || (bottom > 0 && bottom < innerHeight))
          && ((left > 0 && left < innerWidth) || (right > 0 && right < innerWidth))
        : top >= 0 && left >= 0 && bottom <= innerHeight && right <= innerWidth;
};

/**
 * Adds / removes an id in an id list attribute (aria-describedby...) without overwriting the others.
 */
export const addIdRef = (element, attribute, id) => {
    const ids = (element.getAttribute(attribute) || '').split(/\s+/).filter(Boolean);
    if (!ids.includes(id)) {
        ids.push(id);
    }
    element.setAttribute(attribute, ids.join(' '));
};

export const removeIdRef = (element, attribute, id) => {
    const ids = (element.getAttribute(attribute) || '').split(/\s+/).filter((ref) => ref && ref !== id);
    if (ids.length > 0) {
        element.setAttribute(attribute, ids.join(' '));
    }
    else {
        element.removeAttribute(attribute);
    }
};

/**
 * Validation error answered by the server (valid answer, invalid data), to tell apart from a network / server error.
 */
export class ValidationError extends Error {
    constructor(data = {}) {
        super('Form validation failed');
        this.name = 'ValidationError';
        this.data = data;
    }
}

/**
 * Parses an admin-ajax answer in the wp_send_json format: { success, data }.
 */
export const parseJsonResponse = (response) => {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json().then((json) => {
        if (!json || json.success !== true) {
            throw new Error('Invalid JSON response');
        }
        return json.data || {};
    });
};
