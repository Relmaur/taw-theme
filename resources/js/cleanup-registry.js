// A page-transition-library-agnostic cleanup registry for block scripts
// that bind JS to DOM and need to tear that binding down before a
// transition library (Swup, Barba, Taxi, the native View Transitions API
// — see "Using JavaScript View Transition Libraries" in README.md) swaps
// out the container that DOM lives in. The motivating case: an Embla or
// Splide carousel instance leaks its ResizeObserver and event listeners
// across every navigation unless something calls .destroy() on it first.
//
// This is the same window._tawCleanup Set pattern the README's Sample 1
// has documented since the first Swup recipe landed here, now a real,
// importable file instead of something every site re-declares by hand —
// less to drift from the documented pattern, not a new mechanism.
//
// Registering here has zero cost on a site that never wires flushCleanup()
// into anything — a callback just sits in the registry, inert, until the
// page unloads. This file does not import or reference Swup (or any
// transition library) itself; wiring flushCleanup() into one is a
// per-site decision made in app.js, not something this module does on
// its own. See README.md's "Using JavaScript View Transition Libraries"
// section for the full worked example.
//
// Usage in a block script (Blocks/Gallery/script.js):
//
//   import { registerCleanup } from '../../resources/js/cleanup-registry.js';
//
//   const embla = EmblaCarousel(root, { loop: true });
//   registerCleanup(() => embla.destroy());
//
// Usage in app.js, in whichever transition library's before-swap hook:
//
//   import { flushCleanup } from './cleanup-registry.js';
//
//   swup.hooks.before('content:replace', flushCleanup);

const cleanupFns = new Set();

/**
 * Register a teardown callback for one specific stateful instance (e.g.
 * one Embla carousel). Call this once per instance, right after creating
 * it — not once per block *type*, since each navigation that re-inits a
 * block creates a new instance needing its own new teardown closure.
 */
export function registerCleanup(fn) {
    cleanupFns.add(fn);
}

/**
 * Run every registered teardown callback and clear the registry. Called
 * by a site's transition-library integration right before it swaps out
 * the DOM those callbacks' instances live in — never by a block script.
 */
export function flushCleanup() {
    cleanupFns.forEach((fn) => fn());
    cleanupFns.clear();
}
