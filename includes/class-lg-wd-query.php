<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Query
 *
 * Two modes:
 *  1. Auto-populate: fetch post IDs for a date range per section (compose page).
 *  2. Issue-based: build full payload from curated post IDs (email rendering).
 */
class LG_WD_Query {

    // ── Mode 1: Auto-populate ────────────────────────────────────────────────

    /**
     * Fetch post IDs for a single registry section within a date range.
     */
    public static function fetch_ids_for_section( array $section, string $date_from, string $date_to ): array {
        $posts = self::fetch_posts_for_section( $section, $date_from, $date_to );
        return wp_list_pluck( $posts, 'ID' );
    }

    private static function fetch_posts_for_section( array $section, string $date_from, string $date_to ): array {
        $slug      = $section['slug'] ?? '';
        $max       = (int) ( $section['max_items'] ?? 5 );
        $sort_mode = $section['sort_mode'] ?? 'newest';
        $tag       = $section['tag_filter'] ?? '';
        $taxonomy  = $section['tag_taxonomy'] ?? 'post_tag';

        if ( empty( $slug ) ) return [];

        // upcoming sort: future-first by event date meta (for event CPTs)
        if ( $sort_mode === 'upcoming' ) {
            return self::fetch_upcoming( $slug, $max, $tag, $taxonomy );
        }

        return self::fetch_cpt_posts( $slug, $max, $date_from, $date_to, $tag, $taxonomy );
    }

    // ── Mode 2: Issue-based ──────────────────────────────────────────────────

    /**
     * Build the full content payload from an issue's curated data.
     */
    public static function build_payload_from_issue( array $issue_data ): array {
        $sections   = $issue_data['sections'] ?? [];
        $payload    = [];
        $skip_empty = LG_WD_Settings::get( 'skip_empty', true );

        foreach ( $sections as $section ) {
            $post_ids = $section['post_ids'] ?? [];
            if ( empty( $post_ids ) && $skip_empty ) continue;

            $items = self::normalize_posts_by_ids( $post_ids );

            if ( empty( $items ) && $skip_empty ) continue;

            // Resolve template: prefer explicit, fall back from legacy 'type' field
            $template = $section['template'] ?? self::type_to_template( $section['type'] ?? '' );

            $payload[ $section['key'] ] = [
                'section' => [
                    'key'      => $section['key'],
                    'label'    => $section['label'],
                    'template' => $template,
                ],
                'items'      => $items,
                'is_archive' => false,
            ];
        }

        return $payload;
    }

    // ── Search ───────────────────────────────────────────────────────────────

    public static function search_posts( string $search_term, string $post_type = '', int $limit = 20 ): array {
        $types = $post_type ? [ $post_type ] : self::get_all_registered_slugs();

        $posts = get_posts( [
            'post_type'      => $types ?: 'any',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            's'              => $search_term,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

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
     * Generic CPT fetch by date range, with optional tag filter.
     */
    private static function fetch_cpt_posts(
        string $slug,
        int $max,
        string $date_from,
        string $date_to,
        string $tag = '',
        string $taxonomy = 'post_tag'
    ): array {
        $args = [
            'post_type'      => $slug,
            'post_status'    => [ 'publish', 'closed' ], // 'closed' for bbPress topics
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

        if ( $tag && $taxonomy ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $tag,
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
     * Upcoming sort: future-first by events_start_date_and_time_ meta.
     * Falls back to most recent past events if none upcoming.
     */
    private static function fetch_upcoming( string $slug, int $max, string $tag = '', string $taxonomy = 'post_tag' ): array {
        $today = current_time( 'Ymd' );

        $args = [
            'post_type'      => $slug,
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'meta_key'       => 'events_start_date_and_time_',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'events_start_date_and_time_',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ];

        if ( $tag && $taxonomy ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $tag,
                ],
            ];
        }

        $posts = get_posts( $args );

        // Fallback to most recent past events
        if ( empty( $posts ) ) {
            $args['meta_query'][0]['compare'] = '<';
            $args['order'] = 'DESC';
            $posts = get_posts( $args );
        }

        return $posts;
    }

    // ── Normalizers ──────────────────────────────────────────────────────────

    private static function normalize_posts_by_ids( array $post_ids ): array {
        if ( empty( $post_ids ) ) return [];

        $posts = get_posts( [
            'post_type'      => 'any',
            'post__in'       => $post_ids,
            'posts_per_page' => count( $post_ids ),
            'orderby'        => 'post__in',
            'post_status'    => [ 'publish', 'closed' ],
        ] );

        return array_map( [ __CLASS__, 'normalize_post' ], $posts );
    }

    public static function normalize_post( \WP_Post $post ): array {
        $thumb_url = has_post_thumbnail( $post->ID )
            ? get_the_post_thumbnail_url( $post->ID, 'thumbnail' )
            : '';

        return [
            'id'         => $post->ID,
            'title'      => get_the_title( $post ),
            'url'        => get_permalink( $post ),
            'excerpt'    => self::clean_excerpt( $post ),
            'thumb_url'  => $thumb_url,
            'date'       => get_the_date( 'M j', $post ),
            'post_type'  => $post->post_type,
            'type_label' => self::cpt_label( $post->post_type ),
        ];
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Map legacy 'type' values to template slugs for backward compat.
     */
    public static function type_to_template( string $type ): string {
        return match ( $type ) {
            'events'    => 'date-forward',
            'forum'     => 'list',
            'sponsor'   => 'sponsor',
            'spotlight' => 'card',
            default     => 'card',
        };
    }

    private static function clean_excerpt( \WP_Post $post ): string {
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }
        return wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '…' );
    }

    private static function cpt_label( string $slug ): string {
        $obj = get_post_type_object( $slug );
        return $obj ? ( $obj->labels->singular_name ?? ucfirst( $slug ) ) : ucfirst( $slug );
    }

    private static function get_all_registered_slugs(): array {
        $slugs = [];
        foreach ( LG_WD_CPT_Registry::get_all() as $entry ) {
            $slugs[] = $entry['slug'];
        }
        return array_unique( array_filter( $slugs ) );
    }
}
