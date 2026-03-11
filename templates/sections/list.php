<?php
/**
 * Section template: List
 * Compact rows, no image. Works well for forum topics.
 * Pulls bbPress reply count and author if available.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$title  = esc_html( $item['title'] );
$url    = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$date   = esc_html( $item['date'] );
$author = esc_html( get_the_author_meta( 'display_name', get_post_field( 'post_author', $item['id'] ) ) );

$reply_count = 0;
if ( function_exists( 'bbp_get_topic_reply_count' ) ) {
    $reply_count = (int) bbp_get_topic_reply_count( $item['id'] );
}

$meta = [];
if ( $author )       $meta[] = 'By <strong style="color:#87986A;">' . $author . '</strong>';
if ( $reply_count )  $meta[] = $reply_count . ( $reply_count === 1 ? ' reply' : ' replies' );
$meta[] = $date;
$meta_html = implode( ' &middot; ', $meta );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:8px;margin-bottom:8px;">
  <tr>
    <td>
      <a href="<?php echo $url; ?>" style="font-size:13px;font-weight:500;color:#2B2318;text-decoration:none;display:block;margin-bottom:3px;"><?php echo $title; ?></a>
      <p style="font-size:11px;color:#aaa;margin:0;"><?php echo $meta_html; ?></p>
    </td>
  </tr>
</table>
