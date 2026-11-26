import { throttle } from './utils.js';

/**
 * Steps timeline ([data-form-navigation]) and Previous ([data-previous-step]) / Next ([data-next-step]) buttons of the tunnel.
 */
class TunnelFormNavigation {
    constructor(dom, form) {
        this.form = form;
        this.dom = dom;
        this.progressBtn = this.form.getForm().querySelectorAll('[data-next-step]');
        this.previousBtn = this.form.getForm().querySelectorAll('[data-previous-step]');

        this.clickListeners();
        if (this.dom) {
            this.aria();
            this.setHeight();
            window.addEventListener('resize', throttle(this.setHeight.bind(this), 1000));
        }
    }

    loading(isLoading) {
        this.progressBtn.forEach((btn) => {
            if (isLoading) {
                btn.setAttribute('disabled', 'disabled');
            }
            else {
                btn.removeAttribute('disabled');
            }
            btn.classList.toggle('btn--loading', isLoading);
        });

        const item = this.getNavigationItem(this.form.getCurrentStep().getIndex());
        if (item) {
            item.classList.toggle('block-form-step--loading', isLoading);
        }
    }

    /**
     * @param {boolean} focusSubmit focuses the submit button when the current step is the submission step
     */
    update(focusSubmit = false) {
        if (this.dom) {
            this.dom.querySelectorAll('[data-form-navigation-item]').forEach((item) => {
                const itemStepIndex = parseInt(item.getAttribute('data-form-navigation-step'), 10);
                const itemStep = this.form.getStep(itemStepIndex);
                const button = item.querySelector('button');
                if (!itemStep || !button) {
                    return;
                }

                item.classList.remove('block-form-step--current', 'block-form-step--valid', 'block-form-step--selectable', 'block-form-step--has-error');
                button.setAttribute('disabled', 'disabled');
                button.setAttribute('aria-expanded', 'false');
                button.removeAttribute('aria-current');
                button.setAttribute('tabindex', '-1');

                if (itemStep.isOpen()) {
                    item.classList.add('block-form-step--selectable');
                    button.removeAttribute('disabled');
                }

                if (itemStep.isValid()) {
                    item.classList.add('block-form-step--valid');
                }

                if (itemStep.hasError()) {
                    item.classList.add('block-form-step--has-error');
                }

                if (itemStep.isCurrent()) {
                    item.classList.add('block-form-step--current');
                    button.setAttribute('aria-expanded', 'true');
                    button.setAttribute('aria-current', 'step');
                    button.setAttribute('tabindex', '0');
                }
            });
        }

        const currentStep = this.form.getCurrentStep();
        this.progressBtn.forEach((btn) => {
            btn.setAttribute('data-progress', Math.ceil((100 * currentStep.getIndex()) / this.form.getSteps().length));
        });

        const isSubmitStep = this.form.hasSummary() ? currentStep.isSummary() : currentStep.isLast();
        if (isSubmitStep) {
            this.makeSubmit(focusSubmit);
        }
        else {
            this.unmakeSubmit();
        }

        this.previousBtn.forEach((btn) => {
            if (currentStep.isFirst()) {
                btn.setAttribute('disabled', 'disabled');
                btn.setAttribute('hidden', 'hidden');
            }
            else {
                btn.removeAttribute('disabled');
                btn.removeAttribute('hidden');
            }
        });
    }

    makeSubmit(focus = true) {
        const currentStep = this.form.getCurrentStep();
        let focused = false;
        this.progressBtn.forEach((btn) => {
            btn.textContent = currentStep.getSubmitText();
            btn.type = 'submit';
            btn.classList.remove('btn--next');
            btn.classList.add('btn--submit');

            if (focus && !focused) {
                focused = true;
                btn.focus();
            }
        });
    }

    unmakeSubmit() {
        const currentStep = this.form.getCurrentStep();
        this.progressBtn.forEach((btn) => {
            btn.textContent = currentStep.getSubmitText();
            btn.type = 'button';
            btn.classList.remove('btn--submit');
            btn.classList.add('btn--next');
        });
    }

    clickListeners() {
        /**
         * Click on a step button (timeline or summary)
         */
        this.form.getForm().querySelectorAll('[data-go-to-step]').forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();

                if (this.form.isLoading) {
                    e.stopPropagation();
                    this.form.getCurrentStep().shake();
                    return;
                }

                this.form.goToStep(parseInt(button.getAttribute('data-go-to-step'), 10));
            });
        });

        /**
         * Click on Previous
         */
        this.previousBtn.forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();

                if (this.form.isLoading) {
                    e.stopPropagation();
                    this.form.getCurrentStep().shake();
                    return;
                }

                const previous = this.form.getCurrentStep().previous();
                if (!previous) {
                    this.form.getCurrentStep().shake();
                    return;
                }
                this.form.goToStep(previous.getIndex());
            });
        });

        /**
         * Click on Next: validates the current step. On the submission step, the button is of type
         * submit and the native submit event takes over (TunnelForm.submit).
         */
        this.form.getForm().querySelectorAll('[data-next-step]').forEach((button) => {
            button.addEventListener('click', (e) => {
                if (button.type === 'submit') {
                    return;
                }

                e.preventDefault();

                if (this.form.isLoading) {
                    this.form.getCurrentStep().shake();
                    return;
                }

                const currentStep = this.form.getCurrentStep();
                currentStep
                .validate()
                .then(() => {
                    button.classList.add('btn--success');
                    const next = currentStep.next();
                    if (next) {
                        this.form.goToStep(next.getIndex());
                    }
                    setTimeout(() => {
                        button.classList.remove('btn--success');
                    }, 1000);
                    this.update();
                })
                .catch(() => {
                    // Goes to the first step in error + focus + shake
                    this.form.handleErrors();
                    this.form.getCurrentStep().shake();
                    button.classList.add('btn--error');
                    setTimeout(() => {
                        button.classList.remove('btn--error');
                    }, 1000);
                    this.update();
                });
            });
        });
    }

    /**
     * Keyboard navigation in the timeline: left / right arrows between the open steps.
     */
    aria() {
        this.dom.querySelectorAll('[data-go-to-step]').forEach((button) => {
            const targetStepIndex = parseInt(button.getAttribute('data-go-to-step'), 10);

            button.addEventListener('keydown', (e) => {
                if (!['ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                if (this.form.isLoading) {
                    this.form.getCurrentStep().shake();
                    return;
                }

                const targetStep = this.form.getStep(targetStepIndex);
                if (!targetStep) {
                    return;
                }

                if (e.key === 'ArrowDown') {
                    this.form.getCurrentStep().focus();
                    return;
                }

                const step = e.key === 'ArrowLeft' ? targetStep.previous() : targetStep.next();
                if (step && step.isOpen()) {
                    this.form.goToStep(step.getIndex(), false);
                    const item = this.getNavigationItem(step.getIndex());
                    if (item) {
                        item.querySelector('button').focus();
                    }
                }
                else {
                    this.form.getCurrentStep().shake();
                }
            });
        });
    }

    getNavigation() {
        return this.dom;
    }

    getNavigationItem(stepIndex) {
        return this.dom ? this.dom.querySelector(`[data-form-navigation-step="${stepIndex}"]`) : null;
    }

    setHeight() {
        if (!this.dom) {
            return;
        }
        this.dom.style.minHeight = '0';
        const height = this.dom.offsetHeight * 1.2;
        this.dom.style.minHeight = `${height}px`;
    }

    static create(dom, form) {
        return new TunnelFormNavigation(dom, form);
    }
}

export default TunnelFormNavigation;
