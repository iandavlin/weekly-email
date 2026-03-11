<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Email_Builder
 * Renders the full HTML email from the content payload.
 * Uses inline CSS for maximum email client compatibility.
 */
class LG_WD_Email_Builder {

    // Brand palette
    const GOLD       = '#ECB351';
    const SAND       = '#F1DE83';
    const MINT_LIGHT = '#D4E0B8';
    const MINT_DARK  = '#87986A';
    const CORAL      = '#FE6A4F';
    const DARK       = '#2B2318';
    const MID        = '#5C4E3A';
    const LIGHT      = '#FAF6EE';

    // ── Public API ────────────────────────────────────────────────────────────

    public static function build( array $payload ): string {
        $settings = LG_WD_Settings::get_all();
        $week_str = date_i18n( 'F j, Y' );

        ob_start();
        include LG_WD_PLUGIN_DIR . 'templates/email.php';
        return ob_get_clean();
    }

    // ── Section renderers (called from template) ──────────────────────────────

    public static function render_section( array $data ): string {
        $section = $data['section'];
        $items   = $data['items'];
        $is_arch = $data['is_archive'];

        if ( empty( $items ) ) return '';

        $archive_notice = $is_arch
            ? '<p style="font-size:11px;color:#aaa;margin:0 0 10px;font-style:italic;">From the archive</p>'
            : '';

        $rows = '';
        foreach ( $items as $item ) {
            $rows .= self::render_item( $item, $section['type'] );
        }

        $label = esc_html( $section['label'] );

        return <<<HTML
        <div style="margin-bottom:28px;">
          {$archive_notice}
          <!-- Section header -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
            <tr>
              <td style="padding:0;white-space:nowrap;">
                <span style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:700;color:#2B2318;text-transform:uppercase;letter-spacing:1.5px;">{$label}</span>
              </td>
              <td width="100%" style="padding-left:12px;">
                <div style="height:1px;background:#ECB351;"></div>
              </td>
            </tr>
          </table>
          {$rows}
        </div>
        HTML;
    }

    private static function render_item( array $item, string $type ): string {
        return match ( $type ) {
            'events'    => self::render_event( $item ),
            'forum'     => self::render_forum_item( $item ),
            'spotlight' => self::render_spotlight( $item ),
            'sponsor'   => self::render_sponsor( $item ),
            default     => self::render_post( $item ),
        };
    }

    // ── Post item ─────────────────────────────────────────────────────────────

    private static function render_post( array $item ): string {
        $show_thumb    = LG_WD_Settings::get( 'show_thumbnails' );
        $show_excerpt  = LG_WD_Settings::get( 'show_excerpts' );
        $title         = esc_html( $item['title'] );
        $url           = esc_url( self::add_utm( $item['url'] ) );
        $type_label    = esc_html( $item['type_label'] );
        $date          = esc_html( $item['date'] );
        $excerpt       = $show_excerpt && ! empty( $item['excerpt'] )
            ? '<p style="font-size:12px;color:#5C4E3A;margin:3px 0 0;line-height:1.5;">' . esc_html( $item['excerpt'] ) . '</p>'
            : '';

        $thumb_cell = '';
        if ( $show_thumb && ! empty( $item['thumb_url'] ) ) {
            $thumb_url  = esc_url( $item['thumb_url'] );
            $thumb_cell = <<<HTML
            <td width="80" valign="top" style="padding:0 14px 0 0;">
              <img src="{$thumb_url}" width="80" height="56"
                   style="display:block;border-radius:4px;object-fit:cover;"
                   alt="">
            </td>
            HTML;
        }

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:10px;margin-bottom:10px;">
          <tr>
            {$thumb_cell}
            <td valign="top" style="padding:0;">
              <p style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;margin:0 0 2px;">{$type_label}</p>
              <a href="{$url}" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;color:#2B2318;text-decoration:none;display:block;line-height:1.3;margin-bottom:2px;">{$title}</a>
              {$excerpt}
              <p style="font-size:11px;color:#aaa;margin:4px 0 0;">Published {$date}</p>
            </td>
          </tr>
        </table>
        HTML;
    }

    // ── Event item ────────────────────────────────────────────────────────────

    private static function render_event( array $item ): string {
        $title        = esc_html( $item['title'] );
        $url          = esc_url( self::add_utm( $item['url'] ) );
        $month        = esc_html( $item['month_short'] ?? '' );
        $day          = esc_html( $item['day_num'] ?? '' );
        $display_date = esc_html( $item['display_date'] ?? '' );
        $time         = esc_html( $item['time_raw'] ?? '' );
        $location     = esc_html( $item['location'] ?? '' );
        $tier         = esc_html( $item['tier'] ?? '' );

        // Tier badge color
        $tier_bg = match ( strtolower( $tier ) ) {
            'looth pro'   => '#ECB351',
            'looth lite'  => '#D4E0B8',
            default       => '#FAF6EE',
        };
        $tier_color  = ( strtolower( $tier ) === 'looth pro' ) ? '#2B2318' : '#5C4E3A';
        $tier_border = ( strtolower( $tier ) === 'public' ) ? 'border:1px solid #D4E0B8;' : '';
        $tier_html   = $tier
            ? "<span style=\"display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;background:{$tier_bg};color:{$tier_color};{$tier_border}\">{$tier}</span>"
            : '';

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:10px;margin-bottom:10px;">
          <tr>
            <td width="52" valign="top" style="padding:0 14px 0 0;">
              <div style="background:#2B2318;border-radius:6px;width:46px;height:46px;text-align:center;padding-top:6px;">
                <span style="display:block;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#87986A;">{$month}</span>
                <span style="display:block;font-size:20px;font-weight:700;font-family:Georgia,serif;color:#ECB351;line-height:1.1;">{$day}</span>
              </div>
            </td>
            <td valign="top">
              <a href="{$url}" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;color:#2B2318;text-decoration:none;display:block;margin-bottom:3px;">{$title}</a>
              <p style="font-size:12px;color:#5C4E3A;margin:0 0 5px;">{$display_date} · {$time} · {$location}</p>
              {$tier_html}
            </td>
          </tr>
        </table>
        HTML;
    }

