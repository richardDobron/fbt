<?php

namespace fbt;

class fbs extends fbt
{
    /* @var string */
    protected static $moduleName = 'fbs';

    /**
     * @param string|array $text
     * @param string|array|null $description - the options of a common string can be passed instead
     * @param array $options
     */
    public function __construct(
        $text,
        $description = null,
        array $options = []
    ) {
        if (is_array($description) && empty($options)) {
            $options = $description;
            $description = null;
        }

        parent::__construct($text, $description === '' ? null : $description, $options);
    }
}
