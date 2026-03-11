<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Cron
 * Manages WP Cron scheduling for the weekly digest.
 * Recalculates next send time whenever settings change.
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
            return;
        }

        error_log( '[LG Weekly Digest] Cron fired — starting digest send.' );
        $result = LG_WD_Sender::send();
        error_log( '[LG Weekly Digest] Cron result: ' . ( $result['success'] ? 'SUCCESS' : 'FAIL' ) . ' — ' . $result['message'] );
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

    /**
     * Re-schedule after each fire so it repeats weekly.
     * Called by adding action in send flow.
     */
    public static function reschedule_after_send(): void {
        // Schedule next week
        $timestamp = self::next_send_timestamp( 7 );
        if ( $timestamp ) {
            wp_schedule_single_event( $timestamp, self::HOOK );
            error_log( '[LG Weekly Digest] Next digest scheduled for: ' . date( 'Y-m-d H:i:s', $timestamp ) . ' ET' );
        }
    }

    // ── Timestamp calculation ──────────────────────────────────────────────────

    /**
     * Calculate Unix timestamp for the next (or +$add_days) occurrence
     * of the configured send_day + send_time in America/New_York.
     */
    public static function next_send_timestamp( int $add_days = 0 ): int {
        $send_day  = strtolower( LG_WD_Settings::get( 'send_day', 'monday' ) );
        $send_time = LG_WD_Settings::get( 'send_time', '09:00' );

        try {
            $tz   = new DateTimeZone( LG_WD_TIMEZONE );
            $now  = new DateTime( 'now', $tz );
            $next = new DateTime( "next {$send_day} {$send_time}", $tz );

            // If today IS the send day but time hasn't passed yet, use today
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

    /**
     * Human-readable next send date for the admin UI.
     */
    public static function next_send_label(): string {
        $ts = wp_next_scheduled( self::HOOK );
        if ( ! $ts ) return 'Not scheduled';

        $tz   = new DateTimeZone( LG_WD_TIMEZONE );
        $dt   = new DateTime( '@' . $ts );
        $dt->setTimezone( $tz );
        return $dt->format( 'D M j · g:i A T' );
    }
}

// Re-schedule after every successful fire
add_action( LG_WD_Cron::HOOK, [ 'LG_WD_Cron', 'reschedule_after_send' ], 20 );
