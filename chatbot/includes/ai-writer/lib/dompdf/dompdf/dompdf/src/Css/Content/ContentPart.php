<?php
namespace QuantumCloud\AiWriter\Vendor\Dompdf\Css\Content;

defined( 'ABSPATH' ) || exit;


abstract class ContentPart
{
    public function equals(self $other): bool
    {
        return $other instanceof static;
    }
}
