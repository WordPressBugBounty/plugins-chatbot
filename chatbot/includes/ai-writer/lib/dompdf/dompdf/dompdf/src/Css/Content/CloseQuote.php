<?php
namespace QuantumCloud\AiWriter\Vendor\Dompdf\Css\Content;

defined( 'ABSPATH' ) || exit;


final class CloseQuote extends ContentPart
{
    public function __toString(): string
    {
        return "close-quote";
    }
}
