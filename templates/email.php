<?php
/**
 * Email template for LG Weekly Digest.
 * Variables available: $settings (array), $payload (array), $week_str (string)
 */
defined( 'ABSPATH' ) || exit;

$header_img_url = esc_url( $settings['header_image_url'] ?? '' );
$signoff        = nl2br( esc_html( $settings['signoff'] ?? '' ) );
$site_url       = esc_url( home_url() );
$unsubscribe    = '{{unsubscribe_url}}'; // FluentCRM smart code
$from_name      = esc_html( $settings['from_name'] ?? 'The Looth Group' );
$week_label     = esc_html( 'Week of ' . $week_str );
$item_count     = array_sum( array_map( fn( $p ) => count( $p['items'] ), $payload ) );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo esc_html( LG_WD_Email_Builder::build_subject( $payload ) ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#e8e2d8;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#e8e2d8;">
  <tr>
    <td align="center" style="padding:24px 16px;">

      <!-- Email container -->
      <table width="600" cellpadding="0" cellspacing="0" border="0"
             style="max-width:600px;width:100%;background-color:#FAF6EE;border-radius:8px;overflow:hidden;">

        <!-- ── HEADER ─────────────────────────────────────────── -->
        <tr>
          <td align="center" style="background-color:#2B2318;padding:28px 40px;">
            <?php if ( $header_img_url ) : ?>
              <img src="<?php echo $header_img_url; ?>" alt="<?php echo $from_name; ?>"
                   style="max-width:340px;width:100%;height:auto;display:block;margin:0 auto;">
            <?php else : ?>
              <p style="font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:700;
                         color:#ECB351;letter-spacing:4px;text-transform:uppercase;margin:0 0 6px;">
                THE LOOTH GROUP
              </p>
              <p style="font-size:11px;color:#87986A;letter-spacing:2px;text-transform:uppercase;margin:0;">
                <?php echo esc_html( $settings['branding_tagline'] ?? 'Guitar Repair & Restoration Community' ); ?>
              </p>
            <?php endif; ?>
          </td>
        </tr>

        <!-- ── HERO BAND ──────────────────────────────────────── -->
        <tr>
          <td style="background-color:#ECB351;padding:9px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td>
                  <span style="font-size:12px;font-weight:700;color:#2B2318;
                               text-transform:uppercase;letter-spacing:1.5px;">Weekly Digest</span>
                </td>
                <td align="right">
                  <span style="font-size:12px;color:#5C4E3A;">
                    <?php echo $week_label; ?> &middot; <?php echo $item_count; ?> items
                  </span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── BODY ───────────────────────────────────────────── -->
        <tr>
          <td style="padding:24px 40px 8px;">

            <!-- Intro line -->
            <p style="font-size:14px;color:#5C4E3A;line-height:1.6;margin:0 0 20px;
                       padding-bottom:16px;border-bottom:2px solid #ECB351;">
              <?php echo nl2br( esc_html( $settings['intro_text'] ?? '' ) ); ?>
            </p>

            <?php foreach ( $payload as $key => $data ) : ?>
              <?php if ( ! empty( $data['is_header'] ) ) : ?>
                <?php echo LG_WD_Email_Builder::render_group_header( $data['section']['label'] ); ?>
              <?php else : ?>
                <?php echo LG_WD_Email_Builder::render_section( $data ); ?>

                <!-- Divider between sections -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                  <tr>
                    <td height="1" style="background:linear-gradient(to right,transparent,#D4E0B8,transparent);font-size:0;line-height:0;">&nbsp;</td>
                  </tr>
                </table>
              <?php endif; ?>
            <?php endforeach; ?>

          </td>
        </tr>

        <!-- ── SIGN-OFF ────────────────────────────────────────── -->
        <tr>
          <td style="padding:8px 40px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="border-top:1px solid rgba(92,78,58,0.15);padding-top:18px;
                            text-align:center;">
                  <p style="font-size:13px;color:#5C4E3A;font-style:italic;line-height:1.6;margin:0;">
                    <?php echo $signoff; ?>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── FOOTER ─────────────────────────────────────────── -->
        <tr>
          <td style="background-color:#2B2318;padding:20px 40px;text-align:center;">
            <p style="font-family:Georgia,'Times New Roman',serif;color:#ECB351;
                       font-size:14px;letter-spacing:3px;margin:0 0 10px;">
              THE LOOTH GROUP
            </p>
            <p style="margin:0 0 10px;">
              <?php
              $footer_links = json_decode( $settings['footer_links'] ?? '[]', true );
              if ( ! is_array( $footer_links ) || empty( $footer_links ) ) {
                  // Fallback to default links
                  $footer_links = [
                      [ 'label' => 'Website', 'url' => home_url() ],
                      [ 'label' => 'Forum',   'url' => home_url( '/forum' ) ],
                      [ 'label' => 'Events',  'url' => home_url( '/events' ) ],
                      [ 'label' => 'Videos',  'url' => home_url( '/videos' ) ],
                  ];
              }
              foreach ( $footer_links as $fl ) :
                  $fl_url = esc_url( LG_WD_Email_Builder::add_utm( $fl['url'] ) );
              ?>
              <a href="<?php echo $fl_url; ?>" style="color:#87986A;font-size:11px;text-decoration:none;margin:0 8px;"><?php echo esc_html( $fl['label'] ); ?></a>
              <?php endforeach; ?>
            </p>
            <p style="font-size:10px;color:#5C4E3A;margin:0;line-height:1.6;">
              You&rsquo;re receiving this because you subscribed to the Looth Group weekly digest.<br>
              <a href="<?php echo $unsubscribe; ?>" style="color:#87986A;text-decoration:underline;">Unsubscribe</a>
              &nbsp;&middot;&nbsp; <?php echo $from_name; ?> &nbsp;&middot;&nbsp; loothgroup.com
            </p>
          </td>
        </tr>

      </table>
      <!-- /Email container -->

    </td>
  </tr>
</table>

</body>
</html>
