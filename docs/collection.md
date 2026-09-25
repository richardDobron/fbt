---
id: collection
title: Extracting FBTs
sidebar_label: Extracting translatable texts
---

We provide `collect-fbts` as a utility for collecting strings.

```shell
php ./vendor/bin/fbt collect-fbts --path=./path/to/fbt/ --src=./path/to/project/
```

### Options:
| name                       | default | description                                                                             |
|----------------------------|---------|-----------------------------------------------------------------------------------------|
| --src=`[path]`             | *none*  | The directory where you want to scan usages of fbt in php files.                        |
| --path=`[path]`            | *none*  | Cache storage path for source strings                                                   |
| --fbt-common-path=`[path]` | *none*  | Optional path to the common strings module. This is a map from {[text]: [description]}. |

⚠️ Unlike Facebook's version of fbt, we primarily collect `<fbt>` & translate strings during script execution.

Upon successful execution, the output of the `/your/path/to/fbt/.source_strings.json` will be in the following format
(the same as the output of `collectFbt` of Facebook's fbt):

```json
{
  "phrases": [
    {
      "hashToLeaf": {
        "<hash>": {"text": "<text>", "desc": "<description>"},
        ...
      },
      "filepath": "<path of the file>",
      "line_beg": <line>,
      "line_end": <line>,
      "author": "<author>",
      "project": "<project>",
      "jsfbt": {
        "t": <leaf> | <table of leaves>,
        "m": <metadata>
      }
    }
  ],
  "childParentMappings": {
    "<childIdx>": <parentIdx>
  }
}
```

A leaf is an object of the form `{"desc": "...", "text": "...", "tokenAliases": {...}}`, and a table is a
(nested) object whose keys are string variations (e.g. `*`, `_1` for plurals, enum keys, genders, …) and
whose values are leaves or tables:

```json
{
  "t": {
    "*": {"desc": "plural example", "text": "{number} photos"},
    "_1": {"desc": "plural example", "text": "1 photo"}
  },
  "m": [{"token": "number", "type": 2, "singular": true}]
}
```

Inner strings of HTML elements nested in an fbt (e.g. `<a>Learn more</a>`) are collected as separate
phrases, and `childParentMappings` links them to their enclosing phrase. The enclosing phrase refers to
them with a token like `{=Learn more}`, and `tokenAliases` maps these tokens to their runtime aliases
(e.g. `{"=Learn more": "=m3"}`).

`phrases` here represents all the *source* information we need to
process and produce an `fbt::_(...)` callsite's final payload.  When
combined with corresponding translations to each `hashToLeaf` entry we
can produce the translated payloads `fbt::_()` expects.

When it comes to moving from source text to translations, what is most
pertinent is the `hashToLeaf` payload containing all relevant texts and descriptions
with their identifying hash.  You can choose `md5` or `tiger` hash module.  It defaults to `md5`
(encoded in `base64` by default, see the `md5_digest` option).

### A note on hashes

In the FBT framework, there are 2 main places we uses hashes for
identification: **text** and **fbt callsite**.  The `hashToLeaf` mapping
above represents the hash of the **text** and its **description**.  This is used
when *building* the translated payloads.

The hash of the callsite (defaulting to `jenkins` hash) is used to
look up the payload in
[`FbtTranslations`](https://github.com/richardDobron/fbt/blob/main/src/fbt/Runtime/FbtTranslations.php).
This is basically the hash of the object you see in `jsfbt.t`.

See [Translating FBTs](translating.md) for getting your translations in
the right format.
