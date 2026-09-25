# Upgrading from 4.x to 5.0

FBT 5.0 is a port of the compiler of [Facebook's fbt v1.0.0](https://github.com/facebook/fbt/tree/babel-plugin-fbt-v1.0.0)
(`babel-plugin-fbt`, rewritten in 2021 around "fbt nodes"). The collected source strings and the
generated translations use the formats of Facebook's fbt, so they must be regenerated.

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

   The md5 hash of a text doesn't change, but it's now encoded in `base64` instead of `hex` by default,
   so translation keys are converted. The command reports translations of strings that
   changed in v5 and have to be translated again (e.g. inner strings of HTML elements nested in an fbt,
   whose descriptions now contain the whole enclosing string).

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
- **Hash keys** of translated payloads (`translatedFbts.json`) are computed from the texts and token
  aliases of all leaves. They're unchanged for plain strings and for strings without inner strings.

## Changed behaviors

- **String variations are no longer deduplicated by value.** When a phrase contains several plurals,
  enums or pronouns, the translations are generated for all combinations of their variations. If some of
  them depend on the same value (e.g. two plurals with the same `$count`), some combinations can never
  happen (`There is {number} likes`). v4 merged constructs whose values were equal, which also merged
  unrelated constructs with the same value (e.g. the `123` placeholder used by `collect-fbts`).
  In v5, constructs are merged only when they have the same `key` option:

  ```php
  fbt(
      'There ' . fbt::plural('is', $count, ['many' => 'are', 'key' => 'likes']) . ' ' .
      fbt::plural('a like', $count, ['showCount' => 'ifMany', 'many' => 'likes', 'key' => 'likes']),
      'likes'
  );
  ```

  generates only `There is a like` and `There are {number} likes`. Without `key`, all 4 combinations are
  generated. Facebook's fbt compiles JavaScript and merges constructs that use the same expression
  (e.g. `count`). PHP code is evaluated before fbt sees it, so the expression isn't known and `key`
  replaces it.

- **Strings of implicit parameters** are computed differently: an enum inside an HTML element
  produces variations (instead of an empty `{=}` token), and descriptions of inner strings contain the
  whole enclosing string (`In the phrase: "{name1} {=poked you}."` instead of `"{=} {=poked you}."`).
- **Empty HTML elements** (e.g. `<br>`, `<i class="icon"></i>`, `<p></p>`) are kept as a part of the text
  instead of being implicit parameters (an empty `<p></p>` threw an exception in v4).
- **`showCount="ifMany"` without `many`** is allowed (the plural form defaults to `singular + "s"`).
- **Duplicate token names** throw an exception, also when `fbt:name` and `fbt:param` use the same name.
- **Docblock options** (`@fbt {"project": "..."}`) only apply to the fbt calls of the file that declares them.
- The `fbt\fbt()` and `fbt\fbs()` namespaced functions, `fbtNamespace`, `fbtNode`, `fbtElement` and
  `FbtAutoWrap` are removed (the global `fbt()` / `fbs()` helpers are unchanged).
- **fbt constructs return call expressions.** `fbt::param()`, `fbt::plural()`, `fbt::enum()`,
  `fbt::name()`, `fbt::pronoun()` and `fbt::sameParam()` return an `FbtCallExpression` (the equivalent of
  the `fbt.param(...)` call of Facebook's fbt) instead of `<fbt:param>` markup. They still work in arrays
  and when concatenated with strings, but code that relies on the returned markup (or on a `string`
  return type) has to be updated. The callsite is compiled once for all of its runtime values.
- **Numeric strings are not formatted.** Like in Facebook's fbt, the value of `fbt::param()` (with the
  `number` option) or of the `value` option of `fbt::plural()` is formatted (e.g. `12,345`) only when it's a
  number: `fbt::param('zip', '12345', ['number' => true])` renders `12345`. Cast the value to a number to
  keep the formatting. Numeric values of HTML constructs (`<fbt:param number="true">12345</fbt:param>`) are
  still formatted.
- **`<fbt desc>` without a value** throws `<fbt> requires a "desc" attribute`.
- **Nested fbt constructs** in the functional form (e.g. `fbt::param('a', 'x ' . fbt::param('b', $y))`)
  throw an exception, like `<fbt:param>` nested in `<fbt:param>`.
- `FbtTransform::transform()` only accepts HTML documents (the `$functional` and `$values` arguments are
  removed); fbt() callsites use `FbtTransform::transformCallExpression()`.
