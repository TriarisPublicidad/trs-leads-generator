/**
 * TRS Leads Generator - First-Party UTM Tracking
 * Captures UTM parameters from URL and persists them in cookies for 30 days.
 * Automatically injects them into TRS lead generation forms.
 */
(function () {
	'use strict';

	var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
	var COOKIE_PREFIX = 'trs_';
	var COOKIE_DAYS = 30;

	/**
	 * Set a cookie with specified name, value and expiration.
	 */
	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var date = new Date();
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = '; expires=' + date.toUTCString();
		}
		var secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value || '') + expires + '; path=/; SameSite=Lax' + secure;
	}

	/**
	 * Retrieve a cookie value by name.
	 */
	function getCookie(name) {
		var nameEQ = encodeURIComponent(name) + '=';
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) === ' ') {
				c = c.substring(1, c.length);
			}
			if (c.indexOf(nameEQ) === 0) {
				return decodeURIComponent(c.substring(nameEQ.length, c.length));
			}
		}
		return null;
	}

	/**
	 * Extract UTMs from current URL and store in first-party cookies.
	 */
	function captureAndStoreUtms() {
		if (!window.location.search) {
			return;
		}

		var params = new URLSearchParams(window.location.search);
		UTM_KEYS.forEach(function (key) {
			if (params.has(key)) {
				var val = params.get(key);
				if (val && val.trim() !== '') {
					setCookie(COOKIE_PREFIX + key, val.trim(), COOKIE_DAYS);
				}
			}
		});
	}

	/**
	 * Populate hidden UTM fields in all forms on the page.
	 */
	function populateUtmFields(context) {
		var root = context || document;
		var forms = root.querySelectorAll('form.trs-lead-form');

		forms.forEach(function (form) {
			UTM_KEYS.forEach(function (key) {
				var input = form.querySelector('input[name="' + key + '"]');
				if (input) {
					// Check URL first, then fallback to stored cookie.
					var params = new URLSearchParams(window.location.search);
					var val = params.get(key) || getCookie(COOKIE_PREFIX + key) || '';
					if (val) {
						input.value = val;
					}
				}
			});
		});
	}

	// 1. Capture on execution.
	captureAndStoreUtms();

	// 2. Populate inputs when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			populateUtmFields();
		});
	} else {
		populateUtmFields();
	}

	// 3. Observe mutations in case forms are loaded dynamically (e.g. modals/AJAX).
	if ('MutationObserver' in window) {
		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.addedNodes && mutation.addedNodes.length > 0) {
					for (var i = 0; i < mutation.addedNodes.length; i++) {
						var node = mutation.addedNodes[i];
						if (node.nodeType === 1) { // ELEMENT_NODE
							if (node.matches && node.matches('form.trs-lead-form')) {
								populateUtmFields(node.parentNode || document);
							} else if (node.querySelector && node.querySelector('form.trs-lead-form')) {
								populateUtmFields(node);
							}
						}
					}
				}
			});
		});

		observer.observe(document.body || document.documentElement, {
			childList: true,
			subtree: true
		});
	}
})();

