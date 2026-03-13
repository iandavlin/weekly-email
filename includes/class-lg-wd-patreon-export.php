<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Patreon_Export
 *
 * Renders an issue as clean, semantic HTML that can be copied from a browser
 * window and pasted into Patreon's rich text editor with formatting preserved.
 */
class LG_WD_Patreon_Export {

    const CAP = 'manage_options';

    public static function init(): void {
        add_action( 'wp_ajax_lg_wd_compose_export_patreon', [ __CLASS__, 'ajax_export' ] );
    }

    // ── AJAX handler ──────────────────────────────────────────────────────────

    public static function ajax_export(): void {
        check_ajax_referer( 'lg_wd_compose', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_die( 'Unauthorized' );

        $issue_id = absint( $_GET['issue_id'] ?? $_POST['issue_id'] ?? 0 );
        if ( ! $issue_id ) wp_die( 'No issue ID.' );

        $issue_data = LG_WD_Issue::get_data( $issue_id );
        if ( ! $issue_data ) wp_die( 'Issue not found.' );

        $payload = LG_WD_Query::build_payload_from_issue( $issue_data );
        if ( empty( $payload ) ) wp_die( 'No content in this issue.' );

        $issue_title = get_the_title( $issue_id );
        $html = self::render( $payload, $issue_title );

        // Output as a full HTML page the user can copy from
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo $html;
        exit;
    }

    // ── Renderer ──────────────────────────────────────────────────────────────

    public static function render( array $payload, string $issue_title ): string {
        $site_url = esc_url( home_url() );

        $body = '';

        foreach ( $payload as $key => $data ) {
            if ( ! empty( $data['is_header'] ) ) {
                // Group header → H2
                $label = esc_html( self::strip_emoji( $data['section']['label'] ) );
                $body .= '<h2>' . $label . '</h2>' . "\n";
            } else {
                $body .= self::render_section( $data );
            }
        }

        // Build the full page
        $title_html = esc_html( $issue_title );
        $week_str   = date_i18n( 'F j, Y' );

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patreon Export — {$title_html}</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 720px;
    margin: 0 auto;
    padding: 40px 24px;
    color: #2B2318;
    line-height: 1.6;
    background: #FAF6EE;
  }
  .toolbar {
    position: sticky; top: 0; z-index: 10;
    background: #2B2318; color: #ECB351;
    padding: 12px 20px; margin: -40px -24px 24px;
    display: flex; align-items: center; gap: 12px;
    font-size: 14px; font-weight: 600;
  }
  .toolbar button {
    background: #ECB351; color: #2B2318; border: none;
    padding: 8px 16px; border-radius: 6px; cursor: pointer;
    font-size: 14px; font-weight: 700;
  }
  .toolbar button:hover { background: #d9a345; }
  .toolbar .status { margin-left: auto; font-weight: 400; color: #87986A; font-size: 13px; }

  #export-content { cursor: text; }

  h1 { font-family: Georgia, serif; font-size: 24px; margin: 0 0 4px; color: #2B2318; }
  .week-label { font-size: 14px; color: #87986A; margin-bottom: 24px; }
  h2 { font-family: Georgia, serif; font-size: 20px; color: #2B2318; text-transform: uppercase;
       letter-spacing: 1px; border-bottom: 2px solid #ECB351; padding-bottom: 6px;
       margin: 32px 0 16px; }
  h3 { font-family: Georgia, serif; font-size: 16px; color: #87986A; text-transform: uppercase;
       letter-spacing: 1px; margin: 24px 0 12px; }

  .item { margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(92,78,58,0.12); }
  .item img { max-width: 100%; height: auto; border-radius: 6px; display: block; margin-bottom: 4px; }
  .img-wrap { position: relative; margin-bottom: 8px; }
  .img-copy-btn {
    display: inline-block; font-size: 11px; font-weight: 600; color: #2B2318;
    background: #ECB351; border: 1px solid #ECB351; border-radius: 4px;
    padding: 3px 10px; cursor: pointer; margin-bottom: 4px; font-family: inherit;
  }
  .img-copy-btn:hover { background: #d9a345; }
  .img-note { font-size: 11px; color: #aaa; font-style: italic; margin: 0 0 16px; }
  .item:last-child { border-bottom: none; }
  .item-title { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
  .item-title a { color: #2B2318; text-decoration: none; }
  .item-title a:hover { text-decoration: underline; }
  .item-meta { font-size: 13px; color: #87986A; margin: 0 0 6px; }
  .item-excerpt { font-size: 15px; color: #5C4E3A; margin: 0; }
  .item-author { font-size: 13px; color: #aaa; margin-top: 4px; }
  .item-author a { color: #87986A; text-decoration: none; }

  .event-date { font-size: 14px; color: #5C4E3A; margin: 0 0 4px; }
  .event-tier { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px;
                border-radius: 10px; background: #ECB351; color: #2B2318; }

  .sponsor-label { font-size: 11px; font-weight: 700; color: #ECB351;
                    text-transform: uppercase; letter-spacing: 1px; margin: 0 0 4px; }

  hr { border: none; border-top: 1px solid #D4E0B8; margin: 28px 0; }
</style>
</head>
<body>

<div class="toolbar">
  <span>Patreon Export</span>
  <button onclick="selectAndCopy()">📋 Select All & Copy</button>
  <span class="status" id="copy-status">1) Copy text → paste. 2) Copy each image → paste where you want it.</span>
</div>

<div id="export-content">
<h1>{$title_html}</h1>
<p class="week-label">Week of {$week_str} · <a href="{$site_url}">loothgroup.com</a></p>

{$body}
<hr>
<p style="font-size:13px;color:#aaa;text-align:center;">
  <a href="{$site_url}" style="color:#87986A;">The Looth Group</a> · Guitar Repair &amp; Restoration Community
</p>
</div>

<script>
function selectAndCopy() {
  const el = document.getElementById('export-content');
  const range = document.createRange();
  range.selectNodeContents(el);
  const sel = window.getSelection();
  sel.removeAllRanges();
  sel.addRange(range);

  try {
    document.execCommand('copy');
    document.getElementById('copy-status').textContent = '✓ Text copied! Now copy & paste each image.';
    setTimeout(() => {
      document.getElementById('copy-status').textContent = '1) Copy text → paste. 2) Copy each image → paste where you want it.';
    }, 4000);
  } catch (e) {
    document.getElementById('copy-status').textContent = 'Use Ctrl+C to copy the selection.';
  }
}

async function copyImage(btn, url) {
  const orig = btn.textContent;
  btn.textContent = 'Loading…';
  btn.disabled = true;
  try {
    const resp = await fetch(url);
    const blob = await resp.blob();
    // Convert to PNG for clipboard compatibility (clipboard API requires image/png)
    const pngBlob = await toPng(blob);
    await navigator.clipboard.write([
      new ClipboardItem({ 'image/png': pngBlob })
    ]);
    btn.textContent = '✓ Copied! Paste into Patreon.';
    btn.style.background = '#87986A';
    btn.style.borderColor = '#87986A';
    setTimeout(() => {
      btn.textContent = orig;
      btn.style.background = '';
      btn.style.borderColor = '';
    }, 3000);
  } catch (e) {
    // Fallback: copy URL as text
    await navigator.clipboard.writeText(url);
    btn.textContent = '✓ URL copied (image copy unsupported)';
    setTimeout(() => { btn.textContent = orig; }, 3000);
  }
  btn.disabled = false;
}

// Convert any image blob to PNG via canvas (clipboard API needs image/png)
function toPng(blob) {
  return new Promise((resolve, reject) => {
    if (blob.type === 'image/png') { resolve(blob); return; }
    const img = new Image();
    img.onload = () => {
      const c = document.createElement('canvas');
      c.width = img.naturalWidth;
      c.height = img.naturalHeight;
      c.getContext('2d').drawImage(img, 0, 0);
      c.toBlob(b => b ? resolve(b) : reject('Canvas toBlob failed'), 'image/png');
      URL.revokeObjectURL(img.src);
    };
    img.onerror = () => reject('Image load failed');
    img.crossOrigin = 'anonymous';
    img.src = URL.createObjectURL(blob);
  });
}
</script>

</body>
</html>
HTML;
    }

    // ── Section renderer ──────────────────────────────────────────────────────

    private static function render_section( array $data ): string {
        $section      = $data['section'];
        $items        = $data['items'];
        $under_header = ! empty( $data['under_header'] );

        if ( empty( $items ) ) return '';

        $label    = esc_html( self::strip_emoji( $section['label'] ) );
        $template = $section['template'] ?? 'card';

        $html = '';

        // Section heading
        if ( $under_header ) {
            $html .= '<h3>' . $label . '</h3>' . "\n";
        } else {
            $html .= '<h2>' . $label . '</h2>' . "\n";
        }

        // Render items based on template type
        foreach ( $items as $item ) {
            $html .= match ( $template ) {
                'date-forward' => self::render_event_item( $item ),
                'forum'        => self::render_forum_item( $item ),
                'sponsor'      => self::render_sponsor_item( $item ),
                'full-text'    => self::render_fulltext_item( $item ),
                'list'         => self::render_list_item( $item ),
                default        => self::render_card_item( $item ),
            };
        }

        $html .= '<hr>' . "\n";
        return $html;
    }

    // ── Item renderers ────────────────────────────────────────────────────────

    /**
     * Render an item's featured image as an <img> tag.
     * Returns empty string if no image available.
     */
    private static function render_item_image( array $item ): string {
        $img_url = $item['thumb_url'] ?? '';

        // For WP posts, also check for first <img> in content as fallback
        if ( ! $img_url && ! empty( $item['id'] ) ) {
            $post = get_post( $item['id'] );
            if ( $post && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $m ) ) {
                $img_url = $m[1];
            }
        }

        if ( ! $img_url ) return '';

        $url  = esc_url( $item['url'] ?? '' );
        $alt  = esc_attr( $item['title'] ?? '' );
        $src  = esc_url( $img_url );
        $src_js = esc_attr( $img_url ); // for JS onclick

        $img = '<img src="' . $src . '" alt="' . $alt . '">';

        $html  = '<div class="img-wrap">' . "\n";
        if ( $url ) {
            $html .= '  <a href="' . $url . '" style="display:block;line-height:0;">' . $img . '</a>' . "\n";
        } else {
            $html .= '  ' . $img . "\n";
        }
        $html .= '  <button class="img-copy-btn" onclick="copyImage(this, \'' . $src_js . '\')" type="button">📋 Copy Image</button>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    private static function render_card_item( array $item ): string {
        $title   = esc_html( $item['title'] );
        $url     = esc_url( $item['url'] );
        $excerpt = esc_html( $item['excerpt'] ?? '' );
        $author  = self::plain_author( $item );
        $type    = esc_html( $item['type_label'] ?? '' );

        $html  = '<div class="item">' . "\n";
        $html .= self::render_item_image( $item );
        $html .= '  <p class="item-title"><a href="' . $url . '"><strong>' . $title . '</strong></a></p>' . "\n";
        if ( $type ) {
            $html .= '  <p class="item-meta">' . $type . '</p>' . "\n";
        }
        if ( $excerpt ) {
            $html .= '  <p class="item-excerpt">' . $excerpt . '</p>' . "\n";
        }
        if ( $author ) {
            $html .= '  <p class="item-author">' . $author . '</p>' . "\n";
        }
        $html .= '</div>' . "\n";
        return $html;
    }

    private static function render_event_item( array $item ): string {
        $title    = esc_html( $item['title'] );
        $url      = esc_url( $item['url'] );
        $post_id  = $item['id'] ?? 0;

        // Parse date/time the same way as date-forward.php
        $date_raw = get_post_meta( $post_id, 'events_start_date_and_time_', true );
        $time_raw = get_post_meta( $post_id, 'time_of_event', true );
        $zoom_url = get_post_meta( $post_id, 'zoom_url_for_looth_group_virtual_event', true );

        $display_date = '';
        $time_display = '';

        if ( $date_raw ) {
            $tz = new DateTimeZone( LG_WD_TIMEZONE );
            $ts = DateTime::createFromFormat( 'Ymd', $date_raw, $tz );
            if ( $ts ) {
                $display_date = $ts->format( 'l, F j, Y' );
            }
        }

        if ( ! $display_date ) {
            $display_date = $item['date'] ?? '';
        }

        // Time string
        if ( $time_raw && $date_raw ) {
            $tz_eastern = new DateTimeZone( LG_WD_TIMEZONE );
            $tz_utc     = new DateTimeZone( 'UTC' );
            $formats    = [ 'Ymd H:i:s', 'Ymd H:i', 'Ymd g:ia', 'Ymd g:i a', 'Ymd h:iA', 'Ymd h:i A' ];
            foreach ( $formats as $fmt ) {
                $dt = DateTime::createFromFormat( $fmt, $date_raw . ' ' . $time_raw, $tz_eastern );
                if ( $dt ) {
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
        }

        // Tier
        $tiers    = wp_get_post_terms( $post_id, 'event_tier_', [ 'fields' => 'names' ] );
        $tier     = ( ! is_wp_error( $tiers ) && ! empty( $tiers ) ) ? $tiers[0] : '';
        $location = $zoom_url ? 'Virtual Event' : 'In Person';

        $author = self::plain_author( $item );

        $html  = '<div class="item">' . "\n";
        $html .= self::render_item_image( $item );
        $html .= '  <p class="item-title"><a href="' . $url . '"><strong>' . $title . '</strong></a></p>' . "\n";
        $html .= '  <p class="event-date">' . esc_html( $display_date );
        if ( $time_display ) {
            $html .= ' · ' . $time_display;
        }
        $html .= '</p>' . "\n";

        $meta_parts = [];
        if ( $tier )     $meta_parts[] = '<span class="event-tier">' . esc_html( $tier ) . '</span>';
        $meta_parts[] = $location;
        if ( $author )   $meta_parts[] = $author;
        $html .= '  <p class="item-meta">' . implode( ' · ', $meta_parts ) . '</p>' . "\n";

        $html .= '</div>' . "\n";
        return $html;
    }

    private static function render_forum_item( array $item ): string {
        $title   = esc_html( $item['title'] );
        $url     = esc_url( $item['url'] );
        $excerpt = esc_html( $item['excerpt'] ?? '' );
        $author  = self::plain_author( $item );

        $reply_count = '';
        if ( $item['id'] && function_exists( 'bbp_get_topic_reply_count' ) ) {
            $count = bbp_get_topic_reply_count( $item['id'] );
            if ( $count ) {
                $reply_count = $count . ' ' . ( $count == 1 ? 'reply' : 'replies' );
            }
        }

        $html  = '<div class="item">' . "\n";
        $html .= self::render_item_image( $item );
        $html .= '  <p class="item-title"><a href="' . $url . '"><strong>' . $title . '</strong></a></p>' . "\n";

        $meta_parts = [];
        if ( $reply_count ) $meta_parts[] = $reply_count;
        if ( $author )      $meta_parts[] = $author;
        if ( $meta_parts ) {
            $html .= '  <p class="item-meta">' . implode( ' · ', $meta_parts ) . '</p>' . "\n";
        }

        if ( $excerpt ) {
            $html .= '  <p class="item-excerpt">' . $excerpt . '</p>' . "\n";
        }
        $html .= '</div>' . "\n";
        return $html;
    }

    private static function render_sponsor_item( array $item ): string {
        $title   = esc_html( $item['title'] );
        $url     = esc_url( $item['url'] );
        $excerpt = esc_html( $item['excerpt'] ?? '' );

        $html  = '<div class="item">' . "\n";
        $html .= '  <p class="sponsor-label">Partner Spotlight</p>' . "\n";
        $html .= self::render_item_image( $item );
        $html .= '  <p class="item-title"><a href="' . $url . '"><strong>' . $title . '</strong></a></p>' . "\n";
        if ( $excerpt ) {
            $html .= '  <p class="item-excerpt">' . $excerpt . '</p>' . "\n";
        }
        $html .= '</div>' . "\n";
        return $html;
    }

    private static function render_fulltext_item( array $item ): string {
        $title   = esc_html( $item['title'] );
        $url     = esc_url( $item['url'] );
        $post    = get_post( $item['id'] );
        $content = $post ? wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ) : '';

        $html  = '<div class="item">' . "\n";
        $html .= self::render_item_image( $item );
        $html .= '  <p class="item-title"><a href="' . $url . '"><strong>' . $title . '</strong></a></p>' . "\n";
        if ( $content ) {
            // Trim to a reasonable length for Patreon
            $trimmed = wp_trim_words( $content, 80, '…' );
            $html .= '  <p class="item-excerpt">' . esc_html( $trimmed ) . '</p>' . "\n";
        }
        $html .= '</div>' . "\n";
        return $html;
    }

    private static function render_list_item( array $item ): string {
        $title = esc_html( $item['title'] );
        $url   = esc_url( $item['url'] );
        $date  = esc_html( $item['date'] ?? '' );

        return '<p style="margin:4px 0;">• <a href="' . $url . '"><strong>' . $title . '</strong></a>'
             . ( $date ? ' <span style="color:#aaa;font-size:13px;">(' . $date . ')</span>' : '' )
             . '</p>' . "\n";
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a plain-text author attribution (with link if available).
     */
    private static function plain_author( array $item ): string {
        $post_id = $item['id'] ?? 0;
        if ( ! $post_id ) {
            // Manual item
            $name = $item['author_name'] ?? '';
            $url  = $item['author_url'] ?? '';
            if ( ! $name ) return '';
            if ( $url ) {
                return 'By <a href="' . esc_url( $url ) . '" style="color:#87986A;">' . esc_html( $name ) . '</a>';
            }
            return 'By <strong>' . esc_html( $name ) . '</strong>';
        }

        $author_id = (int) get_post_field( 'post_author', $post_id );
        if ( ! $author_id ) return '';

        $name = get_the_author_meta( 'display_name', $author_id );
        if ( ! $name ) return '';

        return 'By <strong>' . esc_html( $name ) . '</strong>';
    }

    private static function strip_emoji( string $text ): string {
        $clean = preg_replace( '/[\x{1F000}-\x{1FFFF}]/u', '', $text );
        $clean = preg_replace( '/[\x{2600}-\x{27BF}]/u', '', $clean );
        $clean = preg_replace( '/[\x{FE00}-\x{FE0F}]/u', '', $clean );
        $clean = preg_replace( '/[\x{200D}]/u', '', $clean );
        return trim( $clean );
    }
}
