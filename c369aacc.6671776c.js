(window.webpackJsonp=window.webpackJsonp||[]).push([[16],{77:function(e,t,n){"use strict";n.r(t),n.d(t,"frontMatter",(function(){return b})),n.d(t,"metadata",(function(){return l})),n.d(t,"rightToc",(function(){return i})),n.d(t,"default",(function(){return s}));var a=n(1),r=n(6),o=(n(0),n(87)),b={id:"getting_started",title:"Integrating into your app",sidebar_label:"Getting started"},l={unversionedId:"getting_started",id:"getting_started",isDocsHomePage:!1,title:"Integrating into your app",description:"We recommend you read the best practices for advice on how to best prepare your applications. We strongly encourage you to do so.",source:"@site/..\\docs\\getting_started.md",slug:"/getting_started",permalink:"/fbt/docs/getting_started",version:"current",lastUpdatedBy:"Richard Dobro\u0148",lastUpdatedAt:1670779354,sidebar_label:"Getting started",sidebar:"docs",next:{title:"Platform Internationalization Best Practices",permalink:"/fbt/docs/best_practices"}},i=[{value:"\ud83d\udce6 Installing",id:"-installing",children:[]},{value:"\ud83d\udd27 Configuration",id:"-configuration",children:[{value:"Options",id:"options",children:[]}]},{value:"\ud83d\ude4b IntlInterface",id:"-intlinterface",children:[]},{value:"\ud83d\ude80  Commands",id:"--commands",children:[]},{value:"\ud83d\udcd8 API",id:"-api",children:[]},{value:"\ud83c\udfa8 Example Usage",id:"-example-usage",children:[{value:"fbtTransform() &amp; endFbtTransform()",id:"fbttransform--endfbttransform",children:[]},{value:"fbt()",id:"fbt",children:[]}]}],c={rightToc:i};function s(e){var t=e.components,n=Object(r.a)(e,["components"]);return Object(o.b)("wrapper",Object(a.a)({},c,n,{components:t,mdxType:"MDXLayout"}),Object(o.b)("p",null,"We recommend you read the ",Object(o.b)("a",Object(a.a)({parentName:"p"},{href:"/fbt/docs/best_practices"}),"best practices")," for advice on how to best prepare your applications. We strongly encourage you to do so."),Object(o.b)("h2",{id:"-installing"},"\ud83d\udce6 Installing"),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-shell"}),"$ composer require richarddobron/fbt\n")),Object(o.b)("p",null,"Add this lines to your code:"),Object(o.b)("ul",null,Object(o.b)("li",{parentName:"ul"},Object(o.b)("em",{parentName:"li"},"We recommend setting the ",Object(o.b)("strong",{parentName:"em"},"author"),", ",Object(o.b)("strong",{parentName:"em"},"project")," and ",Object(o.b)("strong",{parentName:"em"},"path")," options."))),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"<?php\n// require (\"vendor/autoload.php\");\n\n\\fbt\\FbtConfig::set('author', 'your name');\n\\fbt\\FbtConfig::set('project', 'project');\n\\fbt\\FbtConfig::set('path', '/path/to/storage');\n")),Object(o.b)("h2",{id:"-configuration"},"\ud83d\udd27 Configuration"),Object(o.b)("h3",{id:"options"},"Options"),Object(o.b)("p",null,"The following options can be defined:"),Object(o.b)("ul",null,Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"project")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"website app"),") Project to which the text belongs"),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"author")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": Text author"),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"preserveWhitespace")," ",Object(o.b)("inlineCode",{parentName:"li"},"bool"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"false"),")",Object(o.b)("ul",{parentName:"li"},Object(o.b)("li",{parentName:"ul"},"FBT normally consolidates whitespace down to one space (",Object(o.b)("inlineCode",{parentName:"li"},"' '"),")."),Object(o.b)("li",{parentName:"ul"},"Turn this off by setting this to ",Object(o.b)("inlineCode",{parentName:"li"},"true")))),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"viewerContext")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"\\fbt\\Runtime\\Shared\\IntlViewerContext::class"),")"),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"locale")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"en_US"),") User locale."),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"fbtCommon")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"[]"),") common string's, e.g. ",Object(o.b)("inlineCode",{parentName:"li"},"[['text' => 'desc'], ...]")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"fbtCommonPath")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"null"),") Path to the common string's module."),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"path")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": Cache storage path for generated translations & source strings.")),Object(o.b)("p",null,"Below are the less important parameters."),Object(o.b)("ul",null,Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"collectFbt")," ",Object(o.b)("inlineCode",{parentName:"li"},"bool"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"true"),") Collect fbt instances from the source and store them to a JSON file."),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"hash_module")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"md5"),") Hash module."),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"md5_digest")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"hex"),") MD5 digest."),Object(o.b)("li",{parentName:"ul"},Object(o.b)("strong",{parentName:"li"},"driver")," ",Object(o.b)("inlineCode",{parentName:"li"},"string"),": (Default: ",Object(o.b)("inlineCode",{parentName:"li"},"json"),") Currently, only JSON storage is supported.")),Object(o.b)("h2",{id:"-intlinterface"},"\ud83d\ude4b IntlInterface"),Object(o.b)("p",null,"Optional implementation of IntlInterface on UserDTO."),Object(o.b)("p",null,"Example code:"),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"<?php\n\nnamespace App;\n\nuse fbt\\Transform\\FbtTransform\\Translate\\IntlVariations;\nuse fbt\\Lib\\IntlViewerContextInterface;\nuse fbt\\Runtime\\Gender;\n\nclass UserDTO implements IntlViewerContextInterface\n{\n    public function getLocale(): ?string\n    {\n        return $this->locale;\n    }\n\n    public static function getGender(): int\n    {\n        if ($this->gender === 'male') {\n            return IntlVariations::GENDER_MALE;\n        }\n\n        if ($this->gender === 'female') {\n            return IntlVariations::GENDER_FEMALE;\n        }\n\n        return IntlVariations::GENDER_UNKNOWN;\n    }\n}\n")),Object(o.b)("p",null,"After implementation, set ",Object(o.b)("inlineCode",{parentName:"p"},"viewerContext"),":"),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"$loggedUserDto = ...;\n\n\\fbt\\FbtConfig::set('viewerContext', $loggedUserDto)\n")),Object(o.b)("h2",{id:"--commands"},"\ud83d\ude80  Commands"),Object(o.b)("ol",null,Object(o.b)("li",{parentName:"ol"},"This command collects FBT strings across whole application in PHP files.")),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-shell"}),"php ./vendor/bin/fbt collect-fbts --path=./path/to/fbt/ --src=./path/to/project/\n")),Object(o.b)("p",null,"Read more about ",Object(o.b)("a",Object(a.a)({parentName:"p"},{href:"/fbt/docs/collection"}),"FBTs extracting"),"."),Object(o.b)("ol",{start:2},Object(o.b)("li",{parentName:"ol"},"This command generates the missing translation hashes from collected source strings.")),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-shell"}),"php ./vendor/bin/fbt generate-translations --source=./path/to/fbt/.source_strings.json --translations=./path/to/fbt/*.json\n")),Object(o.b)("ol",{start:3},Object(o.b)("li",{parentName:"ol"},"This command creates translation payloads stored in JSON file.")),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-shell"}),"php ./vendor/bin/fbt translate --path=./path/to/fbt/ --translations=./path/to/fbt/*.json\n")),Object(o.b)("p",null,"Read more about ",Object(o.b)("a",Object(a.a)({parentName:"p"},{href:"/fbt/docs/translating"}),"translating"),"."),Object(o.b)("h2",{id:"-api"},"\ud83d\udcd8 API"),Object(o.b)("ul",null,Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/api_intro"}),"fbt(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/params"}),"fbt::param(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/enums"}),"fbt::enum(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/params"}),"fbt::name(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/plurals"}),"fbt::plural(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/pronouns"}),"fbt::pronoun(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/params"}),"fbt::sameParam(...);")),Object(o.b)("li",{parentName:"ul"},Object(o.b)("a",Object(a.a)({parentName:"li"},{href:"/fbt/docs/common"}),"fbt::c(...);"))),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"echo fbt('You just friended ' . \\fbt\\fbt::name('name', 'Sarah', 2 /* gender */), 'names');\n")),Object(o.b)("h2",{id:"-example-usage"},"\ud83c\udfa8 Example Usage"),Object(o.b)("h3",{id:"fbttransform--endfbttransform"},"fbtTransform() & endFbtTransform()"),Object(o.b)("p",null,Object(o.b)("strong",{parentName:"p"},"fbtTransform()"),": ",Object(o.b)("em",{parentName:"p"},"This function will turn output buffering on. While output buffering is active no output is sent from the script (other than headers), instead the output is stored in an internal buffer.")),Object(o.b)("p",null,Object(o.b)("strong",{parentName:"p"},"endFbtTransform()"),": ",Object(o.b)("em",{parentName:"p"},"This function will send the contents of the topmost output buffer (if any) and turn this output buffer off.")),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),'<?php fbtTransform(); ?>\n   ...\n   <fbt desc="auto-wrap example">\n     Go on an\n     <a href="#">\n       <span>awesome</span> vacation\n     </a>\n   </fbt>\n   ...\n<?php endFbtTransform(); ?>\n\n// result: Go on an <a href="#"><span>awesome</span> vacation</a>\n')),Object(o.b)("h3",{id:"fbt"},"fbt()"),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"fbt(\n [\n  'Go on an ',\n  \\fbt\\createElement('a', \\fbt\\createElement('span', 'awesome'), ['href' => '#']),\n  ' vacation',\n ],\n 'It\\'s simple',\n ['project' => \"foo\"]\n)\n\n// result: Go on an <a href=\"#\"><span>awesome</span> vacation</a>\n")),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"fbt('You just friended ' . \\fbt\\fbt::name('name', 'Sarah', 2 /* gender */), 'names')\n\n// result: You just friended Sarah\n")),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),"fbt('A simple string', 'It\\'s simple', ['project' => \"foo\"])\n\n// result: A simple string\n")))}s.isMDXComponent=!0},87:function(e,t,n){"use strict";n.d(t,"a",(function(){return p})),n.d(t,"b",(function(){return f}));var a=n(0),r=n.n(a);function o(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function b(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(e);t&&(a=a.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),n.push.apply(n,a)}return n}function l(e){for(var t=1;t<arguments.length;t++){var n=null!=arguments[t]?arguments[t]:{};t%2?b(Object(n),!0).forEach((function(t){o(e,t,n[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):b(Object(n)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))}))}return e}function i(e,t){if(null==e)return{};var n,a,r=function(e,t){if(null==e)return{};var n,a,r={},o=Object.keys(e);for(a=0;a<o.length;a++)n=o[a],t.indexOf(n)>=0||(r[n]=e[n]);return r}(e,t);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(e);for(a=0;a<o.length;a++)n=o[a],t.indexOf(n)>=0||Object.prototype.propertyIsEnumerable.call(e,n)&&(r[n]=e[n])}return r}var c=r.a.createContext({}),s=function(e){var t=r.a.useContext(c),n=t;return e&&(n="function"==typeof e?e(t):l(l({},t),e)),n},p=function(e){var t=s(e.components);return r.a.createElement(c.Provider,{value:t},e.children)},m={inlineCode:"code",wrapper:function(e){var t=e.children;return r.a.createElement(r.a.Fragment,{},t)}},u=r.a.forwardRef((function(e,t){var n=e.components,a=e.mdxType,o=e.originalType,b=e.parentName,c=i(e,["components","mdxType","originalType","parentName"]),p=s(n),u=a,f=p["".concat(b,".").concat(u)]||p[u]||m[u]||o;return n?r.a.createElement(f,l(l({ref:t},c),{},{components:n})):r.a.createElement(f,l({ref:t},c))}));function f(e,t){var n=arguments,a=t&&t.mdxType;if("string"==typeof e||a){var o=n.length,b=new Array(o);b[0]=u;var l={};for(var i in t)hasOwnProperty.call(t,i)&&(l[i]=t[i]);l.originalType=e,l.mdxType="string"==typeof e?e:a,b[1]=l;for(var c=2;c<o;c++)b[c]=n[c];return r.a.createElement.apply(null,b)}return r.a.createElement.apply(null,n)}u.displayName="MDXCreateElement"}}]);