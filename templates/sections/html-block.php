<?php
/**
 * Section template: HTML Block (custom message)
 * Renders user-supplied HTML content directly into the email.
 * Sanitized via wp_kses with email-safe tags.
 *
 * Variables: $item (array with 'html_content' key), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$html = $item['html_content'] ?? '';
if ( ! $html ) return;

// Allowed HTML tags safe for email clients
$allowed = [
    'p'      => [ 'style' => [] ],
    'div'    => [ 'style' => [] ],
    'span'   => [ 'style' => [] ],
    'strong' => [ 'style' => [] ],
    'b'      => [],
    'em'     => [ 'style' => [] ],
    'i'      => [],
    'a'      => [ 'href' => [], 'style' => [], 'target' => [] ],
    'br'     => [],
    'ul'     => [ 'style' => [] ],
    'ol'     => [ 'style' => [] ],
    'li'     => [ 'style' => [] ],
    'h1'     => [ 'style' => [] ],
    'h2'     => [ 'style' => [] ],
    'h3'     => [ 'style' => [] ],
    'h4'     => [ 'style' => [] ],
    'img'    => [ 'src' => [], 'alt' => [], 'width' => [], 'height' => [], 'style' => [] ],
    'table'  => [ 'width' => [], 'cellpadding' => [], 'cellspacing' => [], 'border' => [], 'style' => [], 'align' => [] ],
    'tr'     => [ 'style' => [] ],
    'td'     => [ 'width' => [], 'style' => [], 'valign' => [], 'align' => [], 'colspan' => [] ],
    'th'     => [ 'width' => [], 'style' => [], 'valign' => [], 'align' => [], 'colspan' => [] ],
    'hr'     => [ 'style' => [] ],
    'blockquote' => [ 'style' => [] ],
];

$content = wp_kses( $html, $allowed );
?>
<div style="font-size:15px;color:#5C4E3A;line-height:1.65;margin-bottom:12px;">
  <?php echo $content; ?>
</div>
