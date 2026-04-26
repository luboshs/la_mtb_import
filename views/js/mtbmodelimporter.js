/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    luboshs
 * @copyright since 2026 luboshs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */

(function () {
    'use strict';

    /**
     * Confirm before clearing log entries.
     */
    function initLogClearConfirm() {
        var clearButtons = document.querySelectorAll('[data-confirm-clear]');

        clearButtons.forEach(function (button) {
            button.addEventListener('click', function (e) {
                if (!window.confirm(button.getAttribute('data-confirm-clear'))) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Auto-hide success/confirmation alert panels after 5 seconds.
     */
    function initAutoHideAlerts() {
        var alerts = document.querySelectorAll('.alert-success, .alert-info');

        alerts.forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function () {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            }, 5000);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLogClearConfirm();
        initAutoHideAlerts();
    });
}());
