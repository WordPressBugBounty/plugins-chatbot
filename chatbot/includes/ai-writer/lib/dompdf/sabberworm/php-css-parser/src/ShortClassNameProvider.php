<?php

declare(strict_types=1);

namespace QuantumCloud\AiWriter\Vendor\Sabberworm\CSS;

defined( 'ABSPATH' ) || exit;


/**
 * Provides a method to obtain the short name of the instantiated class (i.e. without namespace prefix).
 *
 * @internal
 */
trait ShortClassNameProvider
{
    /**
     * @return non-empty-string
     */
    private function getShortClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
