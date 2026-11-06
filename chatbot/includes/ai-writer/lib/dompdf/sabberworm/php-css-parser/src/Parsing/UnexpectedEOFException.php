<?php

declare(strict_types=1);

namespace QuantumCloud\AiWriter\Vendor\Sabberworm\CSS\Parsing;

defined( 'ABSPATH' ) || exit;


/**
 * Thrown if the CSS parser encounters end of file it did not expect.
 *
 * Extends `UnexpectedTokenException` in order to preserve backwards compatibility.
 */
final class UnexpectedEOFException extends UnexpectedTokenException {}
