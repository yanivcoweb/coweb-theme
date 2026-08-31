/**
 * Mobile menu.
 *
 * The only script the theme ships. No framework, no dependencies, and nothing
 * runs until the toggle exists — the desktop header needs none of this.
 *
 * Accessibility contract:
 *   - aria-expanded on the toggle always reflects reality
 *   - Escape closes and returns focus to the toggle
 *   - focus is trapped inside the drawer while it is open
 *   - the drawer is visibility:hidden when closed, so it leaves the tab order
 */
(function () {
	"use strict";

	var nav = document.getElementById("site-nav");
	var openBtn = document.querySelector("[data-menu-open]");
	var closeBtn = document.querySelector("[data-menu-close]");

	if (!nav || !openBtn) {
		return;
	}

	var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

	function focusable() {
		return Array.prototype.filter.call(
			nav.querySelectorAll(FOCUSABLE),
			function (el) {
				return el.offsetParent !== null;
			}
		);
	}

	function isOpen() {
		return nav.hasAttribute("data-open");
	}

	function open() {
		nav.setAttribute("data-open", "");
		openBtn.setAttribute("aria-expanded", "true");
		document.body.style.overflow = "hidden";

		// Wait for the drawer to become visible before moving focus into it.
		window.requestAnimationFrame(function () {
			var first = focusable()[0];
			if (first) {
				first.focus();
			}
		});
	}

	function close(returnFocus) {
		nav.removeAttribute("data-open");
		openBtn.setAttribute("aria-expanded", "false");
		document.body.style.overflow = "";

		if (returnFocus !== false) {
			openBtn.focus();
		}
	}

	function trap(event) {
		if (event.key !== "Tab") {
			return;
		}

		var items = focusable();
		if (items.length === 0) {
			return;
		}

		var first = items[0];
		var last = items[items.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	openBtn.addEventListener("click", open);

	if (closeBtn) {
		closeBtn.addEventListener("click", function () {
			close(true);
		});
	}

	document.addEventListener("keydown", function (event) {
		if (!isOpen()) {
			return;
		}

		if (event.key === "Escape") {
			close(true);
			return;
		}

		trap(event);
	});

	// Following a link inside the drawer should not leave it open behind the
	// next page paint, and on same-page anchors it would stay open entirely.
	nav.addEventListener("click", function (event) {
		if (event.target.closest("a") && isOpen()) {
			close(false);
		}
	});

	// If the viewport grows past the breakpoint while the drawer is open, the
	// nav becomes inline again and the trapped focus would be nonsense.
	var desktop = window.matchMedia("(min-width: 48rem)");
	var onChange = function (event) {
		if (event.matches && isOpen()) {
			close(false);
		}
	};

	if (typeof desktop.addEventListener === "function") {
		desktop.addEventListener("change", onChange);
	} else if (typeof desktop.addListener === "function") {
		desktop.addListener(onChange);
	}
})();
