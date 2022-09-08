(window.webpackJsonp=window.webpackJsonp||[]).push([[12],{73:function(e,t,n){"use strict";n.r(t),n.d(t,"frontMatter",(function(){return i})),n.d(t,"metadata",(function(){return c})),n.d(t,"rightToc",(function(){return l})),n.d(t,"default",(function(){return s}));var a=n(1),r=n(6),o=(n(0),n(86)),i={id:"collection",title:"Extracting FBTs",sidebar_label:"Extracting translatable texts"},c={unversionedId:"collection",id:"collection",isDocsHomePage:!1,title:"Extracting FBTs",description:"We provide collect-fbts as a utility for collecting strings.",source:"@site/..\\docs\\collection.md",slug:"/collection",permalink:"/fbt/docs/collection",version:"current",lastUpdatedBy:"Richard Dobro\u0148",lastUpdatedAt:1662668790,sidebar_label:"Extracting translatable texts",sidebar:"docs",previous:{title:"Transforms",permalink:"/fbt/docs/transform"},next:{title:"Translating",permalink:"/fbt/docs/translating"}},l=[{value:"Options:",id:"options",children:[]},{value:"A note on hashes",id:"a-note-on-hashes",children:[]}],b={rightToc:l};function s(e){var t=e.components,n=Object(r.a)(e,["components"]);return Object(o.b)("wrapper",Object(a.a)({},b,n,{components:t,mdxType:"MDXLayout"}),Object(o.b)("p",null,"We provide ",Object(o.b)("inlineCode",{parentName:"p"},"collect-fbts")," as a utility for collecting strings."),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-shell"}),"php ./vendor/bin/fbt collect-fbts --path=./path/to/project/storage/ --src=./path/to/project/\n")),Object(o.b)("h3",{id:"options"},"Options:"),Object(o.b)("table",null,Object(o.b)("thead",{parentName:"table"},Object(o.b)("tr",{parentName:"thead"},Object(o.b)("th",Object(a.a)({parentName:"tr"},{align:null}),"name"),Object(o.b)("th",Object(a.a)({parentName:"tr"},{align:null}),"default"),Object(o.b)("th",Object(a.a)({parentName:"tr"},{align:null}),"description"))),Object(o.b)("tbody",{parentName:"table"},Object(o.b)("tr",{parentName:"tbody"},Object(o.b)("td",Object(a.a)({parentName:"tr"},{align:null}),"--src=",Object(o.b)("inlineCode",{parentName:"td"},"[path]")),Object(o.b)("td",Object(a.a)({parentName:"tr"},{align:null}),Object(o.b)("em",{parentName:"td"},"none")),Object(o.b)("td",Object(a.a)({parentName:"tr"},{align:null}),"Cache storage path for source strings")),Object(o.b)("tr",{parentName:"tbody"},Object(o.b)("td",Object(a.a)({parentName:"tr"},{align:null}),"--path=",Object(o.b)("inlineCode",{parentName:"td"},"[path]")),Object(o.b)("td",Object(a.a)({parentName:"tr"},{align:null}),Object(o.b)("em",{parentName:"td"},"none")),Object(o.b)("td",Object(a.a)({parentName:"tr"},{align:null}),"The directory where you want to scan usages of fbt in php files.")))),Object(o.b)("p",null,"\u26a0\ufe0f Unlike Facebook's version of fbt, we primarily collect ",Object(o.b)("inlineCode",{parentName:"p"},"<fbt>")," & translate strings during script execution."),Object(o.b)("p",null,"Upon successful execution, the output of the ",Object(o.b)("inlineCode",{parentName:"p"},"/your/path/to/fbt/.source_strings.json")," will be in the following format:"),Object(o.b)("pre",null,Object(o.b)("code",Object(a.a)({parentName:"pre"},{className:"language-php"}),'[\n  "phrases": [\n    [\n      "hashToText": [\n        <hash>: <text>,\n        ...\n      ],\n      "type": "text" | "table",\n      "desc": <description>,\n      "project": <project>,\n      "jsfbt": string | [\'t\' => <table>, \'m\' => <metadata>],\n    ]\n  ],\n  "childParentMappings" => [\n    <childIdx>: <parentIdx>\n  ]\n}\n')),Object(o.b)("p",null,Object(o.b)("inlineCode",{parentName:"p"},"phrases")," here represents all the ",Object(o.b)("em",{parentName:"p"},"source")," information we need to\nprocess and produce an ",Object(o.b)("inlineCode",{parentName:"p"},"fbt::_(...)")," callsite's final payload.  When\ncombined with corresponding translations to each ",Object(o.b)("inlineCode",{parentName:"p"},"hashToText")," entry we\ncan produce the translated payloads ",Object(o.b)("inlineCode",{parentName:"p"},"fbt::_()")," expects."),Object(o.b)("p",null,"When it comes to moving from source text to translations, what is most\npertinent is the ",Object(o.b)("inlineCode",{parentName:"p"},"hashToText")," payload containing all relevant texts\nwith their identifying hash.  You can choose ",Object(o.b)("inlineCode",{parentName:"p"},"md5")," or ",Object(o.b)("inlineCode",{parentName:"p"},"tiger")," hash module.  It defaults to md5."),Object(o.b)("h3",{id:"a-note-on-hashes"},"A note on hashes"),Object(o.b)("p",null,"In the FBT framework, there are 2 main places we uses hashes for\nidentification: ",Object(o.b)("strong",{parentName:"p"},"text")," and ",Object(o.b)("strong",{parentName:"p"},"fbt callsite"),".  The ",Object(o.b)("inlineCode",{parentName:"p"},"hashToText")," mapping\nabove represents the hash of the ",Object(o.b)("strong",{parentName:"p"},"text")," and its ",Object(o.b)("strong",{parentName:"p"},"description"),".  This is used\nwhen ",Object(o.b)("em",{parentName:"p"},"building")," the translated payloads."),Object(o.b)("p",null,"The hash of the callsite (defaulting to ",Object(o.b)("inlineCode",{parentName:"p"},"jenkins")," hash) is used to\nlook up the payload in\n",Object(o.b)("a",Object(a.a)({parentName:"p"},{href:"https://github.com/richardDobron/fbt/blob/main/src/fbt/Runtime/FbtTranslations.php"}),Object(o.b)("inlineCode",{parentName:"a"},"FbtTranslations")),".\nThis is basically the hash of the object you see in ",Object(o.b)("inlineCode",{parentName:"p"},"jsfbt"),"."),Object(o.b)("p",null,"See ",Object(o.b)("a",Object(a.a)({parentName:"p"},{href:"/fbt/docs/translating"}),"Translating FBTs")," for getting your translations in\nthe right format."))}s.isMDXComponent=!0},86:function(e,t,n){"use strict";n.d(t,"a",(function(){return p})),n.d(t,"b",(function(){return h}));var a=n(0),r=n.n(a);function o(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function i(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(e);t&&(a=a.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),n.push.apply(n,a)}return n}function c(e){for(var t=1;t<arguments.length;t++){var n=null!=arguments[t]?arguments[t]:{};t%2?i(Object(n),!0).forEach((function(t){o(e,t,n[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):i(Object(n)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))}))}return e}function l(e,t){if(null==e)return{};var n,a,r=function(e,t){if(null==e)return{};var n,a,r={},o=Object.keys(e);for(a=0;a<o.length;a++)n=o[a],t.indexOf(n)>=0||(r[n]=e[n]);return r}(e,t);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(e);for(a=0;a<o.length;a++)n=o[a],t.indexOf(n)>=0||Object.prototype.propertyIsEnumerable.call(e,n)&&(r[n]=e[n])}return r}var b=r.a.createContext({}),s=function(e){var t=r.a.useContext(b),n=t;return e&&(n="function"==typeof e?e(t):c(c({},t),e)),n},p=function(e){var t=s(e.components);return r.a.createElement(b.Provider,{value:t},e.children)},d={inlineCode:"code",wrapper:function(e){var t=e.children;return r.a.createElement(r.a.Fragment,{},t)}},u=r.a.forwardRef((function(e,t){var n=e.components,a=e.mdxType,o=e.originalType,i=e.parentName,b=l(e,["components","mdxType","originalType","parentName"]),p=s(n),u=a,h=p["".concat(i,".").concat(u)]||p[u]||d[u]||o;return n?r.a.createElement(h,c(c({ref:t},b),{},{components:n})):r.a.createElement(h,c({ref:t},b))}));function h(e,t){var n=arguments,a=t&&t.mdxType;if("string"==typeof e||a){var o=n.length,i=new Array(o);i[0]=u;var c={};for(var l in t)hasOwnProperty.call(t,l)&&(c[l]=t[l]);c.originalType=e,c.mdxType="string"==typeof e?e:a,i[1]=c;for(var b=2;b<o;b++)i[b]=n[b];return r.a.createElement.apply(null,i)}return r.a.createElement.apply(null,n)}u.displayName="MDXCreateElement"}}]);