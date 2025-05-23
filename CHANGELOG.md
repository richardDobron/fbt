# Changelog

All notable changes to `fbt` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

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

## v4.2.3 - 2023-12-09
### Changed
- Allow to call customized `\fbt\fbt` implementation for methods `fbt` and `fbs` when Laravel package is used

### Fixed
- Redundant file scanning for docblock with the `@fbt` pragma
- `tiger128,3` now generates the same hash as the original implementation

### Added
- Detailed information of collecting for fbt collect command

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
