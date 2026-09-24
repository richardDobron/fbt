---
id: plurals
title: Plurals
sidebar_label: Plurals
---

`fbt:plural` provides you with a shorthand way for plural variations.
```
<fbt desc="plural example">
  You have
  <fbt:plural
    count="<?=getLikeCount()?>"
    name="number of likes"
    showCount="ifMany"
    many="likes">
     a like
  </fbt:plural>
  on your
  <fbt:plural
    count="<?=getPhotoCount()?>"
    showCount="no">
     photo
  </fbt:plural>.
</fbt>
```
OR
```php
fbt(
  'You have ' .
    fbt::plural('a like', getLikeCount(), [
      'name' => 'number of likes',
      'showCount' => 'ifMany',
      'many' => 'likes',
    ]) .
    ' on your ' .
    fbt::plural('photo', getPhotoCount()) . '.',
  'plural example',
);
```

Both the above examples generate the following during [collection](collection.md).
```json
"phrases": [
  {
    "hashToLeaf": {
      "kNbsbgoEg+3V6XVFkqSsYQ==": {"text": "You have {number of likes} likes on your photos.", "desc": "plural example"},
      "FYpdcH2oW1Y1PN/AXJL06Q==": {"text": "You have {number of likes} likes on your photo.", "desc": "plural example"},
      "QhJz5pBJ8m12xw+zPGqa6g==": {"text": "You have a like on your photos.", "desc": "plural example"},
      "J5yZL5KAllexJA0clVYVow==": {"text": "You have a like on your photo.", "desc": "plural example"}
    },
    "project": "website app",
    "jsfbt": {
      "t": {
        "*": {
          "*": {"desc": "plural example", "text": "You have {number of likes} likes on your photos."},
          "_1": {"desc": "plural example", "text": "You have {number of likes} likes on your photo."}
        },
        "_1": {
          "*": {"desc": "plural example", "text": "You have a like on your photos."},
          "_1": {"desc": "plural example", "text": "You have a like on your photo."}
        }
      },
      "m": [
        {"token": "number of likes", "type": 2, "singular": true},
        null
      ]
    }
  }
]
```#### Required arguments:
* **singular phrase** `string`: HTML child in `<fbt:plural>` and argument 1 in `fbt::plural`
* **count** `number`: `count` in `<fbt:plural>` and argument 2 in `fbt::plural`

#### Optional arguments:
* **many** `string`: Represents the plural form of the string in English.  Default is `$singular . 's'`
* **showCount** `"yes"|"no"|"ifMany"`: Whether to show the `{number}` in the string.
*Note that the singular phrase never has a token, but inlines to `1`. This is to account for languages like Hebrew for which showing the actual number isn't appropriate*

  * **"no"**: (*DEFAULT*) Don't show the count
  * **"ifMany"**: Show the count only in plural case
  * **"yes"**: Show the count in all cases
* **name** `string`: Name of the token where count shows up. (*Default*: `"number"`)
* **value** `mixed`: For overriding the displayed `number`
* **key** `string`: Plurals with the same `key` share the same count, so that only the consistent
  combinations of their variations are generated for translation (see below).

### Plurals depending on the same count

```
<fbt desc="likes">
  There
  <fbt:plural count="<?=$count?>" many="are" key="likes">is</fbt:plural>
  <fbt:plural count="<?=$count?>" showCount="ifMany" many="likes" key="likes">a like</fbt:plural>
</fbt>
```

generates only 2 strings: `There are {number} likes` and `There is a like` (without the `key`,
all 4 combinations would be generated).

*Facebook's fbt detects that the same variable is used for the count. In PHP, the source code of
values isn't available, so the `key` option has to be used instead.*
