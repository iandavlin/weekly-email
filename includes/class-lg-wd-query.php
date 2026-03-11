<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Query
 * Fetches content for each section. Handles the archive fallback:
 * if no posts in the lookback window, expands the query to all-time.
 */
class LG_WD_Query {

    // ── Public entry point ───────────────────────────────────────────────────

    /**
     * Build the full content payload for the digest.
     * Returns array keyed by section key.
     */
    public static function build_payload(): array {
        $sections  = LG_WD_Settings::enabled_sections();
        $days      = (int) LG_WD_Settings::get( 'lookback_days', 7 );
        $payload   = [];

        foreach ( $sections as $section ) {
            $items = self::fetch_section( $section, $days );
            $payload[ $section['key'] ] = [
                'section' => $section,
                'items'   => $items,
                'is_archive' => false,
            ];

            // Archive fallback — if zero items, fetch from all time
            if ( empty( $items ) ) {
                $archive_items = self::fetch_section( $section, 0 ); // 0 = no date limit
                if ( ! empty( $archive_items ) ) {
                    $payload[ $section['key'] ]['items']      = $archive_items;
                    $payload[ $section['key'] ]['is_archive'] = true;
                }
            }
        }

        // Remove empty sections if setting enabled
        if ( LG_WD_Settings::get( 'skip_empty' ) ) {
            $payload = array_filter( $payload, fn( $p ) => ! empty( $p['items'] ) );
        }

        return $payload;
    }

    // ── Section dispatchers ──────────────────────────────────────────────────

    private static function fetch_section( array $section, int $days ): array {
        switch ( $section['type'] ) {
            case 'events':     return self::fetch_events( $section, $days );
            case 'multi_cpt':  return self::fetch_multi_cpt( $section, $days );
            case 'forum':      return self::fetch_forum( $section, $days );
            case 'spotlight':  return self::fetch_cpt( $section, $days );
            case 'sponsor':    return self::fetch_cpt( $section, $days );
            case 'cpt':        return self::fetch_cpt( $section, $days );
            default:           return [];
        }
    }

    // ── Event fetcher (upcoming, not recent) ────────────────────────────────

