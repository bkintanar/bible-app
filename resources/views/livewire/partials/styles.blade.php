{{-- Theme and text size are applied in <head> (partials/dark-mode-head). --}}

<!-- Page Peel Effect Styles -->
<style>
    [x-cloak] { display: none !important; }

    /* Reader text size, set by A-/A+ (html[data-font-size], localStorage) - no server round-trip.
       Sizes match the old server-side classes: sm=text-lg, base=text-xl ... 2xl=text-4xl. */
    .reader-text { font-size: 1.25rem; }
    html[data-font-size="sm"] .reader-text { font-size: 1.125rem; }
    html[data-font-size="lg"] .reader-text { font-size: 1.5rem; }
    html[data-font-size="xl"] .reader-text { font-size: 1.875rem; }
    html[data-font-size="2xl"] .reader-text { font-size: 2.25rem; }

    .book-container {
        perspective: 1000px;
        transform-style: preserve-3d;
    }

    .page {
        transform-style: preserve-3d;
    }

    /* Book spine shadow for visual depth */
    .book-spine-shadow {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to right,
            rgba(0,0,0,0.15) 0%,
            rgba(0,0,0,0.05) 50%,
            transparent 100%);
        pointer-events: none;
    }

    /* Strong's Number and Morphology Styling */
    .strong-word {
        cursor: help;
        position: relative;
        transition: all 0.2s ease;
    }

    .strong-word.has-strongs {
        border-bottom: 1px dotted rgba(59, 130, 246, 0.5);
    }

    .strong-word.has-strongs:hover {
        background-color: rgba(59, 130, 246, 0.1);
        border-bottom: 1px solid rgba(59, 130, 246, 0.8);
    }

    .dark .strong-word.has-strongs:hover {
        background-color: rgba(59, 130, 246, 0.15);
    }

    .strong-word.hebrew-word.has-strongs {
        border-bottom-color: rgba(34, 197, 94, 0.5);
    }

    .strong-word.hebrew-word.has-strongs:hover {
        background-color: rgba(34, 197, 94, 0.1);
        border-bottom-color: rgba(34, 197, 94, 0.8);
    }

    .dark .strong-word.hebrew-word.has-strongs:hover {
        background-color: rgba(34, 197, 94, 0.15);
    }

    .strong-word.greek-word.has-strongs {
        border-bottom-color: rgba(168, 85, 247, 0.5);
    }

    .strong-word.greek-word.has-strongs:hover {
        background-color: rgba(168, 85, 247, 0.1);
        border-bottom-color: rgba(168, 85, 247, 0.8);
    }

    .dark .strong-word.greek-word.has-strongs:hover {
        background-color: rgba(168, 85, 247, 0.15);
    }

    /* No styling for .has-morph: in the KJV, italics mean words SUPPLIED by the translators
       (<transChange type="added">). Italicising every morphology-tagged word made the default
       view claim translators added words like "created" in Gen 1:1. */

    /* Supplied (italic) words inside Christ's words stay red, as in red-letter KJV editions;
       the generic translator-change colour otherwise turned them grey mid-sentence (Matt 5:3 "are"). */
    .text-red-600 em,
    .text-red-600 .italic {
        color: inherit;
    }

    /* Tooltip styling for Strong's numbers */
    .strong-word[title]:hover::after {
        content: attr(title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background-color: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        z-index: 1000;
        pointer-events: none;
    }

    .dark .strong-word[title]:hover::after {
        background-color: rgba(255, 255, 255, 0.9);
        color: black;
    }
</style>
