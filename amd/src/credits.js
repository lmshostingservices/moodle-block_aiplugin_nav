/**
 * Async credits loader for AI Dashboard Quick Links block.
 * Fetches credit balance after page load so the block renders instantly.
 *
 * @module     block_aiplugin_nav/credits
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function (Ajax) {

    /**
     * Return CSS color class for a given credit amount.
     *
     * @param {string} credits
     * @return {string}
     */
    function colorClass(credits) {
        if (credits === 'unlimited' || credits === '-1') {
            return 'ainav-credits-green';
        }
        var amount = parseInt(credits, 10);
        if (amount < 100) {
            return 'ainav-credits-red';
        } else if (amount < 1000) {
            return 'ainav-credits-orange';
        }
        return 'ainav-credits-green';
    }

    /**
     * Format credit number for display.
     *
     * @param {string} credits
     * @return {string}
     */
    function displayValue(credits) {
        if (credits === 'unlimited' || credits === '-1') {
            return 'Unlimited';
        }
        return parseInt(credits, 10).toLocaleString();
    }

    /**
     * Update the credits placeholder elements in the DOM.
     *
     * @param {string} credits
     */
    function updateDOM(credits) {
        var cls  = colorClass(credits);
        var text = displayValue(credits);

        // Top bar display.
        var display = document.getElementById('ainav-credits-placeholder');
        if (display) {
            var span = display.querySelector('.ainav-credits-total');
            if (span) {
                span.className   = 'ainav-credits-total ' + cls;
                span.textContent = text + ' credits';
            }
            display.style.display = '';
        }

        // Badge next to the Buy Credits link.
        document.querySelectorAll('.ainav-credits-badge').forEach(function (badge) {
            badge.className   = 'ainav-credits-badge ' + cls;
            badge.textContent = text;
            badge.style.display = '';
        });
    }

    return {
        /**
         * Initialise: fetch credits from the server asynchronously.
         */
        init: function () {
            Ajax.call([{
                methodname: 'block_aiplugin_nav_get_credits',
                args: {},
                done: function (response) {
                    if (response.success && response.credits !== '') {
                        updateDOM(response.credits);
                    }
                },
                fail: function () {
                    // Silent fail  -  credits display simply stays hidden.
                }
            }]);
        }
    };
});
