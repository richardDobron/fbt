# Implicit parameters

## Viewer Gender
### The hidden `__viewing_user__` token

If a token of `__viewing_user__` is provided, it is expected to have the
corresponding [`type`](translation.md) of `IntlVariations::GENDER_*`. When
provided, at [translation](translation.md) time, `JSFbtBuilder` will
create a special key in its table payload which signals to the runtime
to check the gender of `IntlVariations::GENDER` in order to variate on
gender.

## Subject
### The hidden `__subject__` token

Similar to [viewer gender](implicit_params.md#viewer-gender), this is an
implicit variation based on gender.  Whether the variation is provided
is determined at the [translation](translating.md) level.  A translator
may choose to variate on `__subject__` or not.

Unlike viewer gender, subject requires that the `fbt` callsite provide it via the [optional argument](api_intro#optional-attributes)

E.g

```
<fbt
  subject="<?=$subjectGender?>" 
  desc="<?='There is an implicit actor here. ' .
        "Like: '{name} translated your string.'"?>">
  translated your string.
</fbt>
```
The above will variate correctly, provided there are separate translations for the `__subject__` token.
