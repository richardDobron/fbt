---
id: locale_detection
title: Locale detection
sidebar_label: Locale detection
---

# How to detect the user's locale from the browser?

## Installation
To implement locale detection in your PHP project, you can use the `HTTP2` library. You can install it via Composer:

```shell
composer require pear/http2
```

## Usage
After installing the `HTTP2 library`, you can utilize it in your PHP code to detect the user's locale. Here's a basic example:

```php
<?php
$http = new \HTTP2();

// List of supported languages
$supportedLanguages = [
    'en'    => 'en_US',
    'en-UK' => 'en_UK',
    'de'    => 'de_DE',
    'de-AT' => 'de_AT',
    'cs'    => 'cs_CZ',
    'sk'    => 'sk_SK',
];

// Detect the user's language
$clientLanguage = $http->negotiateLanguage($supportedLanguages, $fallbackLanguage = 'en');

// Get the locale for the detected language
$locale = $supportedLanguages[$clientLanguage];

// Set the detected locale
\fbt\FbtConfig::set('locale', $locale);
```

## Explanation

### HTTP Accept-Language Header
The `negotiateLanguage()` method works by parsing the HTTP `Accept-Language` header sent by the user's browser. This header contains information about the user's preferred languages in order of priority. The method then matches these languages against the provided list and returns the best match.

### Fallback Locale
In case the user's preferred language is not available or cannot be determined, it's essential to have a fallback locale. This ensures that your application always defaults to a suitable language/locale even when the detection process fails.
