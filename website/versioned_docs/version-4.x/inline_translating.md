---
id: inline_translating
title: Inline Translating
sidebar_label: Inline Translating
---

This framework supports inline translation mode, which means that you can translate anywhere in the application so that you can view strings you are translating in context.
If you right-click on the underlined string, the translation dialog appears and you will be able to vote for the available translations or submit a new one.

## Preview

![Demo of FBT inline translating](https://raw.githubusercontent.com/richardDobron/fbt/main/docs/inline_translating.gif)

## Installing

For installation, please follow these [instructions](https://github.com/swiftyper-sk/fbt-inline-translations#non-react-usage).

## How it works?
```php
// to turn on inline translations
FbtHooks::inlineMode('TRANSLATE');

// to turn off inline translations
FbtHooks::inlineMode('NO_INLINE');
```

## Excluded translations
If you need to turn off inline mode for specific phrases, you can use option `reporting`:

```php
<title>
    <?=fbt('Account Settings', 'page title', ['reporting' => false])?>
</title>
```

or using `canInline` hook:

```php
FbtHooks::register('canInline', function ($backtrace) {
    foreach ($backtrace as $call) {
        if ($call['function'] === 'fbt_raw') {
            return false;
        }
    }

    return true;
});

function fbt_raw(string | fbt\fbt $text): string
{
    return (string)$text;
}

$title = fbt('Account Settings', 'page title');

echo fbt_raw($title);
```
