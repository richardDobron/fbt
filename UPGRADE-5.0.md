# Upgrading from 4.x to 5.0

FBT 5.0 is a port of the compiler of [Facebook's fbt v1.0.0](https://github.com/facebook/fbt/tree/babel-plugin-fbt-v1.0.0)
(`babel-plugin-fbt`, rewritten in 2021 around "fbt nodes"). The collected source strings and the
generated translations use the upstream formats, so they must be regenerated.

## Steps

1. Update the package:

   ```shell
   composer require richarddobron/fbt:^5
   ```

2. Collect the source strings again (the old `.source_strings.json` can't be reused):

   ```shell
   php ./vendor/bin/fbt collect-fbts --path=./path/to/fbt/ --src=./path/to/project/
   ```

3. Migrate your translation files (translation groups keyed by the hashes of the source strings):

   ```shell
   php ./vendor/bin/fbt migrate-v5 --translations="./path/to/translations/*.json" --src=./path/to/fbt/.source_strings.json
   ```

   The md5 hash of a text doesn't change, but it's now encoded in `base64` by default (like upstream)
   instead of `hex`, so translation keys are converted. The command reports translations of strings that
   changed in v5 and have to be translated again (e.g. inner strings of HTML elements nested in an fbt,
   whose descriptions are now generated like upstream).

   If you want to keep the `hex` encoding, set `md5_digest` to `hex` in the configuration and skip this step.

4. Generate the translations again:

   ```shell
   php ./vendor/bin/fbt translate --path=./path/to/fbt/ --translations=./path/to/translations/*.json
   ```

## Changed formats

- **Collected phrases**: `hashToText` (`{hash: text}`), `desc` and `type` are replaced by
  `hashToLeaf` (`{hash: {text, desc}}`). `jsfbt` is always `{t, m}`, where the leaves of `t` are
  `{desc, text, tokenAliases?}` objects. Phrases also contain their location (`filepath`, `line_beg`,
  `line_end`). See [Extracting FBTs](docs/collection.md).
- **Inner strings** (HTML elements nested in an fbt) are collected after their enclosing string, and
  `childParentMappings` maps child phrase indexes to their parent (it was reversed in v4).
  Enclosing strings refer to inner strings with tokens like `{=Learn more}`, which are replaced at
  runtime by aliases like `{=m3}` (`tokenAliases`). Translations keep the clear tokens.
- **Hash keys** of translated payloads (`translatedFbts.json`) are computed from the leaves like
  upstream. They're unchanged for plain strings and for strings without inner strings.

## Changed behaviors

- **String variations are no longer deduplicated by value.** Plurals, enums and pronouns sharing the
  same value have to be marked with the same `key` option (upstream compares the source code of the
  expressions, which isn't available in PHP). Without it, all combinations are generated:

  ```php
  fbt(
      'There ' . fbt::plural('is', $count, ['many' => 'are', 'key' => 'likes']) . ' ' .
      fbt::plural('a like', $count, ['showCount' => 'ifMany', 'many' => 'likes', 'key' => 'likes']),
      'likes'
  );
  ```

- **Strings of implicit parameters** are computed like upstream: an enum inside an HTML element
  produces variations (instead of an empty `{=}` token), and descriptions of inner strings contain the
  whole enclosing string (`In the phrase: "{name1} {=poked you}."` instead of `"{=} {=poked you}."`).
- **Empty HTML elements** (e.g. `<br>`, `<i class="icon"></i>`, `<p></p>`) are kept as a part of the text
  instead of being implicit parameters (an empty `<p></p>` threw an exception in v4).
- **`showCount="ifMany"` without `many`** is allowed (the plural form defaults to `singular + "s"`).
- **Duplicate token names** are detected like upstream (also between `fbt:name` and `fbt:param`).
- **Docblock options** (`@fbt {"project": "..."}`) only apply to the fbt calls of the file that declares them.
- The `fbt\fbt()` and `fbt\fbs()` namespaced functions, `fbtNamespace`, `fbtNode`, `fbtElement` and
  `FbtAutoWrap` are removed (the global `fbt()` / `fbs()` helpers are unchanged).
