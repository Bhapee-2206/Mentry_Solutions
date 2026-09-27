/**
 * assets/js/push-notifications.js - Retired Push Notification Stub
 * Push notifications have been decommissioned in favor of In-App Notifications.
 */
(function() {
    'use strict';
    window.__MENTRY_PUSH_JS_STARTED__ = true;
    window.MentryPush = {
        enable: async function() { return false; },
        checkStatus: async function() { return 'UNSUPPORTED'; },
        resetSubscription: async function() { return true; }
    };
})();
