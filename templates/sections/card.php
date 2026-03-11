<?php
/**
 * Section template: Card
 * Standard post card with optional thumbnail and excerpt.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$show_thumb   = LG_WD_Settings::get( 'show_thumbnails' );
$show_excerpt = LG_WD_Settings::get( 'show_excerpts' );
$title        = esc_html( $item['title'] );
$url          = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$type_label   = esc_html( $item['type_label'] );
$date         = esc_html( $item['date'] );
$excerpt      = $show_excerpt && ! empty( $item['excerpt'] )
    ? '<p style="font-size:12px;color:#5C4E3A;margin:3px 0 0;line-height:1.5;">' . esc_html( $item['excerpt'] ) . '</p>'
    : '';

$thumb_cell = '';
if ( $show_thumb && ! empty( $item['thumb_url'] ) ) {
    $thumb_url  = esc_url( $item['thumb_url'] );
    $thumb_cell = '<td width="80" valign="top" style="padding:0 14px 0 0;">'
        . '<img src="' . $thumb_url . '" width="80" height="56" style="display:block;border-radius:4px;object-fit:cover;" alt="">'
        . '</td>';
}
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:10px;margin-bottom:10px;">
  <tr>
    <?php echo $thumb_cell; ?>
    <td valign="top" style="padding:0;">
      <p style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;margin:0 0 2px;"><?php echo $type_label; ?></p>
      <a href="<?php echo $url; ?>" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.3;margin-bottom:2px;"><?php echo $title; ?></a>
      <?php echo $excerpt; ?>
      <p style="font-size:11px;color:#aaa;margin:4px 0 0;">Published <?php echo $date; ?></p>
    </td>
  </tr>
</table>
