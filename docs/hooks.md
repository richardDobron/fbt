---
id: hooks
title: Runtime Hooks
sidebar_label: Runtime Hooks
---

The runtime behavior can be customized by registering hooks with `FbtHooks::register($name, $callback)`
(and removed again with `FbtHooks::unregister($name)`).

```php
use fbt\Runtime\Shared\FbtHooks;
```

### getTranslatedInput

Looks up the translated payload of an fbt call. Return `null` to fall back to the source text.
By default, translations registered with `FbtTranslations::registerTranslations()` are used.

```php
FbtHooks::register('getTranslatedInput', function (array $input): ?array {
    // $input = ['table' => ..., 'args' => ..., 'options' => ['hk' => <hash key>, ...]]
    $translation = MyTranslations::find(FbtHooks::locale(), $input['options']['hk'] ?? null);

    return $translation === null ? null : [
        'table' => $translation,
        'args' => $input['args'],
    ];
});
```

### getFbtResult / getFbsResult

Build the object returned for `fbt()` / `fbs()` calls. By default, `FbtResult` (or `InlineFbtResult`
in [inline mode](inline_translating.md)) and `FbtPureStringResult` are used.

```php
FbtHooks::register('getFbtResult', function (array $input) {
    // $input = [
    //   'contents' => [...],
    //   'errorListener' => ?IFbtErrorListener,
    //   'extraOptions' => ?array,
    //   'patternString' => string,
    //   'patternHash' => ?string,
    //   'reporting' => bool,
    // ]
    return \fbt\Runtime\Shared\FbtResult::get($input);
});
```

### errorListener

Returns an `IFbtErrorListener` that is notified when a result contains contents which cannot be
converted to a string. It can also implement the optional `onMissingParameterError()` method, which is
called when a token of the string has no parameter (the token is then rendered as an empty string).
Without it, a missing parameter throws an exception in debug mode.

```php
use fbt\Runtime\Shared\IFbtErrorListener;

FbtHooks::register('errorListener', function (array $context): ?IFbtErrorListener {
    // $context = ['hash' => ?string, 'translation' => string]
    return new class ($context) implements IFbtErrorListener {
        private $context;

        public function __construct(array $context)
        {
            $this->context = $context;
        }

        public function onStringSerializationError($content): void
        {
            error_log('Cannot serialize fbt content of "' . $this->context['translation'] . '"');
        }

        // optional
        public function onMissingParameterError(array $providedParamNames, string $missingParamName): void
        {
            error_log('Missing fbt parameter "' . $missingParamName . '" of "' . $this->context['translation'] . '"');
        }
    };
});
```

### logImpression

Called with the hash of every displayed string that has a hash (e.g. translated strings), the input
table and the keys used to access it.

```php
FbtHooks::register('logImpression', function (string $hash, ?array $options = null): void {
    // $options = ['inputTable' => string|array, 'tokens' => array<string|int>]
    // ...
});
```

### onTranslationOverride

Called when a string was replaced by an override from `FbtQTOverrides::$overrides`.

```php
FbtHooks::register('onTranslationOverride', function (string $hash): void {
    // ...
});
```

### canInline

See [Inline Translating](inline_translating.md#excluded-translations).
