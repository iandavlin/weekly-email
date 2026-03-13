<?php
/**
 * Section template: Card
 * Full-width image on top, text below (stacked layout like forum).
 * Variables: $item (array), $settings (array), $hide_type_label (bool)
 */
defined( 'ABSPATH' ) || exit;

$show_thumb   = LG_WD_Settings::get( 'show_thumbnails' );
$show_excerpt = LG_WD_Settings::get( 'show_excerpts' );
$title        = esc_html( $item['title'] );
$url          = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$type_label   = esc_html( $item['type_label'] );
$date         = esc_html( $item['date'] );
// Manual (external) items carry their own author data; WP items use post lookup
if ( ! empty( $item['id'] ) ) {
    $author_html = LG_WD_Email_Builder::author_html( $item['id'] );
} elseif ( ! empty( $item['author_name'] ) ) {
    $a_name = esc_html( $item['author_name'] );
    $a_url  = ! empty( $item['author_url'] ) ? esc_url( LG_WD_Email_Builder::add_utm( $item['author_url'] ) ) : '';
    $author_html = $a_url
        ? 'By <a href="' . $a_url . '" style="color:#87986A;font-weight:600;text-decoration:none;">' . $a_name . '</a>'
        : 'By <strong style="color:#87986A;">' . $a_name . '</strong>';
} else {
    $author_html = '';
}
$excerpt      = $show_excerpt && ! empty( $item['excerpt'] )
    ? '<p class="card-excerpt" style="font-size:13px;color:#5C4E3A;margin:5px 0 0;line-height:1.6;">' . esc_html( $item['excerpt'] ) . '</p>'
    : '';

// Meta line: "By Author · Mar 12"
$meta_parts = [];
if ( $author_html ) $meta_parts[] = $author_html;
if ( $date ) $meta_parts[] = $date;
$meta_html = implode( ' &middot; ', $meta_parts );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:14px;margin-bottom:14px;">
  <tr>
    <td>
      <?php if ( $show_thumb && ! empty( $item['thumb_url'] ) ) : ?>
      <a href="<?php echo $url; ?>" style="display:block;margin-bottom:8px;line-height:0;">
        <img src="<?php echo esc_url( $item['thumb_url'] ); ?>"
             width="820" height="461"
             style="display:block;width:100%;max-width:820px;height:auto;object-fit:cover;border-radius:6px;"
             alt="">
      </a>
      <?php endif; ?>
      <?php if ( empty( $hide_type_label ) ) : ?>
      <p style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;margin:0 0 2px;"><?php echo $type_label; ?></p>
      <?php endif; ?>
      <a href="<?php echo $url; ?>" class="card-title" style="font-family:Georgia,'Times New Roman',serif;font-size:16px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.4;margin-bottom:4px;"><?php echo $title; ?></a>
      <?php echo $excerpt; ?>
      <p class="card-meta" style="font-size:11px;color:#aaa;margin:5px 0 0;"><?php echo $meta_html; ?></p>
    </td>
  </tr>
</table>
