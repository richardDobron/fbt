<?php

namespace fbt\Transform\FbtTransform\Translate;

use function fbt\invariant;

class FbtSiteMetaEntry extends FbtSiteMetaEntryBase
{
    /** @var array<int, string>|null */
    private $_range;

    public function __construct(?int $type, ?string $token, ?array $range)
    {
        parent::__construct($type, $token);
        $this->_range = $range;
    }

    public function hasVariationMask(): bool
    {
        return self::getVariationMaskFromType($this->type) !== null;
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public function getVariationMask(): ?int
    {
        invariant(
            $this->hasVariationMask(),
            'check hasVariationMask to avoid this invariant'
        );

        return self::getVariationMaskFromType($this->type);
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function wrap(array $entry): self
    {
        self::_validate($entry);

        return new self(
            ($entry['type'] ?? null) ?: null,
            $entry['token'] ?? null,
            ($entry['range'] ?? null) ?: null
        );
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public function unwrap(): array
    {
        $token = $this->token;
        $type = $this->type;

        if ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['NUMBER']) {
            $entry = ['type' => $type];
            if ($token !== null) {
                $entry['token'] = $token;
            }

            return $entry;
        }

        if ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER']) {
            invariant($token !== null, 'token should be specified for gender variation');

            return ['type' => $type, 'token' => $token];
        }

        if ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['PRONOUN']) {
            return ['type' => $type];
        }

        invariant($this->_range !== null, 'range should be specified for enum variation');

        return ['range' => $this->_range];
    }

    /**
     * @throws \fbt\Exceptions\FbtException
     */
    public static function _validate(array $entry): void
    {
        $type = ($entry['type'] ?? null) ?: null;
        $token = $entry['token'] ?? null;
        $range = ($entry['range'] ?? null) ?: null;

        if ($type === null) {
            invariant(
                $range !== null,
                'if no type is provided, this must be enum variation and thus range must be specified '
            );
        } elseif ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['GENDER']) {
            invariant(
                $token !== null,
                'token should be specified for gender variation'
            );
        } elseif ($type === IntlVariations::INTL_FBT_VARIATION_TYPE['PRONOUN']) {
            invariant(
                $token === null,
                'token should not be specified for pronoun variation'
            );
        }
    }
}
