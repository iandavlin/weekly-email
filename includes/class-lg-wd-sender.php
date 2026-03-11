<?php
defined( 'ABSPATH' ) || exit;

/**
 * LG_WD_Sender
 * Creates and dispatches the FluentCRM campaign.
 *
 * Uses the PROVEN two-step pattern from LG Event Reminders v3.2.0+:
 *   1. getSubscriberIdsBySegmentSettings()
 *   2. subscribe()
 *
 * NEVER use subscribeBySegment() — it silently returns 0 recipients.
 * NEVER use Subject::create() — throws "Unknown column campaign_id".
 * Settings subscribers array MUST include both 'list' and 'tag' keys.
 */
class LG_WD_Sender {

    /**
     * Build the payload, render the email, create the campaign, and send.
     *
     * @param  bool   $dry_run  If true, returns rendered HTML without sending.
     * @param  string $to_email If set (test send), sends only to this address via WP mail fallback.
     * @return array  [ 'success' => bool, 'message' => string, 'campaign_id' => int|null ]
     */
    public static function send( bool $dry_run = false, string $to_email = '' ): array {
        // 1. Guard: FluentCRM must be loaded
        if ( ! class_exists( 'FluentCrm\App\Models\Campaign' ) ) {
            self::log( 'ERROR: FluentCRM Campaign model not found. Is FluentCRM active?' );
            return [ 'success' => false, 'message' => 'FluentCRM not available.', 'campaign_id' => null ];
        }

        // 2. Build content payload
        self::log( 'INFO: Building content payload.' );
        $payload = LG_WD_Query::build_payload();

        if ( empty( $payload ) ) {
            self::log( 'WARNING: No content found in payload. Aborting send.' );
            return [ 'success' => false, 'message' => 'No content available.', 'campaign_id' => null ];
        }

        $item_count = array_sum( array_map( fn( $p ) => count( $p['items'] ), $payload ) );
        self::log( "INFO: Payload built. {$item_count} items across " . count( $payload ) . ' sections.' );

        // 3. Render HTML
        $html    = LG_WD_Email_Builder::build( $payload );
        $subject = LG_WD_Email_Builder::build_subject( $payload );

        if ( $dry_run ) {
            self::log( 'INFO: Dry run — returning HTML without sending.' );
            return [ 'success' => true, 'message' => 'Dry run complete.', 'html' => $html, 'subject' => $subject, 'campaign_id' => null ];
        }

        // 4. Test send (single address via wp_mail)
        if ( $to_email ) {
            return self::send_test( $to_email, $subject, $html );
        }

        // 5. Full FluentCRM send
        return self::send_via_fluentcrm( $subject, $html );
    }

    // ── FluentCRM campaign pipeline ──────────────────────────────────────────

