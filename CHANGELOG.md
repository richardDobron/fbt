# Changelog

All notable changes to `fbt` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## v5.0.0 - Unreleased
See [UPGRADE-5.0.md](UPGRADE-5.0.md).
### Changed
- The compiler is a port of [babel-plugin-fbt v1.0.0](https://github.com/facebook/fbt/tree/babel-plugin-fbt-v1.0.0) (fbt nodes)
- Runtime (`fbt::_()`, `fbs::_()`, token substitution, result objects, hooks) is aligned 1:1 with the JavaScript runtime of [fbt v1.0.0](https://github.com/facebook/fbt/tree/fbt-v1.0.0)
- Collected phrases use the format of Facebook's fbt (`hashToLeaf`, `jsfbt` leaves with `desc` and `tokenAliases`, location of the phrase)
- Inner strings refer to token aliases at runtime (`{=m1}`), and their descriptions contain the whole enclosing string (e.g. `In the phrase: "{name1} {=poked you}."`)
- Hash keys of translated payloads are computed from the texts and token aliases of all leaves (unchanged for strings without inner strings)
- md5 hashes of source strings are encoded in `base64` instead of `hex` by default (set `md5_digest` to `hex` to keep the old keys)
- Plurals, enums and pronouns are no longer deduplicated by value (use the `key` option)
- Empty HTML elements are kept as a part of the text
- `TranslationBuilder`, `FbtSite`, `TranslationConfig` and `TranslationData` are ported from Facebook's fbt v1.0.0
- Redundant punctuation after a parameter is only removed when it is really redundant (e.g. `T.J.?` is kept, `Chess!!` becomes `Chess!`)
- A substitution token registered more than once (e.g. `fbt:name` and `fbt:param` with the same name) now throws an exception
- `fbs()` returns `FbtPureStringResult` objects and is never inlined in inline translation mode
- Numbers are formatted exactly like in JavaScript (precision is not limited by the `precision` ini setting, halves are rounded up like `Math.round`)
- Direct runtime calls: boolean parameter values are rendered as `true` / `false`, and `fbs::_param()` / `fbs::_plural()` only accept strings or `FbtPureStringResult` values
- Rendered strings are not cached in inline translation mode
- `IntlPunctuation::endsInPunct()` is deprecated
- `FbtHooks::getIntlViewerContext()` is renamed to `FbtHooks::getViewerContext()` (like the hook)
- `IntlList` is a function like `intlList` of Facebook's fbt (`IntlList::intlList($items, $conjunction, $delimiter)`, the constructor and `format()` are removed), `intlNumUtils::escapeRegex()` is moved to `escapeRegex::escapeRegex()`, and the duplicate `InlineFbtResult::$contents` is removed
- The runtime constants of `IntlVariations` (`BITMASK_*`, `NUMBER_*`, `GENDER_*`) are moved to `fbt\Lib\IntlVariations` (the runtime `IntlVariations` of Facebook's fbt), and runtime error messages don't depend on the transform (`FbtUtils::describe()`)
- The runtime uses `GenderConst` (like Facebook's fbt), and `FbtTableAccessor::getGenderResult()` / `getNumberResult()` receive the gender / number value
- `intlInlineMode.css` (styles of inline translation mode) is shipped in `src/fbt/Runtime/`
- `FbtUtils::substituteTokens()` (use `substituteTokens::substitute()`), `FbtUtils::getVariationValue()` and `FbtConstants::PRONOUN_USAGE` (use `FbtConstants::VALID_PRONOUN_USAGES`) are removed
- `Gender::GENDER_CONST['MIXED_SINGULAR']` and `Gender::GENDER_CONST['MIXED_PLURAL']` (removed in v4.4.0) are restored as aliases of `MIXED_UNKNOWN`
- fbt constructs (`fbt::param()`, `fbt::plural()`, ...) return `FbtCallExpression` objects (the equivalent of the babel call expressions of Facebook's fbt) instead of markup, and fbt() callsites are compiled from them directly (like `FbtFunctionCallProcessor` of Facebook's fbt), once for all of their runtime values (`FbtExpression`)
- `fbt::c()` is processed by `FbtCommonFunctionCallProcessor` (port of Facebook's fbt)
- The runtime tables of compiled callsites are computed once (instead of on every render)
- Values of `fbt::param()` (with the `number` option) and `fbt::plural()` are formatted only when they are numbers (like Facebook's fbt), e.g. the string `'12345'` isn't rendered as `12,345` (numeric values of HTML constructs are still numbers)
- Phonological rules are applied to strings without parameters too (e.g. Turkish apostrophes)
- `<fbt desc>` without a value throws an exception (like Facebook's fbt)
- `generate-translations` writes a translation group per file (the format of `translate --translations` of Facebook's fbt, files of older versions are converted), and the generated tokens and types are aligned
- `FbtElementNode` trees of callsites with `doNotExtract` are not collected
- Common strings from `fbtCommonPath` are loaded once
- `translate --jenkins` prints `{}` without translation groups, and line terminators (U+2028, U+2029) aren't escaped in the JSON output
- Changes of Facebook's fbt after v1.0.0 (up to fbt v1.0.2 / main of Nov 2024):
  - a missing parameter is reported to the `onMissingParameterError()` method of the error listener
  - `logImpression` hook receives the input table and the keys used to access it
  - `intlList()` ignores empty items (`null`, `false`, `''`)
### Added
- `key` option of `fbt:plural`, `fbt:enum` and `fbt:pronoun`
- `migrate-v5` command to migrate translation files
- `extraOptions` and `generateOuterTokenName` configuration options
- Phonological rules applied on token substitution (`IntlPunctuation::applyPhonologicalRules()`)
- Hooks: `getFbtResult`, `getFbsResult`, `errorListener`
- `FbtResultBase`, `FbtPureStringResult`, `fbt::isFbtInstance()`, `fbt::_implicitParam()`
- `BULLET` delimiter for `intlList()`
- `formatNumber` utility with `withMaxLimit()` / `withMinLimit()`
- `IntlGender` and `DisplayGenderConst`
- Locales `fbt_AC`, `fn_IT`, `lr_IT`, `nh_MX`, `tq_AR`
### Fixed
- Descriptions of inner strings of implicit parameters (e.g. `In the phrase: "{=} {=poked you}."`)
- Enums and other string variations inside implicit parameters
- File-level docblock options leaking to fbt calls of other files
- Valueless attributes (e.g. `<fbt common>`)
- `intlList()` with an empty conjunction/delimiter
- `Gender::getData()` warning for unknown genders
### Removed
- `fbt\fbt()` and `fbt\fbs()` namespaced functions, `fbtNamespace`, `fbtNode`, `fbtElement` and `FbtAutoWrap`

## v4.4.0 - 2026-09-24
### Changed
- `Gender::GENDER_CONST['MIXED_SINGULAR']` and `Gender::GENDER_CONST['MIXED_PLURAL']` are replaced with `Gender::GENDER_CONST['MIXED_UNKNOWN']`
- `IntlNumberType::getLocale()` is renamed to `IntlNumberType::forLocale()`
- Numbers are no longer formatted in scientific notation (e.g. `1.0E+15` / `1.99E-7`)
- Plural rules (CLDR) support fractional numbers (e.g. `1.5 apples`), Hebrew rules are updated
- Locales with merged unknown gender (`ps`, `sq`, `ti`, `kab`, `dsb`, `vec`; `ht` removed)
- Source strings and translation files are read and written under a file lock, so concurrent processes do not overwrite each other's phrases
- Plurals are deduplicated during collecting by their value instead of their count
- A phrase rendered more than once is collected only once
- `fbt::sameParam` referring to an unknown token name throws an error while collecting
### Added
- Kirundi (`rn`) plural rules
- Support for `nikic/php-parser` v5
### Fixed
- Rendered strings are no longer cached across locales and viewer genders (a process switching locales could return the previously rendered translation)
- Pronouns `object` / `reflexive` with `NOT_A_PERSON`, `NEUTER_SINGULAR` or `UNKNOWN_SINGULAR` genders resolved to the wrong form
- Repeated pronouns in a phrase were not deduplicated
- Fractional plural counts were truncated (`1.5` was treated as `1`)
- `fbt::param` with an explicit `number` value of `0` or a fractional value
- `intlNumUtils`: dropped `.0` decimals, infinite loop with a group size of `0`, unescaped currency patterns, `parseNumber()` returning `0` instead of `null` for invalid input, lost precision of large numbers
- `intlList()` with arrays that are not 0-indexed
- Translations equal to `''` or `'0'` were treated as missing
- Parent mappings of already stored inner strings (including a parent phrase with id `0`)
- Accessing a pattern string with a table index returned a single character

## v4.3.4 - 2026-06-14
### Changed
- Replace `SimpleHtmlDom` with `DOMForge`
### Added
- `FbtConfig::clearListeners()` method
- PHP 8.2, 8.3 and 8.4 to the test matrix

## v4.3.3 - 2025-04-15
### Changed
- Default value for `fbt::param` is now `123` instead of `value` while running `collect-fbts` command

## v4.3.2 - 2025-02-16
### Fixed
- Fix `fbt::param` boolean conversion in `number` option
### Added
- Listener for `FbtConfig`

## v4.3.1 - 2025-01-12
### Fixed
- Fix intlNumUtils class parser and formatter

## v4.3.0 - 2024-12-14
### Added
- Latte support
### Changed
- Replaced deprecated function `utf8_encode` with `mb_convert_encoding`

## v4.2.9 - 2024-12-06
### Fixed
- Unicode characters in fbt::param

## v4.2.8 - 2024-11-01
### Changed
- fbt:param now accepts also empty string as a value
### Added
- fallback configuration for translations (available for 'translate' command)

## v4.2.7 - 2024-06-16
### Fixed
- Fix mixed text children with `<br />` in fbt:param

## v4.2.6 - 2024-06-01
### Added
- Cache to improve performance of the `fbt` and `fbs` methods

## v4.2.5 - 2024-04-16
### Fixed
- Fix fbt::sameParam for subject

## v4.2.4 - 2024-01-12
### Fixed
- Collecting of fbt::param values
### Changed
- `nikic/php-parser` is limited to `^4.1` (version 5 is not supported)

## v4.2.3 - 2023-12-09
### Changed
- Allow to call customized `\fbt\fbt` implementation for methods `fbt` and `fbs` when Laravel package is used

### Fixed
- Redundant file scanning for docblock with the `@fbt` pragma
- `tiger128,3` now generates the same hash as the original implementation

## v4.2.2 - 2023-06-28
### Changed
- Reduced the number of fbt namespaces during collecting

### Added
- Detailed information of collecting for fbt collect command (processed files are printed)

## v4.2.1 - 2023-06-21
### Added
- Config `prettyPrint` (default `true`) to pretty print source strings in a JSON file.

## v4.2.0 - 2023-06-16
### Fixed
- Jenkins hash generation when using unicode characters in text

## v4.1.0 - 2023-04-19
### Added
- The `.source_strings.json` file is automatically deleted prior to executing the `collect-fbts` command.

### Changed
- The `rsearch` function now returns an `array` instead of a `Generator` (files are now sorted alphabetically).

### Fixed
- Error on PHP 7.2 when the source strings file contains no phrases

## v4.0.11 - 2023-02-03
### Fixed
- Rendering of text mixed with elements

## v4.0.10 - 2023-01-10
### Fixed
- Dockblock extraction

## v4.0.9 - 2022-12-16
### Fixed
- Pronoun attribute `type` when using fbt::pronoun
- Disable cache for phrase when using reporting

### Changed
- `fbs()` helper returns an `fbt\fbs` object instead of a `string`

### Added
- Collecting of `fbt::c()` calls (common strings) and `--fbt-common-path` option for the `collect-fbts` command

## v4.0.8 - 2022-09-13
### Changed
- Visibility for CollectFbtsService.php

## v4.0.7 - 2022-09-09
### Added
- Command to collect FBT strings

## v4.0.6 - 2022-08-09
### Fixed
- Pronoun capitalization

### Added
- `intlList` function

## v4.0.5 - 2022-07-30
### Fixed
- Punctuation when a value ends with it

### Added
- `JsonSerializable` interface to fbt
- `ext-mbstring` requirement to composer.json

## v4.0.4 - 2022-07-10
### Fixed
- Empty node checking

### Added
- Command to generate missing translation hashes
- Tokens and types of phrases are filled in generated translations

## v4.0.3 - 2022-06-25
### Fixed
- __toString() issue when using inline mode in php < 8.0

## v4.0.2 - 2022-06-23

### Fixed
- Raw string collecting.

### Added
- Check for tags without content.

## v4.0.1 - 2022-06-19

### Fixed
- Storing already stored hashes.
- --stdin, --pretty flags for command `php ./vendor/bin/fbt translate`

### Added
- Automatic registration of translations.

## v4.0 - 2022-04-09

### Added
- Support PHP >= 7.2.
