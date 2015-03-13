<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Validator.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Provides data validation.
 */
class Validator
{

    /**
     * Validates a URL.
     *
     * @param string $url
     *
     * @return string
     *   The valid URL. It may differ from the $url parameter.
     */
    public static function validateUrl($url)
    {
        $url_parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            if (isset($url_parts['host'])) {
                return false;
            } else {
                $url = 'http://' . $url;

                return static::validateUrl($url);
            }
        }

        return $url;
    }

}
