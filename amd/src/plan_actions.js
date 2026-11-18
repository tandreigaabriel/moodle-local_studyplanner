// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Handles mark complete / skip / reschedule button clicks on the study planner pages.
 *
 * @module     local_studyplanner/plan_actions
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /**
     * Maps a button's data-action value to its web service method name.
     *
     * @param {string} action The data-action attribute value.
     * @returns {string} The web service method name, or an empty string if unknown.
     */
    function methodFor(action) {
        var methods = {
            complete: 'local_studyplanner_mark_complete',
            skip: 'local_studyplanner_skip',
            reschedule: 'local_studyplanner_reschedule',
        };

        return methods[action] || '';
    }

    /**
     * Sends the action request for one plan item and refreshes the page on success.
     *
     * @param {string} action The action to perform.
     * @param {number} itemid The plan item id.
     * @returns {void}
     */
    function performAction(action, itemid) {
        var methodname = methodFor(action);
        if (!methodname) {
            return;
        }

        Ajax.call([{
            methodname: methodname,
            args: {itemid: itemid},
        }])[0].then(function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                Notification.addNotification({message: response.message, type: 'warning'});
            }
            return response;
        }).catch(Notification.exception);
    }

    /**
     * Binds click handlers to every plan item action button currently on the page.
     *
     * @returns {void}
     */
    function bindButtons() {
        document.querySelectorAll('[data-action][data-itemid]').forEach(function(button) {
            button.addEventListener('click', function() {
                var action = button.getAttribute('data-action');
                var itemid = parseInt(button.getAttribute('data-itemid'), 10);
                performAction(action, itemid);
            });
        });
    }

    return {
        /**
         * Initialises the plan actions module by binding all action buttons on the page.
         *
         * @returns {void}
         */
        init: function() {
            bindButtons();
        },
    };
});
