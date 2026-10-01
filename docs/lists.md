---
id: lists
title: Lists
sidebar_label: Lists
---

`<fbt:list>` and `fbt::list` render a list of items with a translatable conjunction, e.g. "Tokyo, London and Vienna". The list is a token of the string, and its connecting words ("and", "or", ", ") are translated separately (see [`intlList`](utilities.md#intllist)).

This construct comes from [fbtee](https://github.com/nkzw-tech/fbtee).

```php
fbt(
  'Available Locations: ' . fbt::list('locations', ['Tokyo', 'London', 'Vienna']) . '.',
  'Lists'
);
// Available Locations: Tokyo, London and Vienna.
```

The items are a runtime value, so the same callsite can render lists of any length. Items can be strings or fbt results.

### Conjunction and delimiter

The optional third and fourth arguments are the conjunction (`and` by default, `or`, `none`) and the delimiter (`comma` by default, `semicolon`, `bullet`). The values of `IntlList::CONJUNCTIONS` and `IntlList::DELIMITERS` work too.

```php
use fbt\Runtime\Shared\IntlList;

fbt::list('locations', $locations, 'or');
// Tokyo, London or Vienna

fbt::list('locations', $locations, IntlList::CONJUNCTIONS['NONE'], IntlList::DELIMITERS['BULLET']);
// Tokyo • London • Vienna
```

### HTML form

The items of `<fbt:list>` are a JSON array, and the element must be self-closing:

```php
<fbt desc="Lists">
  Available Locations:
  <fbt:list
    name="locations"
    items="<?= htmlspecialchars(json_encode($locations)) ?>"
    conjunction="or"
    delimiter="bullet"
  />.
</fbt>
```

### Extracted string

The list is collected as a single token:

```
Available Locations: {locations}.
```
