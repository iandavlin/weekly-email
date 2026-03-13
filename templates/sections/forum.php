<?php
/**
 * Section template: Forum
 * 16:9 image (featured or first content image), title, excerpt, author + reply count.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$title  = esc_html( $item['title'] );
$url    = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$date   = esc_html( $item['date'] );

// Image: featured first, then first <img> in post content
$img_url = $item['thumb_url'] ?? '';
if ( ! $img_url ) {
    $post = get_post( $item['id'] );
    if ( $post && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $m ) ) {
        $img_url = $m[1];
    }
}

// Excerpt
$show_excerpt = LG_WD_Settings::get( 'show_excerpts' );
$excerpt      = $show_excerpt && ! empty( $item['excerpt'] )
    ? '<p style="font-size:12px;color:#5C4E3A;margin:5px 0 0;line-height:1.5;">' . esc_html( $item['excerpt'] ) . '</p>'
    : '';

// bbPress meta
$author_html = LG_WD_Email_Builder::author_html( $item['id'] );
$reply_count = 0;
if ( function_exists( 'bbp_get_topic_reply_count' ) ) {
    $reply_count = (int) bbp_get_topic_reply_count( $item['id'] );
}
$meta = [];
if ( $author_html ) $meta[] = $author_html;
if ( $reply_count ) $meta[] = $reply_count . ( $reply_count === 1 ? ' reply' : ' replies' );
$meta[] = $date;
$meta_html = implode( ' &middot; ', $meta );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:12px;margin-bottom:12px;">
  <tr>
    <td>
      <?php if ( $img_url ) : ?>
      <a href="<?php echo $url; ?>" style="display:block;margin:0 auto 8px;line-height:0;max-width:820px;border-radius:6px;text-align:center;">
        <img src="<?php echo esc_url( $img_url ); ?>"
             width="820"
             style="display:block;max-width:820px;height:auto;border-radius:6px;margin:0 auto;"
             alt="">
      </a>
      <?php endif; ?>
      <a href="<?php echo $url; ?>" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.4;"><?php echo $title; ?></a>
      <?php echo $excerpt; ?>
      <p style="font-size:11px;color:#aaa;margin:5px 0 0;"><?php echo $meta_html; ?></p>
    </td>
  </tr>
</table>
