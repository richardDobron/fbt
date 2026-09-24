<?php

namespace fbt\Lib;

class IntlRedundantStops
{
    public const EQUIVALENCIES = [
        "." => [
            "\u{0964}",
            "\u{104B}",
            "\u{3002}",
        ],
        "\u{2026}" => [
            "\u{0E2F}",
            "\u{0EAF}",
            "\u{1801}",
        ],
        "!" => [
            "\u{FF01}",
        ],
        "?" => [
            "\u{FF1F}",
        ],
    ];

    public const REDUNDANCIES = [
        "?" => [
            "?",
            ".",
            "!",
            "\u{2026}",
        ],
        "!" => [
            "!",
            "?",
            ".",
        ],
        "." => [
            ".",
            "!",
        ],
        "\u{2026}" => [
            "\u{2026}",
            ".",
            "!",
        ],
    ];
}
