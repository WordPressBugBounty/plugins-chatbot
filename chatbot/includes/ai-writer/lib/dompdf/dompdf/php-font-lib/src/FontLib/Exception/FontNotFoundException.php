<?php

namespace QuantumCloud\AiWriter\Vendor\FontLib\Exception;

defined( 'ABSPATH' ) || exit;


class FontNotFoundException extends \Exception
{
    public function __construct($fontPath)
    {
        $this->message = 'Font not found in: ' . $fontPath;
    }
}