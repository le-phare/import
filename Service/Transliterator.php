<?php

namespace LePhare\Import\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

class Transliterator
{
    /**
     * Generates a slug of the text.
     *
     * Does not transliterate correctly eastern languages.
     */
    public static function urlize(string $text, string $separator = '-'): string
    {
        $slugger = new AsciiSlugger();
        $slugged = $slugger->slug($text, $separator)
            ->lower()
            ->trim()
            ->toString()
        ;

        return self::postProcessText($slugged, $separator);
    }

    /**
     * Cleans up the text and adds separator.
     */
    private static function postProcessText(string $text, string $separator): string
    {
        // Remove apostrophes which are not used as quotes around a string
        $text = preg_replace('/(\w)\'(\w)/', '${1}${2}', $text);

        // Replace all none word characters with a space
        $text = preg_replace('/\W/', ' ', $text);

        // More stripping. Replace spaces with dashes
        return strtolower(preg_replace('/[^A-Za-z0-9\/]+/', $separator,
            preg_replace('/([a-z\d])([A-Z])/', '\1_\2',
                preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1_\2',
                    preg_replace('/::/', '/', $text)))));
    }
}
