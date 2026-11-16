<?php
/**
 * Trait for normalizing line endings of plain text email bodies
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Controller
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace ExtendedFeedback\Controller;

/**
 * Trait for normalizing line endings of plain text email bodies
 *
 * Symfony Mailer encodes plain text bodies as quoted-printable using PHP's
 * quoted_printable_encode(), which only resets its line length counter on CRLF.
 * With bare LF, soft line breaks end up in arbitrary places (e.g. directly before
 * a hard line break or inside URLs), which some mail clients render incorrectly.
 *
 * @category VuFind
 * @package  Controller
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait NormalizeLineEndingsTrait
{
    /**
     * Convert all line endings to CRLF.
     *
     * @param string $text Text
     *
     * @return string
     */
    protected function normalizeLineEndings(string $text): string
    {
        return preg_replace('/\r\n|\r|\n/', "\r\n", $text);
    }
}
