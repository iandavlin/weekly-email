<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Settings
 * Central store for all plugin configuration.
 * Wraps get_option / update_option with typed defaults.
 */
class LG_WD_Settings {

    // ── Defaults ────────────────────────────────────────────────────────────
    private static array $defaults = [

        // Schedule
        'enabled'          => true,
        'send_day'         => 'monday',       // lowercase day name
        'send_time'        => '09:00',        // H:i, America/New_York
        'lookback_days'    => 7,

        // Email design
        'header_image_url' => '',             // URL or empty → text logo
        'from_name'        => 'The Looth Group',
        'from_email'       => 'hello@loothgroup.com',
        'subject_template' => 'The Looth Group — Week of {{week_date}}',
        'signoff'          => "Don't forget to try to have some fun. Did you really get into this stuff to perfect your frown?",

        // Display toggles
        'show_excerpts'    => true,
        'show_thumbnails'  => true,
        'skip_empty'       => true,

        // CPT sections — ordered array of section configs
        // Each item: [ slug, label, max_items, enabled, type ]
        // type: 'cpt' | 'events' | 'forum' | 'spotlight' | 'sponsor'
        'sections' => [
            [
                'key'       => 'events',
                'label'     => 'Upcoming Events',
                'type'      => 'events',
                'slug'      => 'event',
                'max_items' => 5,
                'enabled'   => true,
            ],
            [
                'key'       => 'new_content',
                'label'     => 'New to the Website',
                'type'      => 'multi_cpt',
                'slug'      => 'videos,articles,loothprints,loothcuts',
                'max_items' => 6,
                'enabled'   => true,
            ],
            [
                'key'       => 'forum',
                'label'     => 'From the Forum',
                'type'      => 'forum',
                'slug'      => '',
                'max_items' => 5,
                'enabled'   => true,
            ],
            [
                'key'       => 'spotlight',
                'label'     => 'Member Highlight',
                'type'      => 'spotlight',
                'slug'      => 'member-spotlight',
                'max_items' => 1,
                'enabled'   => true,
            ],
            [
                'key'       => 'sponsor',
                'label'     => 'Sponsor Post',
                'type'      => 'sponsor',
                'slug'      => 'sponsor-post',
                'max_items' => 1,
                'enabled'   => true,
            ],
        ],
    ];

    // ── Public API ───────────────────────────────────────────────────────────

    public static function get_all(): array {
        $saved = get_option( LG_WD_OPTION_KEY, [] );
        return wp_parse_args( $saved, self::$defaults );
    }

    public static function get( string $key, $fallback = null ) {
        $all = self::get_all();
        return $all[ $key ] ?? $fallback;
    }

    public static function save( array $data ): void {
        $current = self::get_all();

        // Sanitize scalar fields
        $scalar_keys = [
            'enabled', 'send_day', 'send_time', 'lookback_days',
            'header_image_url', 'from_name', 'from_email',
            'subject_template', 'signoff',
            'show_excerpts', 'show_thumbnails', 'skip_empty',
        ];

        foreach ( $scalar_keys as $k ) {
            if ( array_key_exists( $k, $data ) ) {
                $current[ $k ] = $data[ $k ];
            }
        }

        // Sections handled separately (array)
        if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
            $current['sections'] = self::sanitize_sections( $data['sections'] );
        }

        update_option( LG_WD_OPTION_KEY, $current, false );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function sanitize_sections( array $raw ): array {
        $clean = [];
        foreach ( $raw as $s ) {
            $clean[] = [
                'key'       => sanitize_key( $s['key'] ?? '' ),
                'label'     => sanitize_text_field( $s['label'] ?? '' ),
                'type'      => sanitize_key( $s['type'] ?? 'cpt' ),
                'slug'      => sanitize_text_field( $s['slug'] ?? '' ),
                'max_items' => absint( $s['max_items'] ?? 5 ),
                'enabled'   => ! empty( $s['enabled'] ),
            ];
        }
        return $clean;
    }

    /**
     * Return only enabled sections in their saved order.
     */
    public static function enabled_sections(): array {
        return array_filter( self::get( 'sections', [] ), fn( $s ) => ! empty( $s['enabled'] ) );
    }
}
