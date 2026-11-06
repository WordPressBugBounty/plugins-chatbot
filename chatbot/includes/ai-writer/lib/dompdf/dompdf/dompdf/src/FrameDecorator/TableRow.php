<?php
/**
 * @package dompdf
 * @link    https://github.com/dompdf/dompdf
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace QuantumCloud\AiWriter\Vendor\Dompdf\FrameDecorator;

defined( 'ABSPATH' ) || exit;


use QuantumCloud\AiWriter\Vendor\Dompdf\Dompdf;
use QuantumCloud\AiWriter\Vendor\Dompdf\Frame;

/**
 * Decorates Frames for table row layout
 *
 * @package dompdf
 */
class TableRow extends AbstractFrameDecorator
{
    /**
     * TableRow constructor.
     * @param Frame $frame
     * @param Dompdf $dompdf
     */
    function __construct(Frame $frame, Dompdf $dompdf)
    {
        parent::__construct($frame, $dompdf);
    }
}
