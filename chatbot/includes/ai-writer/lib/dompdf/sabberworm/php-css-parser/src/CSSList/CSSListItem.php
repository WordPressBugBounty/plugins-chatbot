<?php

declare(strict_types=1);

namespace QuantumCloud\AiWriter\Vendor\Sabberworm\CSS\CSSList;

defined( 'ABSPATH' ) || exit;


use QuantumCloud\AiWriter\Vendor\Sabberworm\CSS\Comment\Commentable;
use QuantumCloud\AiWriter\Vendor\Sabberworm\CSS\Renderable;

/**
 * Represents anything that can be in the `$contents` of a `CSSList`.
 *
 * The interface does not define any methods to implement.
 * It's purpose is to allow a single type to be specified for `CSSList::$contents` and manipulation methods thereof.
 * It extends `Commentable` and `Renderable` because all `CSSListItem`s are both.
 * This allows implementations to call methods from those interfaces without any additional type checks.
 */
interface CSSListItem extends Commentable, Renderable {}
