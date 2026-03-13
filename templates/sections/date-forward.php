<?php
/**
 * Section template: Date-forward (3-column layout)
 * Thumbnail | Date badge | Event details
 * Variables: $item (array), $settings (array)
 */
defined( 'ABSPATH' ) || exit;

$title    = esc_html( $item['title'] );
$url      = esc_url( LG_WD_Email_Builder::add_utm( $item['url'] ) );
$date_raw = get_post_meta( $item['id'], 'events_start_date_and_time_', true );
$time_raw = get_post_meta( $item['id'], 'time_of_event', true );
$zoom_url = get_post_meta( $item['id'], 'zoom_url_for_looth_group_virtual_event', true );

// Image: featured image first, then first <img> in post content
$img_url = $item['thumb_url'] ?? '';
if ( ! $img_url ) {
    $post = get_post( $item['id'] );
    if ( $post && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $m ) ) {
        $img_url = $m[1];
    }
}
// Use medium size for thumbnail column
if ( has_post_thumbnail( $item['id'] ) ) {
    $img_url = get_the_post_thumbnail_url( $item['id'], 'medium' );
}

// Parse event date
$display_date = '';
$month_short  = '';
$day_num      = '';
$day_of_week  = '';
$dt_start     = null;

if ( $date_raw ) {
    $tz = new DateTimeZone( LG_WD_TIMEZONE );
    $ts = DateTime::createFromFormat( 'Ymd', $date_raw, $tz );
    if ( $ts ) {
        $month_short  = strtoupper( $ts->format( 'M' ) );
        $day_num      = $ts->format( 'j' );
        $day_of_week  = $ts->format( 'l' );
        $display_date = $ts->format( 'l, F j, Y' );
        $dt_start     = $ts;
    }
}

if ( ! $display_date ) {
    $display_date = $item['date'];
}

// Build time string: "3pm EDT (7pm UTC)"
$time_display = '';
if ( $time_raw && $date_raw ) {
    $tz_eastern = new DateTimeZone( LG_WD_TIMEZONE );
    $tz_utc     = new DateTimeZone( 'UTC' );
    $datetime_str = $date_raw . ' ' . $time_raw;

    $formats = [ 'Ymd H:i:s', 'Ymd H:i', 'Ymd g:ia', 'Ymd g:i a', 'Ymd h:iA', 'Ymd h:i A' ];
    foreach ( $formats as $fmt ) {
        $dt = DateTime::createFromFormat( $fmt, $datetime_str, $tz_eastern );
        if ( $dt ) {
            $dt_start = clone $dt;

            $eastern_abbr = $dt->format( 'T' );
            $eastern_str  = strtolower( $dt->format( 'g' ) ) . $dt->format( ':i' );
            $eastern_str  = str_replace( ':00', '', $eastern_str );
            $eastern_str .= strtolower( $dt->format( 'A' ) ) . ' ' . $eastern_abbr;

            $dt->setTimezone( $tz_utc );
            $utc_str  = strtolower( $dt->format( 'g' ) ) . $dt->format( ':i' );
            $utc_str  = str_replace( ':00', '', $utc_str );
            $utc_str .= strtolower( $dt->format( 'A' ) ) . ' UTC';

            $time_display = $eastern_str . ' (' . $utc_str . ')';
            break;
        }
    }
} elseif ( $time_raw ) {
    $time_display = esc_html( $time_raw );
}

// Tier badge
$tiers    = wp_get_post_terms( $item['id'], 'event_tier_', [ 'fields' => 'names' ] );
$tier     = ( ! is_wp_error( $tiers ) && ! empty( $tiers ) ) ? $tiers[0] : '';
$location = $zoom_url ? 'Virtual Event' : 'In Person';

