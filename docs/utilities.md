# intlNumUtils and intlSummarizeNumber

There are a few utilities in both `intlNumUtils` and
`intlSummarizeNumber` that are documented in the source.

In fact `fbt::param` and `fbt::plural` default to displaying numbers
using `intlNumUtils::formatNumberWithThousandDelimiters`.
You can override this behavior in `fbt:param` by setting the
[number option](params.md#optional-attributes) and using your own
string in the replacement.

You can override this in `fbt::plural` [by providing the `value`
option](plurals.md#optional-arguments).

# createElement

We use this function internally to generate HTML for FBT.

```php
\fbt\createElement('div', 'content', ['id' => 'container']);
```
