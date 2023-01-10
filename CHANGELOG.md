# Changelog

All notable changes to `fbt` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## v3.0.10 - 2023-01-10
### Fixed
- Dockblock extraction

## v3.0.9 - 2022-12-16
### Fixed
- Pronoun attribute `type` when using fbt::pronoun
- Disable cache for phrase when using reporting

### Added
- fbt::c(...) collecting

## v3.0.8 - 2022-09-13
### Changed
- Visibility for CollectFbtsService.php

## v3.0.7 - 2022-09-09
### Added
- Command to collect FBT strings

## v3.0.6 - 2022-08-09
### Fixed
- Pronoun capitalization

### Added
- `intlList` function

## v3.0.5 - 2022-07-30
### Fixed
- Punctuation when a value ends with it

### Added
- `JsonSerializable` interface to fbt
- `ext-mbstring` requirement to composer.json

## v3.0.4 - 2022-07-10
### Fixed
- Empty node checking

### Added
- Command to generate missing translation hashes

## v3.0.3 - 2022-06-25
### Fixed
- __toString() issue when using inline mode in php < 8.0

## v3.0.2 - 2022-06-23

### Fixed
- Raw string collecting.

### Added
- Check for tags without content.

## v3.0.1 - 2022-06-19

### Fixed
- Storing already stored hashes.
- --stdin, --pretty flags for command `php ./vendor/bin/fbt translate`

### Added
- Automatic registration of translations.

## v3.0 - 2022-02-18

### Added
- PHP Internationalization Framework for PHP 7.
