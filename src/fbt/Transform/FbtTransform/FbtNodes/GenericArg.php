<?php

namespace fbt\Transform\FbtTransform\FbtNodes;

/**
 * Special fbt argument that does NOT produce string variations.
 *
 * E.g.
 *
 *    <fbt:plural
 *      count="<?= $numParticipants ?>"              <-- NumberStringVariationArg
 *      value="<?= formatted($numParticipants) ?>"   <-- GenericArg (used for UI display only)
 *      showCount="yes"
 *    >
 *      challenger
 *    </fbt:plural>
 */
class GenericArg extends FbtArgumentBase
{
}
