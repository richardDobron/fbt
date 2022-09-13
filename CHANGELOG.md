# Changelog

All notable changes to `fbt` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

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
