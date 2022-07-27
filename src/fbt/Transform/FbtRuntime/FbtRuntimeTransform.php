<?php

namespace fbt\Transform\FbtRuntime;

use function fbt\invariant;

use fbt\Transform\FbtTransform\fbtHash;

class FbtRuntimeTransform
{
    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function transform(array $phrase): array
    {
        /**
         * JSFbtBuilder.js
         * ehk, reactNativeMode
         */

        if ($phrase['type'] === 'text') {
            $payload = $phrase['jsfbt'];
        } else {
            invariant($phrase['type'] === 'table', 'JSFbt only has 2 types');
            $payload = $phrase['jsfbt']['t'];
        }

        $hk = fbtHash::fbtHashKey($payload, $phrase['desc']);

        return [
            'hk' => $hk,
        ];
    }
}
