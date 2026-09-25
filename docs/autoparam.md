---
id: autoparam
title: Auto-parameterization
sidebar_label: Auto-parameterization
---

# What is Auto Parameterization?

## The basics

`<fbt>` will automatically wrap any non-fbt children in the top-level
`<fbt>` as though they were written with an `<fbt:param>` with a
`name` attribute containing the child's text.  It will pull any child
text into the parameter name, including those of recursive structures.


- HTML fbt syntax:

```html
<fbt desc="auto-wrap example">
  Go on an
  <a href="#">
    <span>awesome</span> vacation
  </a>
</fbt>
```

- Function syntax:

```php
fbt(
  [
    'Go on an ',
    \fbt\createElement(
      'a',
      '<span>awesome</span> vacation',
      ['href' => '#']
     )
  ],
  'auto-wrap example',
);
```

When extracted for translation, the result of the `\fbt\Transform\FbtTransform\FbtTransform::toArray()` is:

```php
[
  "phrases" => [
    0 => [
      "hashToLeaf" => [
        "V2xk3OfcDrMIA7HC/rIXIg==" => [
          "text" => "Go on an {=awesome vacation}",
          "desc" => "auto-wrap example",
        ],
      ],
      "project" => "website app",
      "jsfbt" => [
        "t" => [
          "desc" => "auto-wrap example",
          "text" => "Go on an {=awesome vacation}",
          "tokenAliases" => ["=awesome vacation" => "=m1"],
        ],
        "m" => [],
      ],
    ],
    1 => [
      "hashToLeaf" => [
        "feX2lgKwwomWUYP5/78klg==" => [
          "text" => "{=awesome} vacation",
          "desc" => "In the phrase: \"Go on an {=awesome vacation}\"",
        ],
      ],
      ...,
    ],
    2 => [
      "hashToLeaf" => [
        "a7sBUhipyZur9yE8H6dk2A==" => [
          "text" => "awesome",
          "desc" => "In the phrase: \"Go on an {=awesome} vacation\"",
        ],
      ],
      ...,
    ],
  ],
  "childParentMappings" => [
    1 => 0,
    2 => 1,
  ],
]
```

The `tokenAliases` map the tokens of inner strings to the parameters used at runtime (`{=m1}`), so
that translations can freely reorder or change the text of the inner strings.
Notice the description for "vacation" is auto-generated with an `"In
the phrase: ..."` prefix.  Additionally, we use a convention of adding an equal sign (`=`)
prefix in the interpolation `{=awesome vacation}` to signal to the
translator that this exact word or phrase goes in the associated outer
sentence.

Furthermore, we provide a mapping `[<childIndex> => <parentIndex>]` in
the collection output `childParentMappings`.  At Meta, we use
these to display all relevant inner and outer strings when translating
any given piece of text.  We recommend you do the same in whatever
translation framework you use.  Context is crucial for accurate
translations.

## Advanced scenario: string with nested fbt constructs

You can still use fbt constructs that produce multiple strings (e.g. `<fbt:pronoun>`) and expect the Auto Parameterization to work.

**Example with fbt:pronoun and fbt:plural**
```html
<fbt desc="advanced rich content example">
  <b>
    <!-- Group 1 -->
    <fbt:pronoun
      capitalize="true"
      gender="<?=$personGender?>"
      human="true"
      type="subject" />
  </b>
  <!-- Group 2 -->
  uploaded
  <a href="#">
    <!-- Group 3 -->
    <fbt:plural count="<?=$aCount?>" many="photos" showCount="ifMany">
      one photo
    </fbt:plural>
  </a>
  <!-- Group 4 -->
  that you haven't seen yet.
</fbt>
```

### Top-level strings

Top-level strings are built from the combination of all text sub-groups:

```
`She`  -------------\              /----- `{=one photo}` -----\
`He`   --------------* `uploaded` *                            * `that you haven't seen yet`
`They` -------------/              \-- `{=[number] photos}` --/

^^^^^^^                 ^^^^^^^^        ^^^^^^^^^^^^^^^^^^        ^^^^^^^^^^^^^^^^^^^^^^^^^
Group 1                 Group 2         Group 3                   Group 4
(Gender variation)                      (Nb variation)
```

**Extracted top-level strings by string variation criteria:**

1. Female:
    1. Single: `"{=She} uploaded {=one photo} that you haven't seen yet."`
    1. Plural: `"{=She} uploaded {=[number] photos} that you haven't seen yet."`
1. Male:
    1. Single: `"{=He} uploaded {=one photo} that you haven't seen yet."`
    1. Plural: `"{=He} uploaded {=[number] photos} that you haven't seen yet."`
1. Unknown:
    1. Single: `"{=They} uploaded {=one photo} that you haven't seen yet."`
    1. Plural: `"{=They} uploaded {=[number] photos} that you haven't seen yet."`

*NOTE: all these strings have the same description: `"advanced rich content example"`*

### Combinations of nested strings

Texts and descriptions of nested strings will have the relevant gender/number variations as well:

```
----------------------------------------------------------------------------------------------------------------------
| Text of   | Text of          | Description                                                                         |
| group 1   | group 3          |                                                                                     |
| (Gender)  | (Number)         |                                                                                     |
----------------------------------------------------------------------------------------------------------------------
| She       | {number} photos  | 'In the phrase: "{=She} uploaded {=[number] photos} that you haven\'t seen yet."'   |
| She       | one photo        | 'In the phrase: "{=She} uploaded {=one photo} that you haven\'t seen yet."'         |
| He        | {number} photos  | 'In the phrase: "{=He} uploaded {=[number] photos} that you haven\'t seen yet."'    |
| He        | one photo        | 'In the phrase: "{=He} uploaded {=one photo} that you haven\'t seen yet."'          |
| They      | {number} photos  | 'In the phrase: "{=They} uploaded {=[number] photos} that you haven\'t seen yet."'  |
| They      | one photo        | 'In the phrase: "{=They} uploaded {=one photo} that you haven\'t seen yet."'        |
----------------------------------------------------------------------------------------------------------------------
```

### Child-to-parent phrase mapping

It's generally useful to submit translation requests containing strings that are closely related.
It helps improve translation consistency (tone, vocabulary, etc...) since the same translator
can work on the same bundle of strings all at once.

To support this use case, as part of the [collection](collection.md) output, we expose the relationship between each parent/child string
in the `childParentMappings` property.

For each mapping entry:
- the key represents the index of the child phrase
- the value represents the index of the parent phrase

For the example above, the output is:

```php
[
  "phrases" => [
    0 => [...], // "{=She} uploaded {=one photo} that you haven't seen yet.", ...
    1 => [...], // "She", "He", "They"
    2 => [...], // "one photo", "{number} photos"
  ],
  "childParentMappings" => [
    1 => 0, // i.e. The phrase at index 1 has a parent phrase at index 0
    2 => 0,
  ],
]
```
