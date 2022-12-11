<h1 align="center">
  <img src="icon.png" height="150" width="150" alt="FBT"/>
</h1>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/richardDobron/fbt.svg?style=flat-square)](https://packagist.org/packages/richardDobron/fbt)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
![Test Status](https://img.shields.io/github/workflow/status/richardDobron/fbt/run-tests/main?label=tests)
![Code Style Status](https://img.shields.io/github/workflow/status/richardDobron/fbt/Check%20&%20fix%20styling?label=code%20style)
[![Total Downloads](https://img.shields.io/packagist/dt/richardDobron/fbt.svg?style=flat-square)](https://packagist.org/packages/richardDobron/fbt)

FBT is an internationalization framework for PHP designed to be not just **powerful** and **flexible**, but also **simple** and **intuitive**.  It helps with the following:
* Organizing your source text for translation
* Composing grammatically correct translatable UI
* Eliminating verbose boilerplate for generating UI

**This library is based on the JavaScript implementation of Facebook's [FBT][link-facebook-fbt].**

<img src="/docs/intro.png" alt="FBT intro"/>

## Requirements
* PHP 7.0 or higher
* [Composer](https://getcomposer.org) is required for installation

## Installing

```shell
$ composer require richarddobron/fbt:^4
```

## Getting started

[Integrating into your app](docs/getting_started.md)

## Version Guidance

| Version | Released   | Status     | Repo             | PHP Version |
|---------|------------|------------|------------------|-------------|
| 3.x     | 2022-02-18 | Maintained | [v3][fbt-3-repo] | >= 7.0      |
| 4.x     | 2022-04-09 | Latest     | [v4][fbt-4-repo] | >= 7.2      |

## Official integrations

The following integrations are fully supported and maintained:

- [Laravel](https://github.com/richardDobron/laravel-fbt)

## How FBT works
FBT works by transforming your `<fbt>` and `fbt(...)` constructs via
[Simple HTML DOM Parser][simplehtmldom].  This library serve to extract strings from source and
lookup translated payloads generated while execution.  FBT creates tables
of all possible variations for the given fbt phrase and accesses this
at runtime.

## Full documentation
https://github.com/richardDobron/fbt/tree/main/docs


## TODO

- [ ] Add driver-agnostic support for multiple database systems.
- [ ] Add integrations for Symfony, CakePHP, Zend Framework, Nette, ...
- ...

## License
FBT is MIT licensed, as found in the [LICENSE](LICENSE) file.

[fbt-3-repo]: https://github.com/richarddobron/fbt/tree/3.x
[fbt-4-repo]: https://github.com/richarddobron/fbt
[link-facebook-fbt]: https://github.com/facebook/fbt
[simplehtmldom]: https://sourceforge.net/projects/simplehtmldom/files/simplehtmldom/1.9.1/
