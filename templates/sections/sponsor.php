<?php
/**
 * Section template: Sponsor / Partner
 * Gold-bordered card with "Partner" label.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$title   = esc_html( $item['title'] );
$url     = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$excerpt = esc_html( $item['excerpt'] ?? '' );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#FFF8EC;border:1px solid #ECB351;border-radius:8px;margin-bottom:10px;">
  <tr>
    <td style="padding:16px 18px;">
      <p style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1.5px;color:#ECB351;margin:0 0 6px;">Partner</p>
      <p style="font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:700;color:#2B2318;margin:0 0 8px;"><?php echo $title; ?></p>
      <?php if ( $excerpt ) : ?>
      <p style="font-size:13px;color:#5C4E3A;margin:0 0 10px;line-height:1.6;"><?php echo $excerpt; ?></p>
      <?php endif; ?>
      <a href="<?php echo $url; ?>" style="font-size:12px;font-weight:600;color:#ECB351;text-decoration:none;">Learn more →</a>
    </td>
  </tr>
</table>