    /**
     * Events: query by ACF start date field, return future events
     * ordered soonest first. Ignores lookback — always shows upcoming.
     * Falls back to most recent past events if no upcoming exist.
     */
    private static function fetch_events( array $section, int $days ): array {
        $max    = (int) ( $section['max_items'] ?? 5 );
        $today  = current_time( 'Ymd' ); // matches ACF Date Picker Ymd format

        $args = [
            'post_type'      => 'event',
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'meta_query'     => [
                [
                    'key'     => 'events_start_date_and_time_',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
            'meta_key'  => 'events_start_date_and_time_',
            'orderby'   => 'meta_value',
            'order'     => 'ASC',
        ];

        $posts = get_posts( $args );

        // If no upcoming, get most recent past events as archive fallback
        if ( empty( $posts ) && $days === 0 ) {
            $args['meta_query'][0]['compare'] = '<';
            $args['order'] = 'DESC';
            $posts = get_posts( $args );
        }

        return array_map( [ __CLASS__, 'normalize_event' ], $posts );
    }

    // ── Multi-CPT fetcher (New to the Website) ───────────────────────────────

    private static function fetch_multi_cpt( array $section, int $days ): array {
        $max   = (int) ( $section['max_items'] ?? 6 );
        $slugs = array_filter( array_map( 'trim', explode( ',', $section['slug'] ?? '' ) ) );

        if ( empty( $slugs ) ) return [];

        $args = [
            'post_type'      => $slugs,
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $days > 0 ) {
            $args['date_query'] = self::date_query( $days );
        }

        $posts = get_posts( $args );
        return array_map( [ __CLASS__, 'normalize_post' ], $posts );
    }

    // ── Single CPT fetcher ───────────────────────────────────────────────────

    private static function fetch_cpt( array $section, int $days ): array {
        $max  = (int) ( $section['max_items'] ?? 3 );
        $slug = sanitize_key( $section['slug'] ?? '' );

        if ( empty( $slug ) ) return [];

        $args = [
            'post_type'      => $slug,
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $days > 0 ) {
            $args['date_query'] = self::date_query( $days );
        }

        $posts = get_posts( $args );
        return array_map( [ __CLASS__, 'normalize_post' ], $posts );
    }

    // ── Forum fetcher (bbPress) ───────────────────────────────────────────────

    private static function fetch_forum( array $section, int $days ): array {
        $max = (int) ( $section['max_items'] ?? 5 );

        // bbPress topic post type
        $topic_cpt = function_exists( 'bbp_get_topic_post_type' )
            ? bbp_get_topic_post_type()
            : 'topic';

        $args = [
            'post_type'      => $topic_cpt,
            'post_status'    => [ 'publish', 'closed' ],
            'posts_per_page' => $max,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $days > 0 ) {
            $args['date_query'] = self::date_query( $days );
        }

        $posts = get_posts( $args );
        return array_map( [ __CLASS__, 'normalize_forum_topic' ], $posts );
    }

    // ── Normalizers ──────────────────────────────────────────────────────────

    private static function normalize_post( WP_Post $post ): array {
        $thumb_url = '';
        if ( has_post_thumbnail( $post->ID ) ) {
            $thumb_url = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
        }

        return [
            'id'        => $post->ID,
            'title'     => get_the_title( $post ),
            'url'       => get_permalink( $post ),
            'excerpt'   => self::clean_excerpt( $post ),
            'thumb_url' => $thumb_url,
            'date'      => get_the_date( 'M j', $post ),
            'post_type' => $post->post_type,
            'type_label'=> self::cpt_label( $post->post_type ),
        ];
    }

    private static function normalize_event( WP_Post $post ): array {
        $base = self::normalize_post( $post );

        // ACF fields
        $date_raw = get_post_meta( $post->ID, 'events_start_date_and_time_', true );
        $time_raw = get_post_meta( $post->ID, 'time_of_event', true );
        $zoom_url = get_post_meta( $post->ID, 'zoom_url_for_looth_group_virtual_event', true );

        // Format date for display
        $display_date = '';
        $month_short  = '';
        $day_num      = '';
        if ( $date_raw ) {
            $ts = DateTime::createFromFormat( 'Ymd', $date_raw, new DateTimeZone( LG_WD_TIMEZONE ) );
            if ( $ts ) {
                $display_date = $ts->format( 'l, F j' );
                $month_short  = $ts->format( 'M' );
                $day_num      = $ts->format( 'j' );
            }
        }

        // Tier taxonomy
        $tiers = wp_get_post_terms( $post->ID, 'event_tier_', [ 'fields' => 'names' ] );
        $tier  = ( ! is_wp_error( $tiers ) && ! empty( $tiers ) ) ? $tiers[0] : '';

        // Location type
        $location = $zoom_url ? 'Virtual' : 'In Person';

        return array_merge( $base, [
            'display_date' => $display_date,
            'time_raw'     => $time_raw,
            'month_short'  => $month_short,
            'day_num'      => $day_num,
            'tier'         => $tier,
            'location'     => $location,
            'zoom_url'     => $zoom_url,
        ] );
    }

    private static function normalize_forum_topic( WP_Post $post ): array {
        $reply_count = function_exists( 'bbp_get_topic_reply_count' )
            ? (int) bbp_get_topic_reply_count( $post->ID )
            : 0;

        $author = get_the_author_meta( 'display_name', $post->post_author );

        return [
            'id'          => $post->ID,
            'title'       => get_the_title( $post ),
            'url'         => get_permalink( $post ),
            'author'      => $author,
            'reply_count' => $reply_count,
            'date'        => get_the_date( 'M j', $post ),
            'post_type'   => $post->post_type,
            'type_label'  => 'Forum',
            'excerpt'     => '',
            'thumb_url'   => '',
        ];
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    private static function date_query( int $days ): array {
        return [
            [
                'after'     => date( 'Y-m-d', strtotime( "-{$days} days" ) ),
                'inclusive' => true,
            ],
        ];
    }

    private static function clean_excerpt( WP_Post $post ): string {
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        return wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '…' );
    }

    private static function cpt_label( string $slug ): string {
        $map = [
            'videos'           => 'Video',
            'articles'         => 'Article',
            'loothprints'      => 'Loothprint',
            'loothcuts'        => 'Loothcut',
            'member-spotlight' => 'Member Spotlight',
            'sponsor-post'     => 'Sponsor',
            'event'            => 'Event',
            'topic'            => 'Forum',
        ];
        return $map[ $slug ] ?? ucfirst( $slug );
    }
}
