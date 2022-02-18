# Plurals

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

Both the above examples generate the following during [collection](collection).
```
"phrases": [
  {
    "hashToText": {
      "90d6ec6e0a0483edd5e9754592a4ac61": "You have {number of likes} likes on your photos.",
      "158a5d707da85b56353cdfc05c92f4e9": "You have {number of likes} likes on your photo.",
      "421273e69049f26d76c70fb33c6a9aea": "You have a like on your photos.",
      "279c992f92809657b1240d1c955615a3": "You have a like on your photo."
    },
    "type": "table",
    "desc": "plural example",
    ...
  }
]
```
#### Required arguments:
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
