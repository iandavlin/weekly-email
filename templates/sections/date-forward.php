<?php
/**
 * Section template: Date-forward
 * Prominent date block on the left — ideal for events.
 * Reads event-specific meta directly. Falls back gracefully if fields are absent.
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$title    = esc_html( $item['title'] );
$url      = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$date_raw = get_post_meta( $item['id'], 'events_start_date_and_time_', true );
$time_raw = get_post_meta( $item['id'], 'time_of_event', true );
$zoom_url = get_post_meta( $item['id'], 'zoom_url_for_looth_group_virtual_event', true );

$month_short  = '';
$day_num      = '';
$display_date = '';

if ( $date_raw ) {
    $ts = DateTime::createFromFormat( 'Ymd', $date_raw, new DateTimeZone( LG_WD_TIMEZONE ) );
    if ( $ts ) {
        $month_short  = $ts->format( 'M' );
        $day_num      = $ts->format( 'j' );
        $display_date = $ts->format( 'l, F j' );
    }
}

// Fallback to publish date if no event date meta
if ( ! $display_date ) {
    $display_date = $item['date'];
}

$tiers    = wp_get_post_terms( $item['id'], 'event_tier_', [ 'fields' => 'names' ] );
$tier     = ( ! is_wp_error( $tiers ) && ! empty( $tiers ) ) ? $tiers[0] : '';
$location = $zoom_url ? 'Virtual' : ( $date_raw ? 'In Person' : '' );

$tier_bg     = match ( strtolower( $tier ) ) { 'looth pro' => '#ECB351', 'looth lite' => '#D4E0B8', default => '#FAF6EE' };
$tier_color  = ( strtolower( $tier ) === 'looth pro' ) ? '#2B2318' : '#5C4E3A';
$tier_border = ( strtolower( $tier ) === 'public' ) ? 'border:1px solid #D4E0B8;' : '';
$tier_html   = $tier
    ? '<span style="display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;background:' . $tier_bg . ';color:' . $tier_color . ';' . $tier_border . '">' . esc_html( $tier ) . '</span>'
    : '';

$meta_parts = array_filter( [ esc_html( $display_date ), esc_html( $time_raw ), esc_html( $location ) ] );
$meta_line  = implode( ' &middot; ', $meta_parts );
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:10px;margin-bottom:10px;">
  <tr>
    <?php if ( $month_short && $day_num ) : ?>
    <td width="52" valign="top" style="padding:0 14px 0 0;">
      <div style="background:#2B2318;border-radius:6px;width:46px;height:46px;text-align:center;padding-top:6px;">
        <span style="display:block;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;"><?php echo esc_html( $month_short ); ?></span>
        <span style="display:block;font-size:20px;font-weight:700;font-family:Georgia,serif;color:#ECB351;line-height:1.1;"><?php echo esc_html( $day_num ); ?></span>
      </div>
    </td>
    <?php endif; ?>
    <td valign="top">
      <a href="<?php echo $url; ?>" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;color:#2B2318;text-decoration:none;display:block;margin-bottom:3px;"><?php echo $title; ?></a>
      <p style="font-size:12px;color:#5C4E3A;margin:0 0 5px;"><?php echo $meta_line; ?></p>
      <?php echo $tier_html; ?>
    </td>
  </tr>
</table>
