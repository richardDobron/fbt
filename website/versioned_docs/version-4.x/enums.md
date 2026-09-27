---
id: enums
title: Enumerations
sidebar_label: Enumerations
---

Enumerations eliminate a lot of UI code duplication while enabling accurate translations.  `<fbt:enum>` and `fbt::enum` both provide the ability to add your ad-hoc enumerations.

## Adhoc enums
Adhoc enums can be provided inline to the `enum-range` attribute or as the second parameter to `fbt::enum`.
### Enum map
```
<fbt desc="buy prompt">
  Buy a new
  <fbt:enum enum-range="<?=json_encode([
    'CAR' => 'car',
    'HOUSE' => 'house',
    'BOAT' => 'boat',
    'HOUSEBOAT' => 'houseboat',
  ])?>" value="<?=$enumVal?>" />!
</fbt>

fbt(
  'Buy a new ' .
    fbt::enum($enumVal, [
      'CAR' => 'car',
      'HOUSE' => 'house',
      'BOAT' => 'boat',
      'HOUSEBOAT' => 'houseboat',
    ]),
  'buy prompt',
);
```

### Shorthand array (keys = values)
The shorthand array adhoc enum functions as though you had a `[value => value]` map.
```
<fbt desc="buy prompt">
  Buy a new
  <fbt:enum enum-range="<?=json_encode([
    'car', 'house', 'boat', 'houseboat'
  ])?>" value="<?=$enumVal?>" />!
</fbt>

fbt(
  'Buy a new ' . fbt::enum($enumVal, ['car', 'house', 'boat', 'houseboat']) . '!',
  'buy prompt',
);
```

All the above examples [extract](collection.md) the same 4 separate strings for translation in JSON like:

```json
{
  "phrases": [
    {
      "hashToText": {
        "b463748f978f242787f5f225a7762aeb": "Buy a new car!",
        "1255ecb7aa0a34b8755d4f068c9b9c41": "Buy a new house!",
        "7c01d5d74f6e3c8eda0b166a366b937e": "Buy a new boat!",
        "7a7776e292838b6fe8c4a7dfd58117cd": "Buy a new houseboat!"
      },
      ...,
      "desc": "buy prompt",
      ...
    },
```
