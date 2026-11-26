import AbstractField from './AbstractField.js';

/**
 * Code field (OTP, [data-field] holding an input [data-auto-submit]): submits the form as soon as the input matches
 * its whole pattern (pattern attribute, else maxlength digits).
 */
class PinField extends AbstractField {
    constructor(dom, form) {
        super(dom, form);

        this.currentValue = '';

        const input = this.getInput();
        let pattern = null;
        if (input && input.hasAttribute('pattern')) {
            pattern = input.getAttribute('pattern');
        }
        else if (input && input.hasAttribute('maxlength')) {
            pattern = `\\d{${parseInt(input.getAttribute('maxlength'), 10)}}`;
        }

        // Anchored as the HTML pattern attribute: "1234567" must not match \d{6}
        this.pattern = pattern ? new RegExp(`^(?:${pattern})$`) : null;
    }

    mount() {
        super.mount();

        if (this.pattern) {
            this.onChange((e) => {
                const value = this.getValue().internal || '';
                if (this.pattern.test(value) && this.currentValue !== value) {
                    this.currentValue = value;
                    this.form.submit(e);
                }
            });
        }
    }
}

export default PinField;
