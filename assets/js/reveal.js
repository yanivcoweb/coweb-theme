/**
 * Section reveal on scroll.
 *
 * The behaviour map on the Figma `Motion` page defines exactly one entry for
 * this:
 *
 *   section reveal    opacity, translateY(8px)    slow    entrance
 *
 * plus three rules that shape the implementation more than the values do:
 *
 *   - it runs once per element, never again on the way back up
 *   - items in the same row step by 60ms
 *   - under reduced motion it does not run at all — the content is simply
 *     there, which is different from running instantly
 *
 * "Section reveal" names the trigger, not the animated element: the section is
 * what enters the viewport, and what moves is the content inside it. Animating
 * the section as a block and its rows as well would compound the two 8px
 * offsets into 16px.
 *
 * Nothing is hidden until this script says so — `data-reveal-item` is what the
 * stylesheet keys the initial state off, and it is only ever added to a
 * section the observer has just reported as *outside* the viewport. Without
 * JavaScript, with it broken, or on anything already on screen, every section
 * renders at rest.
 *
 * That ordering is deliberate and was arrived at the hard way: deciding what
 * to hide by reading `window.innerHeight` up front looks equivalent and is
 * not. Any environment that reports a zero height — an offscreen or
 * zero-sized frame, some headless harnesses — hides the whole page and never
 * reveals it, because the observer that would undo it never fires either. The
 * observer's own first report is the only source that cannot disagree with
 * what the observer will do next.
 */
(function () {
	"use strict";

	var STEP_MS = 60;

	// Off entirely, not instant. Arming and then zeroing the duration would
	// still hide anything the observer never reaches.
	if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
		return;
	}

	if (!("IntersectionObserver" in window)) {
		return;
	}

	var main = document.querySelector(".site-main");

	if (!main) {
		return;
	}

	/**
	 * How many columns a grid is actually rendering. Read from the computed
	 * style rather than counted from the markup, because the count changes at
	 * every breakpoint and only the browser knows which one applies.
	 */
	function columnCount(el) {
		var tracks = window.getComputedStyle(el).gridTemplateColumns;

		if (!tracks || tracks === "none") {
			return 1;
		}

		return tracks.split(" ").filter(Boolean).length;
	}

	/**
	 * The pieces of a section that move, in order, each tagged with its
	 * position in its own row.
	 *
	 * A section is a root element wrapping one inner container; its children
	 * are the parts an editor would recognise — a header, then a list. Where
	 * one of those parts is a grid, the grid's items move instead of the grid,
	 * so a row lands card by card.
	 */
	function itemsOf(section) {
		var inner = section.firstElementChild;
		var parts = inner ? Array.prototype.slice.call(inner.children) : [section];
		var items = [];

		parts.forEach(function (part) {
			var isGrid = window.getComputedStyle(part).display === "grid";
			var children = Array.prototype.slice.call(part.children);

			if (!isGrid || children.length < 2) {
				items.push({ el: part, step: 0 });
				return;
			}

			var columns = columnCount(part);

			children.forEach(function (child, i) {
				// Every row restarts at the start edge, so every row restarts
				// its delay. Row two should not begin where row one ended.
				items.push({ el: child, step: 1 + (i % columns) });
			});
		});

		return items;
	}

	// Resolved once, up front, so the observer callback never reads layout —
	// getComputedStyle inside it would run mid-scroll.
	var groups = [];

	function groupFor(section) {
		for (var i = 0; i < groups.length; i++) {
			if (groups[i].section === section) {
				return groups[i];
			}
		}

		return null;
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			var group = groupFor(entry.target);

			if (!group) {
				return;
			}

			if (!entry.isIntersecting) {
				// Off screen, so hiding it now is invisible. This is the only
				// place anything is ever hidden.
				if (!group.hidden) {
					group.hidden = true;

					group.items.forEach(function (item) {
						item.el.style.setProperty("--reveal-delay", item.step * STEP_MS + "ms");
						item.el.setAttribute("data-reveal-item", "");
					});
				}

				return;
			}

			// Once. Unobserving is what makes that true, not a flag checked on
			// the way back up.
			observer.unobserve(entry.target);

			// Already on screen and never hidden — the visitor is looking at
			// it. There is nothing to reveal, and this is why the hero does
			// not fade in on load.
			if (!group.hidden) {
				return;
			}

			group.items.forEach(function (item) {
				item.el.setAttribute("data-revealed", "");
			});
		});
	}, {
		// No rootMargin. A negative bottom margin would report an element in
		// the last strip of the viewport as "not intersecting" while it is
		// plainly visible, and this script hides exactly what that report
		// calls invisible.
		threshold: 0
	});

	Array.prototype.forEach.call(main.children, function (section) {
		groups.push({ section: section, items: itemsOf(section), hidden: false });
		observer.observe(section);
	});
})();
