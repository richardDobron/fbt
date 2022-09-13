# Platform Internationalization Best Practices

Here are some rough guidelines and lessons learned by the internationalization team at Facebook. These are in no specific order of importance.

## Be Descriptive
~~The general rule we use is text under 20 characters needs to have a description.~~ A word like "Poke" can vary if it is used as a noun or a verb. Facebook Translations works by creating a hash value from the text and description of the phrase. That means that even a slight change to the original text or description will cause your string to be counted as a completely new one. So err on the side of starting off with a complete description you won't have to clarify later.

Note that many translators prefer to use the bulk translation interface, so they will not see your text in the context of your application -- that means your descriptions need to give translators all the information they need to make correct translation decisions.

For example, do this:

```php
fbt("Name:", "Label for name of photo album")
```

Instead of this:

```php
fbt("Name:", "")
```

In some languages, the word for *name* is different depending on whether it's the name of a person, a place, or an object. A description here allows a translator to choose the correct word for this label.

Descriptions should usually indicate context as well as meaning (but see the next point). This is especially important for things like link text that are presented as part of a larger grammatical structure like a sentence.

So do this:

```php
fbt("{name}'s photos", "In, 'X's photos are ready to view.'")
```

But not this:

```php
fbt("{name}'s photos", "")
```

In languages where nouns change depending on whether they're used as the subject or object of a sentence, this description will allow translators to use the correct form.

## Reuse Common Elements
You should reuse common text and descriptions rather than repeating the same text over and over with different descriptions; it's less work for translators and will tend to result in higher-quality translations. This is sometimes slightly at odds with using specific descriptions; use your judgment about where to draw the line.

So this:

```php
fbt("Cancel", "Button/link: cancel an action")
```

Is usually better than this:

```php
fbt("Cancel", "Button label: cancel sending a message to event owner")
```

## Avoid Translating Markup
If you have two sentences and a `<br />` in between, split them up into two translatable phrases. Otherwise translators will be able to mess with your markup and the results may not be what you expect.

However, if you really want to use a `<br />`, we recommend doing it this way:
```html
<fbt desc="Multiple lines">
    first line<fbt:param name="lineBreak"><br /></fbt:param>
    second line<fbt:same-param name="lineBreak" />
    last line.
</fbt>
```

## Use CSS instead of Markup
Use CSS rather than markup to confine text to particular parts of the page. (See also the next item.) For example, if you have the text "Next Page" and you want each word on a separate line, put it in a with a maximum width rather than putting a tag in between the two words. Don't split the text into separately translatable units since it will prevent translators from changing word order if needed.

**Don't** do this:

```php
fbt("Next<br/>Page", "...")
```
Because a translator may ignore your formatting.

And **don't** do this:
```php
fbt("Next", "...") . '<br/>' . fbt("Page", "...");
```
If a language needs the word for "Page" to come before the word for "Next", it is impossible to translate correctly.

Rather, do **this**:
```html
<div class="limited-width-box">' . fbt("Next Page", "...") . '</div>
```
With appropriate CSS, the browser will word-wrap the string appropriately.

## Avoid Layouts Relying on Precise Sizing
Try not to use layouts that depend on the precise onscreen sizes of pieces of text in the original language. For any piece of text, in some languages it is likely to be shorter and in some it will be longer (sometimes significantly so in either direction.) If you have sized your user interface elements such that your text just barely fits, your application will probably not work well in a language with longer words.

## Avoid Long Pieces of Text
Large chunks of text like multiple paragraphs should be split up among multiple `<fbt>` tags for ease of translation. Similarly, a single long paragraph should be broken up into several smaller paragraphs. This allows translation voting to more precisely pinpoint problems.

## Assume Word Order Will Change
Assume that a translator will have to change the word order of every sentence. In particular, don't try to assemble sentences from smaller separately-translatable fragments, because even if you provide excellent descriptions, it's likely you will make it impossible for a translator to come up with a grammatically correct translation. Instead, expand all the possible cases out into separate translatable sentences and choose a complete sentence in your code.

Here's a simple example to **avoid**:
```html
<fbt desc="...">You are eating</fbt> <fbt desc="...">at home.</fbt>
<fbt desc="...">You are eating</fbt> <fbt desc="...">at a restaurant.</fbt>
```

Here the code is printing the beginning of the sentence, which doesn't change in English, then choosing one of two possible endings. This is impossible to translate correctly to Chinese, where the phrases for "at home" and "at a restaurant" need to come before the word for "eating".

In this case, use separate phrases:
```html
<fbt desc="...">You are eating at home.</fbt>
<fbt desc="...">You are eating at a restaurant.</fbt>
```

Here the code chooses one of two complete sentences. The translator can adjust the word order of both sentences as needed, and these can be correctly translated into every language.

Along the lines of the previous item, if you have a phrase like "You have {number} photos." where you use the word "photo" when the number is 1, expand this out into separate complete sentences line, "You have one photo." and "You have {number} photos.", like this:
```html
<fbt desc="...">You have <fbt:plural many="photos" showCount="ifMany" count="3">one photo</fbt:plural>.</fbt>
```

## Avoid Tiny Fonts
Font sizes under 10 pixels can be difficult to read in some languages, especially Chinese and Japanese.

## Don't Hardcode Punctuation
Different languages use different punctuation symbols; for example, Chinese has two different comma characters that are used in different contexts. In general if you allow translators to translate complete sentences (including periods and commas) this won't be as big an issue for you.

So you **should** include the punctuation within the fbt tags:

```html
<fbt desc="...">You have mail.</fbt>
```

**Don't** exclude it from the tags:

```html
<fbt desc="...">You have mail</fbt>.
```
Japanese translators, among others, will want to use their language's end-of-sentence character, which is not an English-style period.

Similarly, you **should** do this:
```html
<fbt desc="Form label">Favorite color:</fbt> <input ...>
```
And **not** do this:
```html
<fbt desc="Form label">Favorite color</fbt>: <input ...>
```
Including the colon as part of the translatable string means translators can substitute another punctuation mark if applicable, or can insert whitespace between the text and the colon (as is done in French, for example.)

## Using Icons Instead of Images with Text
Using icons rather than images with prerendered text can sometimes save you the trouble of having to generate your graphics in different languages. But be aware that some symbols are culture-specific and may not mean the same thing to people in different countries -- for example, a hand with a raised thumb indicates "good" in some cultures but is an obscene gesture in others. An icon whose meaning is obscure is actually worse than using untranslated text, since the latter can at least be looked up in a dictionary as a last resort.

Source: http://wiki.developers.facebook.com/index.php/Platform_Internationalization_Best_Practices
