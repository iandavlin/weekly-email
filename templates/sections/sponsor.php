<?php
/**
 * Section template: Sponsor / Partner
 * Gold-bordered card with featured image, "Partner" label, and sponsor name.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$title   = esc_html( $item['title'] );
$url     = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$excerpt = esc_html( $item['excerpt'] ?? '' );

// Featured image
$img_url = $item['thumb_url'] ?? '';
if ( ! $img_url ) {
    $post = get_post( $item['id'] );
    if ( $post && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $m ) ) {
        $img_url = $m[1];
    }
}

// Sponsor page URL from ACF user field on the post author
$author_id   = (int) get_post_field( 'post_author', $item['id'] );
$sponsor_url = '';
if ( $author_id ) {
    if ( function_exists( 'get_field' ) ) {
        $sponsor_url = get_field( 'tlg_sponsor_page_url', 'user_' . $author_id );
    }
    if ( ! $sponsor_url ) {
        $sponsor_url = get_user_meta( $author_id, 'tlg_sponsor_page_url', true );
    }
}

// Sponsor name: linked to sponsor URL, or plain text fallback
if ( $sponsor_url ) {
    $sponsor_link = esc_url( LG_WD_Email_Builder::add_utm( $sponsor_url ) );
    $partner_label = '<a href="' . $sponsor_link . '" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1.5px;color:#ECB351;text-decoration:none;">' . $title . '</a>';
} else {
    $partner_label = '<span style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1.5px;color:#ECB351;">' . $title . '</span>';
}
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#FFF8EC;border:1px solid #ECB351;border-radius:8px;margin-bottom:10px;">
  <?php if ( $img_url ) : ?>
  <tr>
    <td style="padding:0;">
      <a href="<?php echo $url; ?>" style="display:block;line-height:0;border:0;outline:none;text-decoration:none;">
        <img src="<?php echo esc_url( $img_url ); ?>"
             width="520" height="293"
             style="display:block;width:100%;max-width:520px;height:auto;object-fit:cover;border-radius:8px 8px 0 0;border:0;"
             alt="">
      </a>
    </td>
  </tr>
  <?php endif; ?>
  <tr>
    <td style="padding:16px 18px;">
      <p style="margin:0 0 6px;"><?php echo $partner_label; ?></p>
      <p style="font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:700;color:#2B2318;margin:0 0 8px;"><?php echo $title; ?></p>
      <?php if ( $excerpt ) : ?>
      <p style="font-size:13px;color:#5C4E3A;margin:0 0 10px;line-height:1.6;"><?php echo $excerpt; ?></p>
      <?php endif; ?>
      <a href="<?php echo $url; ?>" style="font-size:12px;font-weight:600;color:#ECB351;text-decoration:none;">Learn more →</a>
    </td>
  </tr>
</table>
