<?php
namespace QuantumCloud\AiWriter\Vendor\Dompdf\Css\Content;

defined( 'ABSPATH' ) || exit;


final class OpenQuote extends ContentPart
{
    public function __toString(): string
    {
        return "open-quote";
    }
}
