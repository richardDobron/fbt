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
    2 => [
      "hashToText" => [
        "576c64dce7dc0eb30803b1c2feb21722": "Go on an {=awesome vacation}"
      ],
      "desc": "auto-wrap example",
      ...,
    ],
    1 => [
      "hashToText" => [
        "7de5f69602b0c289965183f9ffbf2496": "{=awesome} vacation"
      ],
      "desc": "In the phrase: \"Go on an {=awesome vacation}\"",
      ...,
    ],
    0 => [
      "hashToText" => [
        "6bbb015218a9c99babf7213c1fa764d8": "awesome"
      ],
      "desc": "In the phrase: \"Go on an {=awesome} vacation\"",
      ...,
    ]
  ],
  "childParentMappings" => [
    0 => 1,
    1 => 2
  ]
}
```

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
