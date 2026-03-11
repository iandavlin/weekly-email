<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Query
 *
 * Two modes:
 *  1. Auto-populate: fetch post IDs for a date range per section (compose page).
 *  2. Issue-based: build full payload from curated post IDs (email rendering).
 *
 * Normalizers convert WP_Post objects into digest-ready arrays.
 */
class LG_WD_Query {

    // ── Mode 1: Auto-populate (returns post IDs for compose) ────────────────

    /**
     * Fetch post IDs for a single registry section within a date range.
     *
     * @param  array  $section   Registry entry (slug, type, max_items).
     * @param  string $date_from Y-m-d start date.
     * @param  string $date_to   Y-m-d end date.
     * @return int[]  Array of post IDs.
     */
    public static function fetch_ids_for_section( array $section, string $date_from, string $date_to ): array {
        $posts = self::fetch_posts_for_section( $section, $date_from, $date_to );
        return wp_list_pluck( $posts, 'ID' );
    }

    /**
     * Fetch WP_Post objects for a section within a date range.
     */
    private static function fetch_posts_for_section( array $section, string $date_from, string $date_to ): array {
        $max  = (int) ( $section['max_items'] ?? 5 );
        $type = $section['type'] ?? 'cpt';
        $slug = $section['slug'] ?? '';

        switch ( $type ) {
            case 'events':
                return self::fetch_events( $max );

            case 'forum':
                return self::fetch_forum( $max, $date_from, $date_to );

            case 'multi_cpt':
                $slugs = array_filter( array_map( 'trim', explode( ',', $slug ) ) );
                return ! empty( $slugs )
                    ? self::fetch_cpt_posts( $slugs, $max, $date_from, $date_to )
                    : [];

            case 'spotlight':
            case 'sponsor':
            case 'cpt':
            default:
                return ! empty( $slug )
                    ? self::fetch_cpt_posts( [ $slug ], $max, $date_from, $date_to )
                    : [];
        }
    }

    // ── Mode 2: Issue-based (returns full payload for email rendering) ───────

    /**
     * Build the full content payload from an issue's curated data.
     * Used by the email builder.
     *
     * @param  array $issue_data  The issue's sections with post_ids.
     * @return array Keyed by section key, each with 'section' and 'items'.
     */
    public static function build_payload_from_issue( array $issue_data ): array {
        $sections = $issue_data['sections'] ?? [];
        $payload  = [];
        $skip_empty = LG_WD_Settings::get( 'skip_empty', true );

        foreach ( $sections as $section ) {
            $post_ids = $section['post_ids'] ?? [];
            if ( empty( $post_ids ) && $skip_empty ) continue;

            $items = self::normalize_posts_by_ids( $post_ids, $section['type'] ?? 'cpt' );

            if ( empty( $items ) && $skip_empty ) continue;

            $payload[ $section['key'] ] = [
                'section'    => [
                    'key'   => $section['key'],
                    'label' => $section['label'],
                    'type'  => $section['type'],
                ],
                'items'      => $items,
                'is_archive' => false,
            ];
        }

        return $payload;
    }

    // ── Search (for "Add from Archive" in compose) ──────────────────────────

