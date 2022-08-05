/**
 * Copyright (c) 2017-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @noflow
 * @emails oncall+internationalization
 */

module.exports = {
  title: "FBT for PHP",
  tagline: "An Internationalization Framework for PHP.",
  url: "https://richarddobron.github.io",
  baseUrl: "/fbt/",
  favicon: "img/favicon_blue.png",
  projectName: "fbt",
  organizationName: "richardDobron",
  scripts: ["https://buttons.github.io/buttons.js"],
  customFields: {
    users: [
      {
        caption: "Facebook",
        imageUrl: "img/flogo_RGB_HEX-72.svg",
        infoUrl: "https://www.facebook.com",
        pinned: true
      }
    ]
  },
  themeConfig: {
    navbar: {
      title: "FBT",
      logo: {
        alt: "FBT Logo",
        src: "img/fbt.png"
      },
      items: [
        { to: "docs/getting_started", label: "Docs", position: "right" },
        {
          href: "https://github.com/richardDobron/fbt",
          label: "GitHub",
          position: "right"
        }
      ]
    },
    footer: {
      style: "dark",
        logo: {
            alt: "Richard's Blog",
            src: "/img/blog.svg",
            href: "https://dobron.showwcase.com/"
        },
      copyright: `Copyright © ${new Date().getFullYear()} Richard Dobroň & Meta Platforms, Inc. and affiliates.`,
      links: [
        {
          title: "Docs",
          items: [
            { label: "Getting Started", to: "docs/getting_started" },
            {
              label: "API Reference",
              to: "docs/api_intro"
            }
          ]
        },
        {
          title: "Community",
          items: [
            {
              label: "Stack Overflow",
              href: "https://stackoverflow.com/questions/tagged/fbt"
            }
          ]
        },
        {
          title: "FBT",
          items: [
            {
              label: "JavaScript",
              href: "https://github.com/facebook/fbt"
            },
            {
              label: "Laravel 5.0+",
              href: "https://github.com/richardDobron/laravel-fbt"
            }
          ]
        },
      ]
    },
    image: "img/fbt.png",
    algolia: {
      apiKey: "9a5a805d18c37abc7339b217ec941de4",
      indexName: "fbt",
    },
  },
  presets: [
    [
      "@docusaurus/preset-classic",
      {
        docs: {
          path: "../docs",
          sidebarPath: require.resolve("./sidebars.js"),
          showLastUpdateAuthor: true,
          showLastUpdateTime: true
        },
        theme: {
          customCss: require.resolve("./src/css/custom.css")
        }
      }
    ]
  ]
};
