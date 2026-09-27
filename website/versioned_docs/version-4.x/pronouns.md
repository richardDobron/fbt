---
id: pronouns
title: Pronouns
sidebar_label: Pronouns
---

`fbt:pronoun` and `fbt::pronoun` both take a required `FbtConstants::PRONOUN_USAGE` enum and a [`Gender::GENDER_CONST`](https://github.com/richardDobron/fbt/blob/main/src/fbt/Runtime/Gender.php) enum:
```php
class FbtConstants
{
    const PRONOUN_USAGE = [
        "OBJECT" => 0,
        "POSSESSIVE" => 1,
        "REFLEXIVE" => 2,
        "SUBJECT" => 3
    ];
}

class Gender
{
    const GENDER_CONST = [
        'NOT_A_PERSON' => 0,
        'FEMALE_SINGULAR' => 1,
        'MALE_SINGULAR' => 2,
        'FEMALE_SINGULAR_GUESS' => 3,
        'MALE_SINGULAR_GUESS' => 4,
        'MIXED_SINGULAR' => 5,
        'MIXED_PLURAL' => 5,
        'NEUTER_SINGULAR' => 6,
        'UNKNOWN_SINGULAR' => 7,
        'FEMALE_PLURAL' => 8,
        'MALE_PLURAL' => 9,
        'NEUTER_PLURAL' => 10,
        'UNKNOWN_PLURAL' => 11,
    ];
}
```

**⚠️ NOTE: This is not the same gender as used in `fbt:param`, `fbt:name`, or `subject`!**
The `IntlVariations` used in those cases only has `GENDER_MALE`, `GENDER_FEMALE`, and `GENDER_UNKNOWN`.


## Pronoun example:

```
<fbt desc="pronoun example">
  <fbt:param name="name"><?=$ent->getName()?></fbt:param>
  shared
  <fbt:pronoun type="possessive" gender="<?=$ent->getPronounGender()?>" />
  photo with you.
</fbt>
```

### Optional attributes
* **capitalize** `bool`: Whether to capitalize the pronoun in the source string.
* **human** `bool`: Whether to elide the NOT_A_PERSON option in the text variations generated.

The example above generates:
```
{
  "hashToText": {
    "23fa7e4d6a4686bb6ff609c00726cf33": "{name} shared her photo with you.",
    "dd86ffccd845f2767c691f8d48f69e25": "{name} shared his photo with you.",
    "2584ed80718ca4138cd95adcf492de53": "{name} shared their photo with you."
  },
  ...,
  "type": "table",
  "desc": "pronoun example",
  "jsfbt": {
    "t": {
      "1": "{name} shared her photo with you.",
      "2": "{name} shared his photo with you.",
      "*": "{name} shared their photo with you."
    },
    "m": [
      null
    ]
  }
}
```

## Combinations
Conceptually, pronouns work as though there was an `enum` supplied for the given `type`.
Below is the table of possible values for their various types.
*Note how `reflexive` and `object` have 4 types*

    subject:    he/she/they
    possessive: his/her/their
    reflexive:  himself/herself/themselves/themself
    object:     him/her/them/this

     V Name                  Subject Possessive Reflexive  Object
    =============================================================
     0 NOT_A_PERSON          they    their      themself   this
     1 FEMALE_SINGULAR       she     her        herself    her
     2 MALE_SINGULAR         he      his        himself    him
     3 FEMALE_SINGULAR_GUESS she     her        herself    her
     4 MALE_SINGULAR_GUESS   he      his        himself    him
     5 MIXED_SINGULAR        they    their      themselves them
     5 MIXED_PLURAL          they    their      themselves them
     6 NEUTER_SINGULAR       they    their      themself   them
     7 UNKNOWN_SINGULAR      they    their      themself   them
     8 FEMALE_PLURAL         they    their      themselves them
     9 MALE_PLURAL           they    their      themselves them
    10 NEUTER_PLURAL         they    their      themselves them
    11 UNKNOWN_PLURAL        they    their      themselves them
