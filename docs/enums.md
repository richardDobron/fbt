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
      "hashToLeaf": {
        "tGN0j5ePJCeH9fIlp3Yq6w==": {"text": "Buy a new car!", "desc": "buy prompt"},
        "ElXst6oKNLh1XU8GjJucQQ==": {"text": "Buy a new house!", "desc": "buy prompt"},
        "fAHV109uPI7aCxZqNmuTfg==": {"text": "Buy a new boat!", "desc": "buy prompt"},
        "end24pKDi2/oxKff1YEXzQ==": {"text": "Buy a new houseboat!", "desc": "buy prompt"}
      },
      "project": "website app",
      "jsfbt": {
        "t": {
          "car": {"desc": "buy prompt", "text": "Buy a new car!"},
          "house": {"desc": "buy prompt", "text": "Buy a new house!"},
          "boat": {"desc": "buy prompt", "text": "Buy a new boat!"},
          "houseboat": {"desc": "buy prompt", "text": "Buy a new houseboat!"}
        },
        "m": [null]
      }
    }
  ]
}
```

### Reusing the same enum value

When multiple enums depend on the same value, give them the same `key`, so that only the
consistent combinations of their values are generated for translation:

```
<fbt desc="buy prompt">
  Buy a new <fbt:enum enum-range="<?=json_encode($range1)?>" value="<?=$value?>" key="item" />
  or a used <fbt:enum enum-range="<?=json_encode($range2)?>" value="<?=$value?>" key="item" />!
</fbt>

fbt(
  'Buy a new ' . fbt::enum($value, $range1, ['key' => 'item']) .
  ' or a used ' . fbt::enum($value, $range2, ['key' => 'item']) . '!',
  'buy prompt'
);
```

*Facebook's fbt detects that the same variable is used. In PHP, the source code of values isn't
available, so the `key` option has to be used instead.*
