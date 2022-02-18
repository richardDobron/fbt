# The FBT API

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
