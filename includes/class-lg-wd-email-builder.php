<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Email_Builder
 * Renders the full HTML email from the content payload.
 * Section display is handled by pluggable template files in templates/sections/.
 */
class LG_WD_Email_Builder {

    // Brand palette (available to section templates via LG_WD_Email_Builder::GOLD etc.)
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

    // ── Section renderer (called from email.php template) ─────────────────────

    public static function render_section( array $data ): string {
        $section = $data['section'];
        $items   = $data['items'];
        $is_arch = $data['is_archive'];

        if ( empty( $items ) ) return '';

        $settings = LG_WD_Settings::get_all();

        $archive_notice = $is_arch
            ? '<p style="font-size:11px;color:#aaa;margin:0 0 10px;font-style:italic;">From the archive</p>'
            : '';

        $template = $section['template'] ?? 'card';
        $template_file = LG_WD_PLUGIN_DIR . 'templates/sections/' . $template . '.php';

        // Safety: fall back to card if template file doesn't exist
        if ( ! file_exists( $template_file ) ) {
            $template_file = LG_WD_PLUGIN_DIR . 'templates/sections/card.php';
        }

        $rows = '';
        foreach ( $items as $item ) {
            ob_start();
            include $template_file;
            $rows .= ob_get_clean();
        }

        $label     = esc_html( $section['label'] );
        $cpt_label = ! empty( $section['cpt_label'] ) ? esc_html( $section['cpt_label'] ) : '';

        // Subheading: show CPT label below the main section header when a section_header is set
        $sub_html = $cpt_label
            ? '<p style="font-family:Georgia,\'Times New Roman\',serif;font-size:12px;font-weight:600;color:#87986A;text-transform:uppercase;letter-spacing:1px;margin:0 0 10px;">' . $cpt_label . '</p>'
            : '';

        return '<div style="margin-bottom:28px;">'
            . $archive_notice
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:' . ( $cpt_label ? '8px' : '14px' ) . ';">'
            . '<tr>'
            . '<td style="padding:0;white-space:nowrap;">'
            . '<span style="font-family:Georgia,\'Times New Roman\',serif;font-size:14px;font-weight:700;color:#2B2318;text-transform:uppercase;letter-spacing:1.5px;">' . $label . '</span>'
            . '</td>'
            . '<td width="100%" style="padding-left:12px;">'
            . '<div style="height:1px;background:#ECB351;"></div>'
            . '</td>'
            . '</tr>'
            . '</table>'
            . $sub_html
            . $rows
            . '</div>';
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