    /**
     * Search posts across all registered CPTs.
     *
     * @param  string $search_term  Search query.
     * @param  string $post_type    Optional: limit to a specific post type.
     * @param  int    $limit        Max results.
     * @return array  Array of [ id, title, date, post_type, type_label ].
     */
    public static function search_posts( string $search_term, string $post_type = '', int $limit = 20 ): array {
        $types = $post_type ? [ $post_type ] : self::get_all_registered_slugs();

        $args = [
            'post_type'      => $types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            's'              => $search_term,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $posts = get_posts( $args );
        $results = [];

        foreach ( $posts as $post ) {
            $results[] = [
                'id'         => $post->ID,
                'title'      => get_the_title( $post ),
                'date'       => get_the_date( 'M j, Y', $post ),
                'post_type'  => $post->post_type,
                'type_label' => self::cpt_label( $post->post_type ),
            ];
        }

        return $results;
    }

    // ── Fetchers ─────────────────────────────────────────────────────────────

    /**
     * Fetch upcoming events (ignores date range — always future-first).
     */
    private static function fetch_events( int $max ): array {
        $today = current_time( 'Ymd' );

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

        // Fallback: most recent past events
        if ( empty( $posts ) ) {
            $args['meta_query'][0]['compare'] = '<';
            $args['order'] = 'DESC';
            $posts = get_posts( $args );
        }

        return $posts;
    }

    /**
     * Fetch forum topics (bbPress).
     */
    private static function fetch_forum( int $max, string $date_from, string $date_to ): array {
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

        if ( $date_from && $date_to ) {
            $args['date_query'] = [
                [
                    'after'     => $date_from,
                    'before'    => $date_to,
                    'inclusive' => true,
                ],
            ];
        }

        $posts = get_posts( $args );

        // Fallback: if date range returned nothing, pull most recent
        if ( empty( $posts ) && LG_WD_Settings::get( 'fallback_enabled', true ) ) {
            unset( $args['date_query'] );
            $posts = get_posts( $args );
        }

        return $posts;
    }

    /**
     * Fetch posts from one or more CPT slugs within a date range.
     */
    private static function fetch_cpt_posts( array $slugs, int $max, string $date_from, string $date_to ): array {
        $args = [
            'post_type'      => $slugs,
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $date_from && $date_to ) {
            $args['date_query'] = [
                [
                    'after'     => $date_from,
                    'before'    => $date_to,
                    'inclusive' => true,
                ],
            ];
        }

        $posts = get_posts( $args );

        // Fallback: if date range returned nothing, pull most recent
        if ( empty( $posts ) && LG_WD_Settings::get( 'fallback_enabled', true ) ) {
            unset( $args['date_query'] );
            $posts = get_posts( $args );
        }

        return $posts;
    }

    // ── Normalizers ──────────────────────────────────────────────────────────

    /**
     * Given an array of post IDs and a section type, fetch and normalize.
     */
    private static function normalize_posts_by_ids( array $post_ids, string $type ): array {
        if ( empty( $post_ids ) ) return [];

        $posts = get_posts( [
            'post_type'      => 'any',
            'post__in'       => $post_ids,
            'posts_per_page' => count( $post_ids ),
            'orderby'        => 'post__in',
            'post_status'    => 'publish',
        ] );

        $normalizer = match ( $type ) {
            'events'    => [ __CLASS__, 'normalize_event' ],
            'forum'     => [ __CLASS__, 'normalize_forum_topic' ],
            default     => [ __CLASS__, 'normalize_post' ],
        };

        return array_map( $normalizer, $posts );
    }

    public static function normalize_post( \WP_Post $post ): array {
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

    public static function normalize_event( \WP_Post $post ): array {
        $base = self::normalize_post( $post );

        $date_raw = get_post_meta( $post->ID, 'events_start_date_and_time_', true );
        $time_raw = get_post_meta( $post->ID, 'time_of_event', true );
        $zoom_url = get_post_meta( $post->ID, 'zoom_url_for_looth_group_virtual_event', true );

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

        $tiers = wp_get_post_terms( $post->ID, 'event_tier_', [ 'fields' => 'names' ] );
        $tier  = ( ! is_wp_error( $tiers ) && ! empty( $tiers ) ) ? $tiers[0] : '';

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

    public static function normalize_forum_topic( \WP_Post $post ): array {
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

    private static function clean_excerpt( \WP_Post $post ): string {
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        return wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '…' );
    }

    private static function cpt_label( string $slug ): string {
        $obj = get_post_type_object( $slug );
        if ( $obj ) {
            return $obj->labels->singular_name ?? ucfirst( $slug );
        }
        return ucfirst( $slug );
    }

    /**
     * Get all registered CPT slugs from the registry.
     */
    private static function get_all_registered_slugs(): array {
        $registry = LG_WD_CPT_Registry::get_all_with_overrides();
        $slugs    = [];
        foreach ( $registry as $entry ) {
            if ( $entry['type'] === 'multi_cpt' ) {
                $slugs = array_merge( $slugs, array_map( 'trim', explode( ',', $entry['slug'] ) ) );
            } else {
                $slugs[] = $entry['slug'];
            }
        }
        return array_unique( array_filter( $slugs ) );
    }
}
