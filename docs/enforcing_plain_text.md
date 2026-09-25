---
id: enforcing_plain_text
title: Enforcing plain-text strings with the fbs API
sidebar_label: Enforcing plain-text strings
---

## TL;DR

* `fbs` is a specialized version of the `fbt` API to represent **only** translatable plain-text strings
   * It's a subset of `fbt` since the latter can also represent rich text contents (like a mix of text and HTML elements)
* Use `fbs` whenever you want to force the translated result to be a plain string.
   * This is typically useful for HTML attributes whose value can only be plain text strings (e.g. `title`, `placeholder`, `alt`, `aria-label`)
* The translation process of `fbs` is the same as with regular fbt strings

## What is it?

* `fbs` represents a translatable plain-text string
   * It's a subset of fbt which can also represent rich text contents (i.e. a mix of text and HTML elements)
* `fbs` means something like "FB string", it's not a true acronym. 😅

## Why using it?

* Enforce plain text only translatable strings, which is useful for:
   * Writing localizable HTML attributes like `title`, `label`, `placeholder`, etc...
      * Why again? Because those HTML attributes only expect a string value; no HTML!
   * Page titles, emails subjects, API responses or any code where you need to enforce those same constraints

## How to use it?

* Use the `fbs()` functional API *(recommended)*
   * You can still use the `<fbs>` HTML API (see the [transform requirements](api_intro.md#html-fbt--api)), but it can't be used inside an HTML attribute value.
* All existing fbt constructs are supported. Just write `fbs` instead of `fbt`.
   * E.g. `<fbt:param>` and `<fbs:param>`, or `fbt::plural()` and `fbs::plural()` work the same way.
   * Use the constructs of the same module, e.g. `fbs::param()` (not `fbt::param()`) within `fbs()`.
   * Options like `desc`, `common`, `project`, ... are supported too. For common strings, the description can be omitted: `fbs('Accept', ['common' => true])`.
* **🚨 IMPORTANT: the values of `fbs:param` / `fbs::param()` (and `fbs::plural()` values) must be strings or `fbs` results, otherwise an exception is thrown.** HTML elements inside `<fbs>` are not allowed either.
* **⚠️ NOTE: like with `fbt`, the parameter values are not escaped!**
* `fbt` and `fbs` can't be nested in each other, but an `fbs` result can be passed as a parameter value to `fbt` or `fbs`.
* How to submit translation requests for it?
   * Please follow the same process as for the regular `fbt` strings

### Examples in HTML

```php
<input
  type="search"
  placeholder="<?=fbs('Search', 'search input placeholder')?>"
  aria-label="<?=fbs('Search', 'search input label')?>">

<fbs desc="some desc">Hello world!</fbs>

<fbs desc="some desc">
  Hello
  <fbs:name name="name" gender="<?=$someGender?>"><?=$name?></fbs:name>
</fbs>
```

### Examples in regular PHP

```php
$myPlainTranslatedText = fbs('Hello world!', 'description');

$myPlainTranslatedText = fbs(
  [
    'I have ',
    \fbt\fbs::plural('a dream', $count, [
      'many' => 'dreams',
      'showCount' => 'yes',
    ]),
    '.',
  ],
  'desc',
);
// singular text = "I have a dream."
// plural text = "I have {number} dreams."

// the string is rendered when the object is converted to a string
$title = (string)$myPlainTranslatedText;
```

### What do `fbs` result values return?

* Upon invoking `fbs()`, you'll receive an `\fbt\fbs` object, which is rendered when it's converted to a string.
* At runtime, the translated result is built by the [`getFbsResult` hook](hooks.md#getfbtresult--getfbsresult), which returns an `FbtPureStringResult` by default.
* `fbs` values can be used in lieu of `fbt` values (e.g. as a `fbt::param()` value)
* `fbt` values CANNOT be used in lieu of `fbs` values (as expected)
* `fbs` strings are never [inlined for translation](inline_translating.md), since they are meant to be used as plain text.