    private static function send_via_fluentcrm( string $subject, string $html ): array {
        $settings   = LG_WD_Settings::get_all();
        $list_id    = (string) LG_WD_FCRM_LIST_ID;
        $from_name  = $settings['from_name'];
        $from_email = $settings['from_email'];

        // Subscriber segment settings — MUST include both list AND tag
        $subscriber_settings = [
            'subscribers' => [
                [ 'list' => $list_id, 'tag' => 'all' ],
            ],
        ];

        // Campaign data
        $scheduled_at = current_time( 'mysql' );

        $campaign_data = [
            'title'        => 'Weekly Digest — ' . date_i18n( 'F j, Y' ),
            'subject'      => $subject,
            'status'       => 'scheduled',
            'type'         => 'regular',
            'template_id'  => 0,
            'email_body'   => $html,
            'settings'     => [
                'mailer_settings' => [
                    'from_name'  => $from_name,
                    'from_email' => $from_email,
                    'reply_to'   => $from_email,
                    'is_custom'  => 'yes',
                ],
                'subscribers'     => $subscriber_settings['subscribers'],
                'sending_filter'  => 'list_tag',
                'template_config' => [
                    'content_width'   => '600',
                    'body_bg_color'   => '#e8e2d8',
                    'content_bg_color'=> '#FAF6EE',
                    'content_font'    => 'Arial, Helvetica, sans-serif',
                    'footer_text_color'=> '#5C4E3A',
                    'disable_footer'  => 'yes', // we have our own footer
                ],
            ],
            'scheduled_at' => $scheduled_at,
        ];

        self::log( 'INFO: Creating FluentCRM campaign: ' . $campaign_data['title'] );

        try {
            $campaign = \FluentCrm\App\Models\Campaign::create( $campaign_data );
        } catch ( \Exception $e ) {
            self::log( 'ERROR: Campaign::create() threw: ' . $e->getMessage() );
            return [ 'success' => false, 'message' => 'Campaign creation failed: ' . $e->getMessage(), 'campaign_id' => null ];
        }

        if ( ! $campaign || ! $campaign->id ) {
            self::log( 'ERROR: Campaign::create() returned empty/null.' );
            return [ 'success' => false, 'message' => 'Campaign creation returned null.', 'campaign_id' => null ];
        }

        self::log( "INFO: Campaign created. ID={$campaign->id}" );

        // Step 1: Resolve subscriber IDs
        self::log( 'INFO: Resolving subscriber IDs via getSubscriberIdsBySegmentSettings.' );
        try {
            $result = $campaign->getSubscriberIdsBySegmentSettings( $subscriber_settings );
            $subscriber_ids = $result['subscriber_ids'] ?? [];
        } catch ( \Exception $e ) {
            self::log( 'ERROR: getSubscriberIdsBySegmentSettings threw: ' . $e->getMessage() );
            return [ 'success' => false, 'message' => 'Subscriber resolution failed.', 'campaign_id' => $campaign->id ];
        }

        if ( empty( $subscriber_ids ) ) {
            self::log( "WARNING: Zero subscribers resolved for List ID {$list_id}. Check list/tag config." );
            return [ 'success' => false, 'message' => 'No subscribers resolved.', 'campaign_id' => $campaign->id ];
        }

        self::log( 'INFO: Resolved ' . count( $subscriber_ids ) . ' subscribers.' );

        // Step 2: Create CampaignEmail rows
        self::log( 'INFO: Subscribing via $campaign->subscribe().' );
        try {
            $campaign->subscribe( $subscriber_ids );
        } catch ( \Exception $e ) {
            self::log( 'ERROR: subscribe() threw: ' . $e->getMessage() );
            return [ 'success' => false, 'message' => 'subscribe() failed.', 'campaign_id' => $campaign->id ];
        }

        // Step 3: Verify CampaignEmail rows were created
        $email_count = \FluentCrm\App\Models\CampaignEmail::where( 'campaign_id', $campaign->id )->count();
        self::log( "INFO: CampaignEmail rows created: {$email_count}" );

        if ( $email_count === 0 ) {
            self::log( 'ERROR: CampaignEmail rows = 0 after subscribe(). Pipeline failure.' );
            return [ 'success' => false, 'message' => 'CampaignEmail rows not created.', 'campaign_id' => $campaign->id ];
        }

        // Update campaign status to processing so FluentCRM picks it up
        $campaign->status = 'working';
        $campaign->save();

        // Store send record
        self::record_send( $campaign->id, $campaign_data['title'], $email_count );

        self::log( "SUCCESS: Campaign ID={$campaign->id} dispatched to {$email_count} subscribers." );

        return [
            'success'     => true,
            'message'     => "Digest sent to {$email_count} subscribers.",
            'campaign_id' => $campaign->id,
        ];
    }

    // ── Test send ────────────────────────────────────────────────────────────

    private static function send_test( string $to_email, string $subject, string $html ): array {
        self::log( "INFO: Sending test email to {$to_email}" );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . LG_WD_Settings::get( 'from_name' ) . ' <' . LG_WD_Settings::get( 'from_email' ) . '>',
        ];

        $sent = wp_mail( $to_email, '[TEST] ' . $subject, $html, $headers );

        if ( $sent ) {
            self::log( "INFO: Test email sent successfully to {$to_email}" );
            return [ 'success' => true, 'message' => "Test sent to {$to_email}.", 'campaign_id' => null ];
        }

        self::log( "ERROR: wp_mail() failed for test send to {$to_email}" );
        return [ 'success' => false, 'message' => 'wp_mail() failed.', 'campaign_id' => null ];
    }

    // ── Send history ─────────────────────────────────────────────────────────

    private static function record_send( int $campaign_id, string $title, int $recipients ): void {
        $history   = get_option( 'lg_wd_send_history', [] );
        $history[] = [
            'campaign_id' => $campaign_id,
            'title'       => $title,
            'recipients'  => $recipients,
            'sent_at'     => current_time( 'mysql' ),
        ];
        // Keep last 20
        if ( count( $history ) > 20 ) {
            $history = array_slice( $history, -20 );
        }
        update_option( 'lg_wd_send_history', $history, false );
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    private static function log( string $message ): void {
        $always = str_starts_with( $message, 'ERROR' ) || str_starts_with( $message, 'WARNING' ) || str_starts_with( $message, 'SUCCESS' );
        if ( $always || ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
            error_log( '[LG Weekly Digest] ' . $message );
        }
    }
}
