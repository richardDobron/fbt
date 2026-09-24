---
id: utilities
title: Runtime Utilities
sidebar_label: Runtime Utilities
---

Bundled with fbt come a few useful utilities for constructing strings.

### intlList(...)
[`intlList`](https://github.com/richardDobron/fbt/blob/master/src/fbt/Runtime/Shared/intlList.php) creates `fbt` instances with selectable conjunctions given an array.

As an example

```php
$CONJUNCTIONS = \fbt\Runtime\Shared\intlList::CONJUNCTIONS;
$DELIMITERS = \fbt\Runtime\Shared\intlList::DELIMITERS;
$people = ['Adam', 'Becky', fbt('4 others', 'last item')];
intlList($people, $CONJUNCTIONS['AND'], $DELIMITERS['COMMA']);
```
produces the fbt
```
<fbt
  desc="A list of items of various types. {previous items} and {following items} are themselves lists that contain one or more items.">
  <fbt:param name="previous items">{$output}</fbt:param>,
  <fbt:param name="following items">{$items[$i]}</fbt:param>
</fbt>
```
recursively combining fbts.
**Note that genders are not used in this `fbt:param` instances, so they default to `UNKNOWN`**

Available delimiters are `COMMA` (default), `SEMICOLON` and `BULLET`:

```php
intlList(['Menlo Park, CA', 'Seattle, WA', 'New York City, NY'], $CONJUNCTIONS['NONE'], $DELIMITERS['BULLET']);
// Menlo Park, CA • Seattle, WA • New York City, NY
```

### formatNumber
[`formatNumber`](https://github.com/richardDobron/fbt/blob/master/src/fbt/Runtime/Shared/formatNumber.php)
formats numbers according to the viewer's locale:

```php
use fbt\Runtime\Shared\formatNumber;

formatNumber::formatNumber(1234.5, 2);          // "1234.50"
formatNumber::withThousandDelimiters(1234.5);   // "1,234.5"
formatNumber::withMaxLimit(1500, 1000);         // fbs "1,000+"
formatNumber::withMinLimit(3, 10);              // fbs "<10"
```

### IntlGender
[`IntlGender`](https://github.com/richardDobron/fbt/blob/master/src/fbt/Runtime/Shared/IntlGender.php)
maps genders to `Gender::GENDER_CONST` values usable by fbt:

```php
use fbt\Lib\DisplayGenderConst;
use fbt\Runtime\Shared\IntlGender;

IntlGender::fromMultiple([$gender]);                  // $gender
IntlGender::fromMultiple([$gender1, $gender2]);       // Gender::GENDER_CONST['UNKNOWN_PLURAL']
IntlGender::fromDisplayGender(DisplayGenderConst::FEMALE); // Gender::GENDER_CONST['FEMALE_SINGULAR']
```

### intlNumUtils and intlSummarizeNumber
There are a few utilities in both `intlNumUtils` and
`intlSummarizeNumber` that are documented in the source.

In fact `fbt::param` and `fbt::plural` default to displaying numbers
using `intlNumUtils::formatNumberWithThousandDelimiters`.
You can override this behavior in `fbt:param` by setting the
[number option](params.md#optional-attributes) and using your own
string in the replacement.

You can override this in `fbt::plural` [by providing the `value`
option](plurals.md#optional-arguments).

### createElement

We use this function internally to generate HTML for FBT.

```php
\fbt\createElement('div', 'content', ['id' => 'container']);
```
