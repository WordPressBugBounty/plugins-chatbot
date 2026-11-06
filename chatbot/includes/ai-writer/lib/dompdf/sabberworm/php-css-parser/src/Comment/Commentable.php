<?php

declare(strict_types=1);

namespace QuantumCloud\AiWriter\Vendor\Sabberworm\CSS\Comment;

defined( 'ABSPATH' ) || exit;


/**
 * A standard implementation of this interface is available in the `CommentContainer` trait.
 */
interface Commentable
{
    /**
     * @param list<Comment> $comments
     */
    public function addComments(array $comments): void;

    /**
     * @return list<Comment>
     */
    public function getComments(): array;

    /**
     * @param list<Comment> $comments
     */
    public function setComments(array $comments): void;
}
