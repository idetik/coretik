;(function () {
    'use strict';

    var openClass = 'is-open';
    var hasModalClass = 'has-modal';

    function container() {
        return document.getElementById('modals-container');
    }

    function openModals() {
        return Array.prototype.slice.call(document.querySelectorAll('#modals-container .modal.' + openClass));
    }

    function toggleContainer() {
        var hasOpen = openModals().length > 0;
        if (container()) {
            container().classList.toggle(hasModalClass, hasOpen);
        }
        document.body.classList.toggle(hasModalClass, hasOpen);
    }

    function openModal(modal) {
        if (!modal) {
            return;
        }
        openModals().forEach(closeModal);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add(openClass);
        toggleContainer();

        var focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) {
            focusable.focus();
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }
        modal.classList.remove(openClass);
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        toggleContainer();
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-modal]');
        if (trigger) {
            e.preventDefault();
            openModal(document.getElementById(trigger.getAttribute('data-modal')));
            return;
        }

        var closer = e.target.closest('[data-modal-close], .modal-discard');
        if (closer) {
            e.preventDefault();
            closeModal(document.getElementById(closer.getAttribute('data-modal-close')) || closer.closest('.modal'));
            return;
        }

        if (e.target.id === 'modals-backdrop') {
            openModals().forEach(closeModal);
        }
    });

    document.addEventListener('keydown', function (e) {
        if ('Escape' === e.key) {
            openModals().forEach(closeModal);
        }
    });

    // 1.x API
    if (window.jQuery) {
        window.jQuery.extend({
            openModal: function ($modal) {
                openModal(window.jQuery($modal).get(0));
            },
            closeModal: function ($modal) {
                closeModal(window.jQuery($modal).closest('.modal').get(0));
            }
        });
    }
})();
