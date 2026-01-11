<h1 align="center">
  <img src="icon.png" height="150" width="150" alt="FBT"/>
</h1>

# FBT for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/richardDobron/fbt.svg?style=flat-square)](https://packagist.org/packages/richardDobron/fbt)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
![Test Status](https://github.com/richardDobron/fbt/actions/workflows/run-tests.yml/badge.svg)
![Code Style Status](https://github.com/richardDobron/fbt/actions/workflows/php-cs-fixer.yml/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/richardDobron/fbt.svg?style=flat-square)](https://packagist.org/packages/richardDobron/fbt)

FBT is an internationalization framework for PHP designed to be not just **powerful** and **flexible**, but also **simple** and **intuitive**.  It helps with the following:
* Organizing your source text for translation
* Composing grammatically correct translatable UI
* Eliminating verbose boilerplate for generating UI

**This library is based on the JavaScript implementation of Facebook's [FBT][link-facebook-fbt].**

<img src="docs/intro.png" alt="FBT intro"/>
<img src="docs/plurals.png" alt="FBT plurals"/>
<img src="docs/enum.png" alt="FBT enum"/>
<img src="docs/pronouns.png" alt="FBT pronouns"/>

## 📖 Requirements
* PHP 7.0 or higher
* [Composer](https://getcomposer.org) is required for installation

## 📦 Installing

```shell
$ composer require richarddobron/fbt:^4
```

## ✨ Features
- [x] Parameters and interpolation
- [x] Implicit parameters
- [x] Auto-parameterization
- [x] Plurals
- [x] Enumerations
- [x] Pronouns
- [x] Number formatting
- [x] Phrase extracting
- [x] Inline translating

## 🚀 Getting started

See the [Getting Started](docs/getting_started.md) guide for more.

## 📑 Version Guidance

| Version | Released   | Status     | Repo             | PHP Version |
|---------|------------|------------|------------------|-------------|
| 3.x     | 2022-02-18 | Maintained | [v3][fbt-3-repo] | >= 7.0      |
| 4.x     | 2022-04-09 | Latest     | [v4][fbt-4-repo] | >= 7.2      |

## 🔌 Official integrations

The following integrations are fully supported and maintained:

- [Laravel](https://github.com/richardDobron/laravel-fbt)

## ⚙️ How FBT works
FBT works by transforming your `<fbt>` and `fbt(...)` constructs via
[DOM Forge][dom-forge].  It serves to extract strings from source and
lookup translated payloads generated during execution.  FBT creates tables
of all possible variations for each fbt phrase and accesses them
at runtime.

## 📕 Full documentation

- [API Reference](https://richarddobron.github.io/fbt/)

## ✅ TODO

- [ ] Add driver-agnostic support for multiple database systems.
- [ ] Add integrations for Symfony, CakePHP, Zend Framework, ...
- ...

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 📜 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

[fbt-3-repo]: https://github.com/richarddobron/fbt/tree/3.x
[fbt-4-repo]: https://github.com/richarddobron/fbt
[link-facebook-fbt]: https://github.com/facebook/fbt
[dom-forge]: https://github.com/richardDobron/dom-forge
