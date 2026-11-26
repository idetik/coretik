import AbstractField from './AbstractField.js';
import PinField from './PinField.js';

/**
 * Builds the field object of a [data-field] element: the first registered type whose matcher accepts it, else AbstractField.
 *
 *   Factory.register((dom) => dom.matches('[data-field-type="map"]'), MapField);
 */
const types = [
    {
        // Auto submitted code (OTP)
        matcher: (dom) => dom.getAttribute('data-field') === 'pin' || dom.querySelector('[data-auto-submit]') !== null,
        FieldClass: PinField
    }
];

class Factory {
    /**
     * @param {function(HTMLElement): boolean} matcher
     * @param {typeof AbstractField} FieldClass
     */
    static register(matcher, FieldClass) {
        types.unshift({ matcher, FieldClass });
    }

    static createField(dom, form) {
        const type = types.find((item) => item.matcher(dom));
        return (type ? type.FieldClass : AbstractField).create(dom, form);
    }
}

export default Factory;
