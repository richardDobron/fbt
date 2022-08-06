# Common FBT strings

The `fbt` framework provides a way to define common simple strings in one shared location.  The expected format is as a text to description map.

E.g.

```json5
// OurCommonStrings.json
{
  "Photo": "Still image ...",
  "Video": "Moving pictures ...",
  ...
}
```

or

```php
// OurCommonStrings.php
<?php

return [
  "Photo": "Still image ...",
  "Video": "Moving pictures ...",
  ...
]
```

## FBT Transform
It accepts these common strings via the main transform, [`FbtTransform`](https://github.com/richardDobron/fbt/blob/main/src/fbt/Transform/FbtTransform/FbtTransform.php#L34-L35), as an option.

Example setup:

```php
\fbt\FbtConfig::set('fbtCommonPath', '/path/to/OurCommonStrings.json');
// OR inlined...
\fbt\FbtConfig::set('fbtCommon', [
    'Post' => 'Button to post a comment',
]);
```

## Runtime API
To use the strings at runtime, there is the `fbt::c(...)` function call or the `<fbt common="true">...</fbt>` JSX API.

***NOTE: The transform will throw if it encounters a common string *not* in the map provided.***

E.g.

```php
<button>
  <?=fbt::c('Photo')?>
</button>
```

or

```html
<button>
  <fbt common="true">Photo</fbt>
</button>
```

Both examples above function as if the engineer had also included the description with the text.

```js
  <fbt desc="Still image ...">Photo</fbt>
```

All of these instances would produce the same identifying hash at collection time, and thus coalesce into the same translation.
