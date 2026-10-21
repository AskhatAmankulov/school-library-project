<?php
// Every form on this site sends plain text fields. A field sent as an array
// (e.g. "title[]=x", only possible with a hand-made request) would crash
// string functions like trim(), so arrays are replaced with an empty string.
if (!defined('INPUT_CLEANED')) {
    define('INPUT_CLEANED', true);
    foreach ($_GET as $k => $v) {
        if (is_array($v)) {
            $_GET[$k] = '';
        }
    }
    foreach ($_POST as $k => $v) {
        if (is_array($v)) {
            $_POST[$k] = '';
        }
    }
}

// Returns a message like 'Title is too long (255 characters at most).' for the
// first field that does not fit its database column, or null if all fit.
// Usage: length_error(['Title' => [$title, 255], 'ISBN' => [$isbn, 20]])
if (!function_exists('length_error')) {
    function length_error(array $fields) {
        foreach ($fields as $label => [$value, $max]) {
            if ($value !== null && mb_strlen((string)$value) > $max) {
                return $label . ' is too long (' . $max . ' characters at most).';
            }
        }
        return null;
    }
}
