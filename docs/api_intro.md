---
id: api_intro
title: The FBT API
sidebar_label: Introduction
---
The fbt framework has two (mostly) equivalent APIs: A HTML-style `<fbt>` tag API and a "vanilla" or "functional" `fbt(...)` API that more closely resembles standard PHP.  In general, you can compose your translatable text in either format.  As the following example illustrates, the child of the `<fbt>` tag shows up as the first argument to `fbt` and any attributes show up in the optional third argument parameter.  The `desc` (text description) argument is the exception to this rule because it is a *required* parameter and attribute in `fbt(...)` and `<fbt>` respectively.

Let's start with a simple example:

## HTML `<fbt />` API
**NOTE: You can use this method only if you use the `fbtTransform` + `endFbtTransform` functions or by using `FbtTransform::transform(...)`**
```
<fbt project="foo" desc="a simple example">
  Hello, World!
</fbt>
```
### Required attributes
* `desc`: description of text to be translated

### Optional attributes
* **author** `string`: Text author
* **project** `string`: Project to which the text belongs
* **preserveWhitespace** `bool`: (Default: `false`)
  - FBT normally consolidates whitespace down to one space (`' '`).
  - Turn this off by setting this to `true`
* **subject** `IntlVariations::GENDER_*`: Pass an [implicit subject](implicit_params.md) gender to a partially formed text
* **common** `bool`: Use a "common" string repository
* **doNotExtract** `bool`: Informs [collection](collection.md) to skip this string (useful for tests/mocks)
* **reporting** `bool`: (Default: `true`) Set to `false` to exclude the string from [inline translating](inline_translating.md)

--------------------------------------------------------------------------------

## "Vanilla" `fbt(...)` API

```php
fbt('Hello, World', 'a simple example', ['project' => "foo"])
```
#### Required arguments
1. Text to translate
2. Description of text to be translated

#### Optional parameters
3. Options object - same optional arguments as the `<fbt>` [attributes above](api_intro.md#optional-attributes)

--------------------------------------------------------------------------------
## Docblock defaults
Defaults for the above optional attributes may be provided in the
docblock with the `@fbt` pragma.  It uses a straight `json_decode` to
interpret this, so you'll have to make sure your object is parseable. (i.e. keys should be wrapped in `"double quotes"`)

E.g.
```php
<?php
/**
 * @fbt {"author": "me", "project": "awesome sauce"}
 */
```

--------------------------------------------------------------------------------

## Can I enforce `fbt` strings to be rendered only as plain-text?

Yes, please use the [`fbs` API as per this documentation.](enforcing_plain_text.md)

--------------------------------------------------------------------------------

## Custom Fbt API attributes

The Fbt library supports the ability to define custom attributes/options that enable developers to customize how to render fbt result strings at runtime.

**NOTE: these options do not have any effect on how we extract fbt strings.**

To configure this, define the extra options in the `extraOptions` config option.
Each option maps either to `true` (any string value is accepted) or to an array of allowed values:

```php
\fbt\FbtConfig::set('extraOptions', [
    'aStringOption' => true,
    'aStringEnumOption' => [
        'yes' => true,
        'no' => true,
    ],
]);
```

Then, you can use these new options in your code as follows:

```php
<fbt desc="..."
  aStringOption="any text"
  aStringEnumOption="yes">
  ...
</fbt>

<?=fbt('...', '...', [
    'aStringOption' => 'any text',
    'aStringEnumOption' => 'yes',
])?>
```

Extra option values must be strings. Using an option that isn't configured, or a value that isn't in
the allowed list (e.g. `aStringEnumOption="maybe"`), throws an exception.

Eventually, these option values will be exposed to the [`getFbtResult` / `getFbsResult` hooks](hooks.md#getfbtresult--getfbsresult):

```php
use fbt\Runtime\Shared\FbtHooks;
use fbt\Runtime\Shared\FbtResult;

FbtHooks::register('getFbtResult', function (array $input) {
    $result = (string)FbtResult::get($input);

    if (($input['extraOptions']['aStringEnumOption'] ?? null) === 'yes') {
        return "[$result]";
    }

    return $result;
});
```
