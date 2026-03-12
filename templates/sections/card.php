<?php
/**
 * Section template: Card
 * Standard post card with optional thumbnail and excerpt.
 * Variables: $item (array), $settings (array), $hide_type_label (bool)
 */
defined( 'ABSPATH' ) || exit;

$show_thumb   = LG_WD_Settings::get( 'show_thumbnails' );
$show_excerpt = LG_WD_Settings::get( 'show_excerpts' );
$title        = esc_html( $item['title'] );
$url          = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$type_label   = esc_html( $item['type_label'] );
$date         = esc_html( $item['date'] );
$author_html  = LG_WD_Email_Builder::author_html( $item['id'] );
$excerpt      = $show_excerpt && ! empty( $item['excerpt'] )
    ? '<p style="font-size:12px;color:#5C4E3A;margin:3px 0 0;line-height:1.5;">' . esc_html( $item['excerpt'] ) . '</p>'
    : '';

$thumb_cell = '';
if ( $show_thumb && ! empty( $item['thumb_url'] ) ) {
    $thumb_url  = esc_url( $item['thumb_url'] );
    $thumb_cell = '<td width="160" valign="top" style="padding:0 14px 0 0;">'
        . '<a href="' . $url . '" style="display:block;line-height:0;border:0;outline:none;text-decoration:none;">'
        . '<img src="' . $thumb_url . '" width="160" height="90" style="display:block;border-radius:4px;object-fit:cover;border:0;" alt="">'
        . '</a>'
        . '</td>';
}

// Meta line: "By Author · Mar 12"
$meta_parts = [];
if ( $author_html ) $meta_parts[] = $author_html;
$meta_parts[] = $date;
$meta_html = implode( ' &middot; ', $meta_parts );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:10px;margin-bottom:10px;">
  <tr>
    <?php echo $thumb_cell; ?>
    <td valign="top" style="padding:0;">
      <?php if ( empty( $hide_type_label ) ) : ?>
      <p style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;margin:0 0 2px;"><?php echo $type_label; ?></p>
      <?php endif; ?>
      <a href="<?php echo $url; ?>" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.3;margin-bottom:2px;"><?php echo $title; ?></a>
      <?php echo $excerpt; ?>
      <p style="font-size:11px;color:#aaa;margin:4px 0 0;"><?php echo $meta_html; ?></p>
    </td>
  </tr>
</table>