    // ── Forum item ────────────────────────────────────────────────────────────

    private static function render_forum_item( array $item ): string {
        $title        = esc_html( $item['title'] );
        $url          = esc_url( self::add_utm( $item['url'] ) );
        $author       = esc_html( $item['author'] ?? '' );
        $reply_count  = (int) ( $item['reply_count'] ?? 0 );
        $date         = esc_html( $item['date'] ?? '' );
        $replies_text = $reply_count === 1 ? '1 reply' : "{$reply_count} replies";

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="border-bottom:1px solid rgba(92,78,58,0.1);padding-bottom:8px;margin-bottom:8px;">
          <tr>
            <td>
              <a href="{$url}" style="font-size:13px;font-weight:500;color:#2B2318;text-decoration:none;display:block;margin-bottom:3px;">{$title}</a>
              <p style="font-size:11px;color:#aaa;margin:0;">Started by <strong style="color:#87986A;">{$author}</strong> · {$replies_text} · {$date}</p>
            </td>
          </tr>
        </table>
        HTML;
    }

    // ── Member spotlight ──────────────────────────────────────────────────────

    private static function render_spotlight( array $item ): string {
        $title   = esc_html( $item['title'] );
        $url     = esc_url( self::add_utm( $item['url'] ) );
        $excerpt = esc_html( $item['excerpt'] ?? '' );
        $initial = strtoupper( substr( $item['title'], 0, 1 ) );

        $avatar = ! empty( $item['thumb_url'] )
            ? "<img src=\"" . esc_url( $item['thumb_url'] ) . "\" width=\"52\" height=\"52\" style=\"border-radius:50%;display:block;\" alt=\"\">"
            : "<div style=\"width:52px;height:52px;border-radius:50%;background:#ECB351;display:table-cell;vertical-align:middle;text-align:center;\"><span style=\"font-family:Georgia,serif;font-size:22px;font-weight:700;color:#2B2318;\">{$initial}</span></div>";

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background:#2B2318;border-radius:8px;">
          <tr>
            <td width="68" valign="top" style="padding:16px 0 16px 16px;">
              {$avatar}
            </td>
            <td valign="top" style="padding:16px;">
              <a href="{$url}" style="font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:700;color:#ECB351;text-decoration:none;display:block;margin-bottom:6px;">{$title}</a>
              <p style="font-size:12px;color:#D4E0B8;margin:0;line-height:1.6;">{$excerpt}</p>
            </td>
          </tr>
        </table>
        HTML;
    }

    // ── Sponsor ────────────────────────────────────────────────────────────────

    private static function render_sponsor( array $item ): string {
        $title   = esc_html( $item['title'] );
        $url     = esc_url( self::add_utm( $item['url'] ) );
        $excerpt = esc_html( $item['excerpt'] ?? '' );

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background:#FFF8EC;border:1px solid #ECB351;border-radius:8px;">
          <tr>
            <td style="padding:16px 18px;">
              <p style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1.5px;color:#ECB351;margin:0 0 6px;">Partner</p>
              <p style="font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:700;color:#2B2318;margin:0 0 8px;">{$title}</p>
              <p style="font-size:13px;color:#5C4E3A;margin:0 0 10px;line-height:1.6;">{$excerpt}</p>
              <a href="{$url}" style="font-size:12px;font-weight:600;color:#ECB351;text-decoration:none;">Learn more →</a>
            </td>
          </tr>
        </table>
        HTML;
    }

    // ── UTM helper ────────────────────────────────────────────────────────────

    public static function add_utm( string $url ): string {
        if ( ! LG_WD_Settings::get( 'utm_enabled' ) ) {
            return $url;
        }

        $week_date = date_i18n( 'Y-m-d' );
        $campaign  = str_replace( '{{week_date}}', $week_date, LG_WD_Settings::get( 'utm_campaign', '' ) );

        return add_query_arg( [
            'utm_source'   => LG_WD_Settings::get( 'utm_source', 'weekly-digest' ),
            'utm_medium'   => LG_WD_Settings::get( 'utm_medium', 'email' ),
            'utm_campaign' => $campaign,
        ], $url );
    }

    // ── Subject line builder ───────────────────────────────────────────────────

    public static function build_subject( array $payload ): string {
        $template   = LG_WD_Settings::get( 'subject_template', 'The Looth Group — Week of {{week_date}}' );
        $item_count = array_sum( array_map( fn( $p ) => count( $p['items'] ), $payload ) );
        $week_date  = date_i18n( 'F j, Y' );

        return str_replace(
            [ '{{week_date}}', '{{site_name}}', '{{item_count}}' ],
            [ $week_date, get_bloginfo( 'name' ), $item_count ],
            $template
        );
    }
}
