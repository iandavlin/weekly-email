<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Cron
 * Manages WP Cron scheduling for the weekly digest.
 * On fire, sends the latest draft issue (or auto-creates one).
 */
class LG_WD_Cron {

    const HOOK = 'lg_wd_send_digest';

    public static function init(): void {
        add_action( self::HOOK, [ __CLASS__, 'fire' ] );
        add_action( 'lg_wd_settings_saved', [ __CLASS__, 'reschedule' ] );
    }

    // ── Activation ────────────────────────────────────────────────────────────

    public static function activate(): void {
        self::schedule();
    }

    public static function deactivate(): void {
        self::clear();
    }

    // ── Fire ──────────────────────────────────────────────────────────────────

    public static function fire(): void {
        if ( ! LG_WD_Settings::get( 'enabled' ) ) {
            error_log( '[LG Weekly Digest] Cron fired but digest is disabled. Skipping.' );
            self::schedule();
            return;
        }

        error_log( '[LG Weekly Digest] Cron fired — starting digest send.' );

        try {
            // Find or create a draft issue
            $issue_id = LG_WD_Issue::get_latest_draft();

            if ( ! $issue_id ) {
                // Auto-create an issue and populate it
                $issue_id = LG_WD_Issue::create();
                if ( $issue_id ) {
                    $date_to   = date( 'Y-m-d' );
                    $date_from = date( 'Y-m-d', strtotime( '-7 days' ) );
                    $sections  = LG_WD_Issue::auto_populate( $date_from, $date_to );

                    $data = LG_WD_Issue::get_data( $issue_id );
                    $data['date_from'] = $date_from;
                    $data['date_to']   = $date_to;
                    $data['sections']  = $sections;
                    LG_WD_Issue::save_data( $issue_id, $data );
                }
            }

            if ( ! $issue_id ) {
                error_log( '[LG Weekly Digest] Failed to create issue for cron send.' );
                self::schedule();
                return;
            }

            $result = LG_WD_Sender::send_issue( $issue_id );
            error_log( '[LG Weekly Digest] Cron result: ' . ( $result['success'] ? 'SUCCESS' : 'FAIL' ) . ' — ' . $result['message'] );
        } catch ( \Throwable $e ) {
            error_log( '[LG Weekly Digest] Cron fire threw: ' . $e->getMessage() );
        }

        // Always reschedule for next week
        self::schedule();
    }

    // ── Schedule management ───────────────────────────────────────────────────

    public static function schedule(): void {
        self::clear();
        $timestamp = self::next_send_timestamp();
        if ( $timestamp ) {
            wp_schedule_single_event( $timestamp, self::HOOK );
            error_log( '[LG Weekly Digest] Scheduled next send at: ' . date( 'Y-m-d H:i:s', $timestamp ) . ' ET' );
        }
    }

    public static function reschedule(): void {
        self::schedule();
    }

    public static function clear(): void {
        $ts = wp_next_scheduled( self::HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::HOOK );
        }
        wp_clear_scheduled_hook( self::HOOK );
    }

    // ── Timestamp calculation ──────────────────────────────────────────────────

    public static function next_send_timestamp( int $add_days = 0 ): int {
        $send_day  = strtolower( LG_WD_Settings::get( 'send_day', 'monday' ) );
        $send_time = LG_WD_Settings::get( 'send_time', '09:00' );

        try {
            $tz   = new DateTimeZone( LG_WD_TIMEZONE );
            $now  = new DateTime( 'now', $tz );
            $next = new DateTime( "next {$send_day} {$send_time}", $tz );

            // If today IS the send day but time hasn't passed, use today
            $today_name = strtolower( $now->format( 'l' ) );
            if ( $today_name === $send_day ) {
                $today_send = new DateTime( "today {$send_time}", $tz );
                if ( $today_send > $now ) {
                    $next = $today_send;
                }
            }

            if ( $add_days > 0 ) {
                $next->modify( "+{$add_days} days" );
            }

            return $next->getTimestamp();
        } catch ( \Exception $e ) {
            error_log( '[LG Weekly Digest] next_send_timestamp error: ' . $e->getMessage() );
            return 0;
        }
    }

    public static function next_send_label(): string {
        $ts = wp_next_scheduled( self::HOOK );
        if ( ! $ts ) return 'Not scheduled';

        $tz = new DateTimeZone( LG_WD_TIMEZONE );
        $dt = new DateTime( '@' . $ts );
        $dt->setTimezone( $tz );
        return $dt->format( 'D M j · g:i A T' );
    }
}
