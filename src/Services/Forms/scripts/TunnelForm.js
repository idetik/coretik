import TunnelFormStep from './TunnelFormStep.js';
import TunnelFormNavigation from './TunnelFormNavigation.js';
import Form from './Form.js';
import { ValidationError, elementIsVisibleInViewport } from './utils.js';

// Enter in these fields validates the current step (not in textarea / select / checkbox / radio)
const ENTER_TO_VALIDATE = 'input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]):not([type="button"]):not([type="submit"])';

/**
 * Multi-step form ([data-form-tunnel]), Coretik\Services\Forms\Tunnel\TunnelForm: each step is validated by the server
 * (tunnel-action=validate-step), then the whole tunnel on submission (tunnel-action=validate-tunnel).
 */
class TunnelForm extends Form {
    constructor(form) {
        super(form);
        this.currentStepTimer = null;
        this.initSteps();
        this.initNavigation();

        this.dom.querySelectorAll(ENTER_TO_VALIDATE).forEach((item) => {
            item.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                if (this.isLoading) {
                    this.getCurrentStep().shake();
                    return;
                }

                const currentStep = this.getCurrentStep();

                if (currentStep.isSummary() || currentStep.isLast()) {
                    this.submit(e);
                    return;
                }

                currentStep.validate()
                .then(() => {
                    const nextStep = currentStep.next();
                    if (nextStep) {
                        this.goToStep(nextStep.getIndex());
                    }
                })
                .catch(() => {
                    currentStep.shake();
                    this.handleErrors();
                    this.navigation.update();
                    item.focus();
                });
            });
        });
    }

    initSteps() {
        const steps = this.dom.querySelectorAll('[data-form-step]');
        this.steps = Array.from(steps).map((step) => TunnelFormStep.create(step, this));
        this.steps.sort((a, b) => a.getIndex() - b.getIndex());

        const currentStep = parseInt(this.dom.getAttribute('data-current-step'), 10) || this.getFirstStep().getIndex();
        (this.getStep(currentStep) || this.getFirstStep()).setAsCurrent();

        // The steps before the current step are considered valid
        this.steps.forEach((step) => {
            if (step.getIndex() < currentStep) {
                step.setValid(true);
            }
        });
    }

    initNavigation() {
        this.navigation = TunnelFormNavigation.create(this.dom.querySelector('[data-form-navigation]'), this);
    }

    loading(isLoading) {
        super.loading(isLoading);
        if (this.navigation) {
            this.navigation.loading(isLoading);
        }
    }

    goToStep(stepIndex, giveFocus = true) {
        if (this.currentStepTimer) {
            clearTimeout(this.currentStepTimer);
        }

        const stepToGo = this.getStep(stepIndex);
        if (!stepToGo || !stepToGo.isOpen()) {
            this.getCurrentStep().shake();
            return;
        }

        if (stepToGo.getIndex() === this.getCurrentStep().getIndex()) {
            this.getCurrentStep().shake();
            return;
        }

        const event = new CustomEvent('stepChange', {
            detail: {
                from: this.getCurrentStep().getIndex(),
                to: stepIndex
            }
        });

        const step = stepToGo.setAsCurrent();
        this.navigation.update(giveFocus);
        if (giveFocus) {
            if (!elementIsVisibleInViewport(step.getDom(), true)) {
                step.smoothScroll();
            }

            this.currentStepTimer = setTimeout(() => {
                step.focus({
                    preventScroll: true
                });
            }, 300); // CSS animation duration
        }
        this.dom.dispatchEvent(event);
    }

    /**
     * The server step indexes are 0-based, the DOM ones (data-step) 1-based.
     */
    handleResponse(data) {
        if (!data.valid) {
            (data.errors || []).forEach((error) => {
                const step = this.getStep(error.step + 1);
                if (step) {
                    step.setValid(false);
                    step.addError(error);
                }
            });
        }

        return super.handleResponse(data);
    }

    isOrphanError(error) {
        return super.isOrphanError(error) || !this.getStep(error.step + 1);
    }

    fetchPayload() {
        const formData = new FormData(this.getForm());
        formData.append('tunnel-action', 'validate-tunnel');
        formData.append('action', this.fetchAction());
        return formData;
    }

    submit(e) {
        if (e) {
            e.preventDefault();
        }

        if (this.isLoading) {
            this.getCurrentStep().shake();
            return Promise.resolve();
        }

        this.loading(true);
        this.clearMessages();

        this.getSteps().forEach((step) => {
            step.resetErrors();
            step.resetData();
        });

        return this.fetchPromise()
        .then(() => {
            // Success: the tunnel starts over
            this.steps.forEach((step) => {
                step.setValid(false);
            });
            this.goToStep(this.getFirstStep().getIndex(), false);
        })
        .catch((error) => {
            // Goes to the first step in error + focus + shake
            this.handleErrors();
            this.getCurrentStep().shake();
            if (!(error instanceof ValidationError)) {
                return;
            }
            const submitBtn = this.getForm().querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('btn--error');
                setTimeout(() => {
                    submitBtn.classList.remove('btn--error');
                }, 1000);
            }
        })
        .finally(() => {
            this.navigation.update();
        });
    }

    validateStep(stepIndex) {
        return this.getStep(stepIndex).validate();
    }

    handleErrors() {
        const step = this.getSteps().find((item) => item.hasError());
        if (!step) {
            return;
        }

        if (step.getIndex() !== this.getCurrentStep().getIndex()) {
            this.goToStep(step.getIndex(), false);
        }

        const field = this.getField(step.getErrors()[0].field);
        if (field) {
            field.focus();
        }
    }

    getSteps() {
        return this.steps;
    }

    hasSummary() {
        return this.steps.some((step) => step.isSummary());
    }

    getStep(index) {
        return this.steps.find((step) => step.getIndex() === index);
    }

    getFirstStep() {
        return this.steps.at(0);
    }

    getLastStep() {
        return this.steps.at(-1);
    }

    getCurrentStep() {
        return this.getSteps().find((step) => step.isCurrent()) || this.getFirstStep();
    }

    setSteps(steps) {
        this.steps = steps;
    }

    setCurrentStep(stepIndex) {
        this.getStep(stepIndex).setAsCurrent();
    }

    focus(options = {}) {
        this.getCurrentStep().focus(options);
    }
}

export default TunnelForm;
