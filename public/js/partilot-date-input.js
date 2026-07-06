/**
 * Restringe input[type=date]: año de 4 dígitos, fechas reales y rangos min/max.
 */
(function () {
    'use strict';

    var DEFAULT_MIN = '1900-01-01';
    var DEFAULT_MAX = '2100-12-31';

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function todayIso() {
        var t = new Date();
        return t.getFullYear() + '-' + pad2(t.getMonth() + 1) + '-' + pad2(t.getDate());
    }

    function formatEs(iso) {
        if (!iso || iso.indexOf('-') === -1) return iso;
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function detectProfile(input) {
        if (input.dataset.dateProfile) {
            return input.dataset.dateProfile;
        }
        var name = (input.name || '').toLowerCase();
        if (name.indexOf('birthday') !== -1 || name.indexOf('nacimiento') !== -1) {
            return 'birthday';
        }
        if (name.indexOf('execution') !== -1 || name.indexOf('collection') !== -1) {
            return 'future';
        }
        return 'default';
    }

    function applyBounds(input) {
        if (input.readOnly || input.disabled) {
            return;
        }
        var profile = detectProfile(input);
        if (!input.getAttribute('min')) {
            if (profile === 'future') {
                input.min = todayIso();
            } else {
                input.min = DEFAULT_MIN;
            }
        }
        if (!input.getAttribute('max')) {
            if (profile === 'birthday' || profile === 'past') {
                input.max = todayIso();
            } else if (profile === 'future') {
                input.max = DEFAULT_MAX;
            } else {
                input.max = DEFAULT_MAX;
            }
        }
    }

    function isRealCalendarDate(y, m, d) {
        if (y < 1000 || y > 9999) {
            return false;
        }
        var dt = new Date(y, m - 1, d);
        return dt.getFullYear() === y && dt.getMonth() === m - 1 && dt.getDate() === d;
    }

    function validateDateInput(input) {
        var val = input.value;

        if (!val) {
            if (input.required) {
                input.setCustomValidity('Introduce una fecha.');
                return false;
            }
            input.setCustomValidity('');
            return true;
        }

        if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) {
            input.setCustomValidity('Introduce una fecha válida con año de cuatro dígitos.');
            return false;
        }

        var parts = val.split('-').map(function (n) { return parseInt(n, 10); });
        if (!isRealCalendarDate(parts[0], parts[1], parts[2])) {
            input.setCustomValidity('La fecha no existe en el calendario.');
            return false;
        }

        var min = input.getAttribute('min');
        var max = input.getAttribute('max');
        if (min && val < min) {
            input.setCustomValidity('La fecha no puede ser anterior al ' + formatEs(min) + '.');
            return false;
        }
        if (max && val > max) {
            input.setCustomValidity('La fecha no puede ser posterior al ' + formatEs(max) + '.');
            return false;
        }

        input.setCustomValidity('');
        return true;
    }

    function hookFormSubmit(form) {
        if (!form || form.dataset.partilotDateSubmitHook) {
            return;
        }
        form.dataset.partilotDateSubmitHook = '1';
        form.addEventListener('submit', function (e) {
            var dates = form.querySelectorAll('input[type="date"]');
            for (var i = 0; i < dates.length; i++) {
                var input = dates[i];
                if (input.disabled || input.readOnly) {
                    continue;
                }
                if (!validateDateInput(input)) {
                    e.preventDefault();
                    input.reportValidity();
                    input.focus();
                    return;
                }
            }
        });
    }

    function initPartilotDateInput(input) {
        if (!input || input.type !== 'date' || input.dataset.partilotDateInit) {
            return;
        }
        input.dataset.partilotDateInit = '1';
        applyBounds(input);
        validateDateInput(input);

        input.addEventListener('input', function () {
            validateDateInput(input);
        });
        input.addEventListener('change', function () {
            validateDateInput(input);
        });
        input.addEventListener('blur', function () {
            if (input.value && !validateDateInput(input)) {
                input.reportValidity();
            }
        });

        hookFormSubmit(input.closest('form'));
    }

    window.initPartilotDateInputs = function (root) {
        var scope = root || document;
        scope.querySelectorAll('input[type="date"]').forEach(initPartilotDateInput);
    };

    document.addEventListener('DOMContentLoaded', function () {
        initPartilotDateInputs();
    });
})();
