<?php
namespace QuantumCloud\AiWriter\Vendor\Dompdf\Css\Content;

defined( 'ABSPATH' ) || exit;


final class Url extends ContentPart
{
    /**
     * @var string
     */
    public $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function equals(ContentPart $other): bool
    {
        return $other instanceof self
            && $other->url === $this->url;
    }

    public function __toString(): string
    {
        return "url(\"" . str_replace("\"", "\\\"", $this->url) . "\")";
    }
}
