---
id: getting_started
title: Integrating into your app
sidebar_label: Getting started
---

We recommend you read the [best practices](best_practices.md) for advice on how to best prepare your applications. We strongly encourage you to do so.

## 📦 Installing

```shell
$ composer require richarddobron/fbt
```

Add these lines to your code:

- _We recommend setting the **author**, **project** and **path** options._
```php
<?php
// require ("vendor/autoload.php");

\fbt\FbtConfig::set('author', 'your name');
\fbt\FbtConfig::set('project', 'project');
\fbt\FbtConfig::set('path', '/path/to/storage');
```

## 🔧 Configuration

### Options

The following options can be defined:

* **project** `string`: (Default: `website app`) Project to which the text belongs
* **author** `string`: Text author
* **preserveWhitespace** `bool`: (Default: `false`)
  - FBT normally consolidates whitespace down to one space (`' '`).
  - Turn this off by setting this to `true`
* **viewerContext** `string`: (Default: `\fbt\Lib\IntlViewerContext::class`)
* **locale** `string`: (Default: `en_US`) User locale.
* **fbtCommon** `string`: (Default: `[]`) common strings, e.g. `[['text' => 'desc'], ...]`
* **fbtCommonPath** `string`: (Default: `null`) Path to the common strings module.
* **path** `string`: Cache storage path for generated translations & source strings.
* **fallback** `array`: (Default: `[]`) Fallback translations, e.g. `['de_AT' => 'de_DE']` 

Below are the less important parameters.

* **collectFbt** `bool`: (Default: `true`) Collect fbt instances from the source and store them to a JSON file.
* **prettyPrint** `bool`: (Default: `true`) Pretty print source strings in a JSON file.
* **hash_module** `string`: (Default: `md5`) Hash module. You can choose `md5` or `tiger` hash module.
* **md5_digest** `string`: (Default: `base64`) MD5 digest. You can choose `base64` (like upstream) or `hex` (default in v4).
* **driver** `string`: (Default: `json`) Currently, only JSON storage is supported.
* **extraOptions** `array`: (Default: `[]`) Extra options allowed on fbt callsites, e.g. `['myOption' => true]`. Their values are passed to the runtime (see the [`getFbtResult` hook](hooks.md#getfbtresult--getfbsresult)).
* **generateOuterTokenName** `bool`: (Default: `false`) Add the outer token name of inner strings to the collected phrases.
* **debug** `bool`: (Default: `false`) Debug mode, e.g. a missing parameter throws an exception.
* **logger** `bool`: (Default: `false`) Log impressions of displayed strings.


## 	🙋 IntlInterface
Optional implementation of IntlInterface on UserDTO.

Example code:

```php
<?php

namespace App;

use fbt\Transform\FbtTransform\Translate\IntlVariations;
use fbt\Lib\IntlViewerContextInterface;
use fbt\Runtime\Gender;

class UserDTO implements IntlViewerContextInterface
{
    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getGender(): int
    {
        if ($this->gender === 'male') {
            return IntlVariations::GENDER_MALE;
        }

        if ($this->gender === 'female') {
            return IntlVariations::GENDER_FEMALE;
        }

        return IntlVariations::GENDER_UNKNOWN;
    }
}
```

After implementation, set `viewerContext`:

```php
$loggedUserDto = ...;

\fbt\FbtConfig::set('viewerContext', $loggedUserDto)
```

## 	🚀  Commands

1. This command collects FBT strings across whole application in PHP files.
```shell
php ./vendor/bin/fbt collect-fbts --path=./path/to/fbt/ --src=./path/to/project/
```
Read more about [FBTs extracting](collection.md).

2. This command generates the missing translation hashes from collected source strings.
```shell
php ./vendor/bin/fbt generate-translations --src=./path/to/fbt/.source_strings.json --translations=./path/to/fbt/*.json
```
3. This command creates translation payloads stored in JSON file.
```shell
php ./vendor/bin/fbt translate --path=./path/to/fbt/ --translations=./path/to/fbt/*.json
```
Read more about [translating](translating.md).

4. This command migrates translation files from v4 to v5.
```shell
php ./vendor/bin/fbt migrate-v5 --translations="./path/to/translations/*.json" --src=./path/to/fbt/.source_strings.json
```
Read more about [upgrading to 5.0](https://github.com/richardDobron/fbt/blob/main/UPGRADE-5.0.md).

## 📘 API

- [fbt(...);](api_intro.md)
- [fbt::param(...);](params.md)
- [fbt::enum(...);](enums.md)
- [fbt::name(...);](params.md)
- [fbt::plural(...);](plurals.md)
- [fbt::pronoun(...);](pronouns.md)
- [fbt::sameParam(...);](params.md)
- [fbt::c(...);](common.md)

```php
echo fbt('You just friended ' . \fbt\fbt::name('name', 'Sarah', 2 /* gender */), 'names');
```

## 🎨 Example Usage

### fbtTransform() & endFbtTransform()
**fbtTransform()**: _This function will turn output buffering on. While output buffering is active no output is sent from the script (other than headers), instead the output is stored in an internal buffer._

**endFbtTransform()**: _This function will send the contents of the topmost output buffer (if any) and turn this output buffer off._

```php
<?php fbtTransform(); ?>
   ...
   <fbt desc="auto-wrap example">
     Go on an
     <a href="#">
       <span>awesome</span> vacation
     </a>
   </fbt>
   ...
<?php endFbtTransform(); ?>

// result: Go on an <a href="#"><span>awesome</span> vacation</a>
```

### fbt()

```php
fbt(
 [
  'Go on an ',
  \fbt\createElement('a', \fbt\createElement('span', 'awesome'), ['href' => '#']),
  ' vacation',
 ],
 'It\'s simple',
 ['project' => "foo"]
)

// result: Go on an <a href="#"><span>awesome</span> vacation</a>
```

```php
fbt('You just friended ' . \fbt\fbt::name('name', 'Sarah', 2 /* gender */), 'names')

// result: You just friended Sarah
```

```php
fbt('A simple string', 'It\'s simple', ['project' => "foo"])

// result: A simple string
```
