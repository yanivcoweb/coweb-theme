/**
 * In-article table of contents.
 *
 * Headings are collected from the rendered h2 nodes rather than authored
 * separately — an authored list drifts the moment someone edits a heading.
 *
 * The active item is driven by an IntersectionObserver, not a scroll handler,
 * so nothing runs on the main thread while the reader scrolls.
 *
 * Progressive enhancement: the nav is empty and hidden in the markup. If this
 * script never runs, there is no broken widget — there is nothing at all.
 */
(function () {
	"use strict";

	var toc = document.querySelector("[data-toc]");
	var body = document.querySelector("[data-toc-source]");

	if (!toc || !body) {
		return;
	}

	var headings = Array.prototype.slice.call(body.querySelectorAll("h2"));

	// One heading is a list of one. Not worth the furniture.
	if (headings.length < 2) {
		return;
	}

	var list = toc.querySelector("[data-toc-list]");
	if (!list) {
		return;
	}

	var links = {};

	headings.forEach(function (heading, index) {
		if (!heading.id) {
			heading.id = "section-" + (index + 1);
		}

		var item = document.createElement("li");
		var link = document.createElement("a");

		link.className = "article-toc__link";
		link.href = "#" + heading.id;
		link.textContent = heading.textContent;

		item.appendChild(link);
		list.appendChild(item);

		links[heading.id] = link;
	});

	toc.hidden = false;

	if (!("IntersectionObserver" in window)) {
		return;
	}

	var active = null;

	function setActive(id) {
		if (id === active) {
			return;
		}

		if (active && links[active]) {
			links[active].removeAttribute("aria-current");
		}

		active = id;

		if (active && links[active]) {
			links[active].setAttribute("aria-current", "true");
		}
	}

	var observer = new IntersectionObserver(
		function (entries) {
			// Pick the heading nearest the top of the viewport that is above
			// the fold line, rather than reacting to whichever entry fired.
			var visible = headings.filter(function (heading) {
				var box = heading.getBoundingClientRect();
				return box.top <= window.innerHeight * 0.3;
			});

			if (visible.length) {
				setActive(visible[visible.length - 1].id);
			} else {
				setActive(headings[0].id);
			}
		},
		{
			rootMargin: "-30% 0px -60% 0px",
			threshold: 0
		}
	);

	headings.forEach(function (heading) {
		observer.observe(heading);
	});
})();
