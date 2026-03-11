<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_CPT_Registry
 * Admin UI to register WordPress CPTs as available email sections.
 * Stored in wp_options as 'lg_wd_cpt_registry'.
 */
class LG_WD_CPT_Registry {

    const OPTION_KEY = 'lg_wd_cpt_registry';

    // Built-in section types that cannot be removed.
    private static array $builtins = [
        [
            'slug'      => 'event',
            'label'     => 'Upcoming Events',
            'type'      => 'events',
            'max_items' => 5,
            'enabled'   => true,
            'builtin'   => true,
        ],
        [
            'slug'      => 'topic',
            'label'     => 'From the Forum',
            'type'      => 'forum',
            'max_items' => 5,
            'enabled'   => true,
            'builtin'   => true,
        ],
        [
            'slug'      => 'member-spotlight',
            'label'     => 'Member Highlight',
            'type'      => 'spotlight',
            'max_items' => 1,
            'enabled'   => true,
            'builtin'   => true,
        ],
        [
            'slug'      => 'sponsor-post',
            'label'     => 'Sponsor Post',
            'type'      => 'sponsor',
            'max_items' => 1,
            'enabled'   => true,
            'builtin'   => true,
        ],
    ];

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Get all registered sections (builtins + custom).
     */
    public static function get_all(): array {
        $custom = get_option( self::OPTION_KEY, [] );
        return array_merge( self::$builtins, $custom );
    }

    /**
     * Get only enabled sections.
     */
    public static function get_enabled(): array {
        return array_filter( self::get_all(), fn( $s ) => ! empty( $s['enabled'] ) );
    }

    /**
     * Get a section by slug.
     */
    public static function get_by_slug( string $slug ): ?array {
        foreach ( self::get_all() as $section ) {
            if ( $section['slug'] === $slug ) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Save custom (non-builtin) sections.
     */
    public static function save_custom( array $sections ): void {
        $clean = [];
        foreach ( $sections as $s ) {
            if ( ! empty( $s['builtin'] ) ) continue; // skip builtins
            $clean[] = self::sanitize_entry( $s );
        }
        update_option( self::OPTION_KEY, $clean, false );
    }

    /**
     * Add a new custom section.
     */
    public static function add( array $entry ): bool {
        $entry = self::sanitize_entry( $entry );
        if ( empty( $entry['slug'] ) || empty( $entry['label'] ) ) {
            return false;
        }

        // Check for duplicate slug
        if ( self::get_by_slug( $entry['slug'] ) ) {
            return false;
        }

        $custom   = get_option( self::OPTION_KEY, [] );
        $custom[] = $entry;
        update_option( self::OPTION_KEY, $custom, false );
        return true;
    }

    /**
     * Remove a custom section by slug.
     */
    public static function remove( string $slug ): bool {
        // Cannot remove builtins
        foreach ( self::$builtins as $b ) {
            if ( $b['slug'] === $slug ) return false;
        }

        $custom  = get_option( self::OPTION_KEY, [] );
        $filtered = array_filter( $custom, fn( $s ) => $s['slug'] !== $slug );

        if ( count( $filtered ) === count( $custom ) ) {
            return false; // not found
        }

        update_option( self::OPTION_KEY, array_values( $filtered ), false );
        return true;
    }

    /**
     * Update an existing section (builtin or custom).
     * For builtins, only label, max_items, and enabled can be changed.
     */
    public static function update( string $slug, array $data ): bool {
        // Check if it's a builtin — builtins aren't stored in options,
        // so we store overrides separately.
        $is_builtin = false;
        foreach ( self::$builtins as $b ) {
            if ( $b['slug'] === $slug ) {
                $is_builtin = true;
                break;
            }
        }

        if ( $is_builtin ) {
            $overrides = get_option( 'lg_wd_builtin_overrides', [] );
            $overrides[ $slug ] = [
                'label'     => sanitize_text_field( $data['label'] ?? '' ),
                'max_items' => absint( $data['max_items'] ?? 5 ),
                'enabled'   => ! empty( $data['enabled'] ),
            ];
            update_option( 'lg_wd_builtin_overrides', $overrides, false );
            return true;
        }

        // Custom section — update in place
        $custom = get_option( self::OPTION_KEY, [] );
        foreach ( $custom as &$s ) {
            if ( $s['slug'] === $slug ) {
                $s = self::sanitize_entry( array_merge( $s, $data ) );
                update_option( self::OPTION_KEY, $custom, false );
                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered WP post types (for the dropdown).
     */
    public static function get_available_post_types(): array {
        $types  = get_post_types( [ 'public' => true ], 'objects' );
        $result = [];
        foreach ( $types as $cpt ) {
            if ( in_array( $cpt->name, [ 'attachment', 'weekly_email' ], true ) ) continue;
            $result[ $cpt->name ] = $cpt->label;
        }
        return $result;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /**
     * Override builtins with stored overrides before returning.
     */
    public static function get_all_with_overrides(): array {
        $overrides = get_option( 'lg_wd_builtin_overrides', [] );
        $all       = self::get_all();

        foreach ( $all as &$section ) {
            if ( ! empty( $section['builtin'] ) && isset( $overrides[ $section['slug'] ] ) ) {
                $section = array_merge( $section, $overrides[ $section['slug'] ] );
            }
        }

        return $all;
    }

    private static function sanitize_entry( array $s ): array {
        return [
            'slug'      => sanitize_key( $s['slug'] ?? '' ),
            'label'     => sanitize_text_field( $s['label'] ?? '' ),
            'type'      => sanitize_key( $s['type'] ?? 'cpt' ),
            'max_items' => absint( $s['max_items'] ?? 5 ),
            'enabled'   => ! empty( $s['enabled'] ),
            'builtin'   => false,
        ];
    }
}
