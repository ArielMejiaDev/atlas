<?php

namespace ArielMejiaDev\Atlas\Support;

class Normalizer
{
    public function normalize(string $s): string
    {
        if ($s === '') {
            return '';
        }

        if (extension_loaded('intl') && class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');

            if ($transliterator !== null) {
                $s = $transliterator->transliterate($s) ?: mb_strtolower($s, 'UTF-8');
            } else {
                $s = $this->fallbackNormalize($s);
            }
        } else {
            $s = $this->fallbackNormalize($s);
        }

        // Replace every non-alphanumeric run with a single space
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    private function fallbackNormalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');

        return $this->stripDiacritics($s);
    }

    private function stripDiacritics(string $s): string
    {
        // NFKD decomposition if intl is available for Normalizer class
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($s, \Normalizer::FORM_KD);
            if ($decomposed !== false) {
                // Remove combining marks (Unicode category M)
                return preg_replace('/\p{M}+/u', '', $decomposed) ?? $s;
            }
        }

        // Last resort: manual transliteration map
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y',
            'ß' => 'ss', 'ă' => 'a', 'ą' => 'a', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd',
            'ę' => 'e', 'ě' => 'e', 'ğ' => 'g', 'ı' => 'i', 'ł' => 'l', 'ń' => 'n', 'ň' => 'n',
            'ő' => 'o', 'ř' => 'r', 'ś' => 's', 'ş' => 's', 'š' => 's', 'ť' => 't', 'ů' => 'u',
            'ű' => 'u', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        ];

        return strtr($s, $map);
    }
}
