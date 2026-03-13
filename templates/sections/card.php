<?php
/**
 * Section template: Card
 * Full-width 16:9 image on top, title + excerpt + meta below.
 * Variables: $item (array), $settings (array), $hide_type_label (bool)
 */
defined( 'ABSPATH' ) || exit;

$show_thumb   = LG_WD_Settings::get( 'show_thumbnails' );
$show_excerpt = LG_WD_Settings::get( 'show_excerpts' );
$title        = esc_html( $item['title'] );
$url          = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$type_label   = esc_html( $item['type_label'] );
$date         = esc_html( $item['date'] );

// Author attribution
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

$excerpt = $show_excerpt && ! empty( $item['excerpt'] )
    ? '<p class="card-excerpt" style="font-size:14px;color:#5C4E3A;margin:6px 0 0;line-height:1.55;">' . esc_html( $item['excerpt'] ) . '</p>'
    : '';

// Meta line: "By Author · Mar 12"
$meta_parts = [];
if ( $author_html ) $meta_parts[] = $author_html;
if ( $date ) $meta_parts[] = $date;
$meta_html = implode( ' &middot; ', $meta_parts );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:16px;margin-bottom:16px;">
  <tr>
    <td>
      <?php if ( $show_thumb && ! empty( $item['thumb_url'] ) ) : ?>
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px;">
        <tr>
          <td align="left" style="line-height:0;">
            <a href="<?php echo $url; ?>" style="line-height:0;">
              <img src="<?php echo esc_url( $item['thumb_url'] ); ?>"
                   width="720" class="img-cap"
                   style="max-width:100%;max-height:405px;width:auto;height:auto;border-radius:6px;"
                   alt="">
            </a>
          </td>
        </tr>
      </table>
      <?php endif; ?>
      <?php if ( empty( $hide_type_label ) ) : ?>
      <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;margin:0 0 3px;"><?php echo $type_label; ?></p>
      <?php endif; ?>
      <a href="<?php echo $url; ?>" class="card-title" style="font-family:Georgia,'Times New Roman',serif;font-size:18px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.35;margin-bottom:4px;"><?php echo $title; ?></a>
      <?php echo $excerpt; ?>
      <p class="card-meta" style="font-size:12px;color:#aaa;margin:6px 0 0;"><?php echo $meta_html; ?></p>
    </td>
  </tr>
</table>