$tier_bg     = match ( strtolower( $tier ) ) { 'looth pro' => '#ECB351', 'looth lite' => '#D4E0B8', default => '#FAF6EE' };
$tier_color  = ( strtolower( $tier ) === 'looth pro' ) ? '#2B2318' : '#5C4E3A';
$tier_border = ( strtolower( $tier ) === 'public' ) ? 'border:1px solid #D4E0B8;' : '';
$tier_html   = $tier
    ? '<span style="display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;background:' . $tier_bg . ';color:' . $tier_color . ';' . $tier_border . 'margin-right:6px;">' . esc_html( $tier ) . '</span>'
    : '';

// Author
$author_html = LG_WD_Email_Builder::author_html( $item['id'] );

// Google Calendar link
$gcal_url = '';
if ( $dt_start ) {
    $dt_utc_start = clone $dt_start;
    $dt_utc_start->setTimezone( new DateTimeZone( 'UTC' ) );
    $gcal_start = $dt_utc_start->format( 'Ymd\THis\Z' );

    $dt_utc_end = clone $dt_utc_start;
    $dt_utc_end->modify( '+1 hour' );
    $gcal_end = $dt_utc_end->format( 'Ymd\THis\Z' );

    $gcal_params = [
        'action'   => 'TEMPLATE',
        'text'     => $item['title'],
        'dates'    => $gcal_start . '/' . $gcal_end,
        'details'  => $item['url'],
        'location' => $zoom_url ?: '',
    ];
    $gcal_url = 'https://calendar.google.com/calendar/render?' . http_build_query( $gcal_params );
}
?>
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:16px;margin-bottom:16px;">
  <tr>
    <!-- Thumbnail -->
    <?php if ( $img_url ) : ?>
    <td class="event-img-cell" width="200" valign="top" style="padding:0 14px 0 0;">
      <a href="<?php echo $url; ?>" style="display:block;line-height:0;">
        <img src="<?php echo esc_url( $img_url ); ?>"
             width="200" class="event-img"
             style="width:200px;max-width:100%;height:auto;border-radius:6px;display:block;"
             alt="<?php echo $title; ?>">
      </a>
    </td>
    <?php endif; ?>

    <!-- Date badge -->
    <?php if ( $month_short && $day_num ) : ?>
    <td class="date-badge" width="52" valign="top" style="padding:0 12px 0 0;">
      <div style="background:#2B2318;border-radius:6px;width:46px;text-align:center;padding:6px 0;">
        <span style="display:block;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;"><?php echo esc_html( $month_short ); ?></span>
        <span style="display:block;font-size:20px;font-weight:700;font-family:Georgia,serif;color:#ECB351;line-height:1.1;"><?php echo esc_html( $day_num ); ?></span>
      </div>
    </td>
    <?php endif; ?>

    <!-- Event details -->
    <td valign="top">
      <a href="<?php echo $url; ?>" class="event-title" style="font-family:Georgia,'Times New Roman',serif;font-size:18px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.35;margin-bottom:4px;"><?php echo $title; ?></a>
      <p class="event-date" style="font-size:14px;color:#5C4E3A;margin:0 0 4px;">
        <?php echo esc_html( $display_date ); ?>
        <?php if ( $time_display ) : ?>
          &middot; <?php echo $time_display; ?>
        <?php endif; ?>
      </p>
      <p class="event-meta" style="font-size:13px;color:#aaa;margin:0 0 6px;">
        <?php echo $tier_html; ?>
        <span style="color:#87986A;"><?php echo esc_html( $location ); ?></span>
        <?php if ( $author_html ) : ?>
          &middot; <?php echo $author_html; ?>
        <?php endif; ?>
      </p>
      <?php if ( $gcal_url ) : ?>
      <div class="gcal-wrap" style="margin-top:6px;">
        <a href="<?php echo esc_url( $gcal_url ); ?>"
           style="display:inline-block;font-size:12px;font-weight:600;color:#ECB351;text-decoration:none;padding:4px 12px;border:1px solid #ECB351;border-radius:12px;line-height:1.4;"
           target="_blank">&#128197; Add to Calendar</a>
      </div>
      <?php endif; ?>
    </td>
  </tr>
</table>
