<?php
/**
 * Section template: Full Text
 * Outputs the post's WYSIWYG content directly. Ideal for the Email Append CPT.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$post = get_post( $item['id'] );
if ( ! $post ) return;

// Allowed HTML tags safe for email clients
$allowed = [
    'p'      => [ 'style' => [] ],
    'strong' => [],
    'b'      => [],
    'em'     => [],
    'i'      => [],
    'a'      => [ 'href' => [], 'style' => [] ],
    'br'     => [],
    'ul'     => [ 'style' => [] ],
    'ol'     => [ 'style' => [] ],
    'li'     => [ 'style' => [] ],
    'h2'     => [ 'style' => [] ],
    'h3'     => [ 'style' => [] ],
    'img'    => [ 'src' => [], 'alt' => [], 'width' => [], 'height' => [], 'style' => [] ],
];

$content = wp_kses( apply_filters( 'the_content', $post->post_content ), $allowed );
?>
<div style="font-size:14px;color:#5C4E3A;line-height:1.7;margin-bottom:10px;">
  <?php echo $content; ?>
</div>
