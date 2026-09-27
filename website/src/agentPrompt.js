export default `Add internationalization to this PHP app with fbt (docs: https://richarddobron.github.io/fbt/). For a Laravel app,
use laravel-fbt instead (https://richarddobron.github.io/laravel-fbt/).

1. Install it with \`composer require richarddobron/fbt\`.
2. Configure it at the start of the application: \`\fbt\FbtConfig::set('author', ...)\`, \`'project'\`, \`'path'\` (storage
   of the collected texts and translations) and \`'locale'\` (the locale of the current request).
3. Make the user class implement \`fbt\Lib\IntlViewerContextInterface\` (\`getLocale()\` and \`getGender()\`) and set it
   as the viewer context with \`\fbt\FbtConfig::set('viewerContext', $user)\`, so that texts use the locale and the
   gender of the signed-in user.
4. Wrap every user-facing text in \`fbt('Text', 'Description')\`, or in \`<fbt desc="Description">Text</fbt>\` between
   \`fbtTransform()\` and \`endFbtTransform()\` in templates. Use \`fbs()\` for plain text (e.g. HTML attributes).
   Every text needs a description which explains its context to translators.
5. Never concatenate translated fragments, keep whole sentences in one fbt. Use \`fbt::param()\` for values,
   \`fbt::plural()\` for counts, \`fbt::name()\` for names of people (with their gender), \`fbt::enum()\` and
   \`fbt::pronoun()\`.
6. Collect the texts with \`php ./vendor/bin/fbt collect-fbts --path=<path> --src=<source directory>\`, generate the
   missing translations with \`php ./vendor/bin/fbt generate-translations\` and build the translations with
   \`php ./vendor/bin/fbt translate --path=<path> --translations=<translation files>\`.`;
