/**
 * Copyright (c) 2017-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @noflow
 * @emails oncall+internationalization
 */

import React from "react";
import classnames from "classnames";
import useDocusaurusContext from "@docusaurus/useDocusaurusContext";
import useBaseUrl from "@docusaurus/useBaseUrl";
import styles from "../pages/styles.module.css";

const Showcase = ({ showAll = false }) => {
  const { siteConfig = {} } = useDocusaurusContext();
  const { users } = siteConfig.customFields;

  const showcase = (showAll ? users : users.filter(user => user.pinned)).map(
    (user, i) => {
      return (
        <a key={i} className={styles.showcaseLogo} href={user.infoUrl}>
          <img src={useBaseUrl(user.imageUrl)} title={user.caption} />
        </a>
      );
    }
  );

  return (
    <section
      className={classnames("text--center margin-top--xl", styles.showcase)}
    >
      <h2
        className={classnames("showcaseHeading", styles.showcaseHeadingColored)}
      >
          From whom does it come?
      </h2>
      <div className={styles.showcaseLogos}>{showcase}</div>
    </section>
  );
};

export default Showcase;
