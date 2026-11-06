# Dompdf for AI Writer (scoped)

Used by AI Writer to create the checklist PDF download (CR-16). Built by `tools/dompdf/build.php` from the Composer packages below; do not edit by hand. Every class is moved into the `QuantumCloud\AiWriter\Vendor` namespace so it cannot clash with another plugin's Dompdf. Only the files needed to render a PDF are kept, with DejaVu Sans (regular and bold) and the PDF core font metrics.

Rebuild: `cd tools/dompdf && composer install`, then `npm run vendor:dompdf` from the repository root.

| Package | Version | Licence | Source |
| --- | --- | --- | --- |
| dompdf/dompdf | v3.1.6 | LGPL-2.1 | https://github.com/dompdf/dompdf |
| dompdf/php-font-lib | 1.0.2 | LGPL-2.1-or-later | https://github.com/dompdf/php-font-lib |
| dompdf/php-svg-lib | 1.0.2 | LGPL-3.0-or-later | https://github.com/dompdf/php-svg-lib |
| masterminds/html5 | 2.12.0 | MIT | https://github.com/Masterminds/html5-php |
| sabberworm/php-css-parser | v9.5.0 | MIT | https://github.com/MyIntervals/PHP-CSS-Parser |

The licence of each package is in its folder. All are GPL-compatible: LGPL-2.1 and LGPL-3.0-or-later (php-svg-lib) libraries may be combined with GPL-2.0-or-later code, the combination being distributed under GPL-3.0; MIT is permissive. DejaVu fonts: Bitstream Vera / DejaVu licence (free).
