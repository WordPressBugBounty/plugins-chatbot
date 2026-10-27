<?php
/**
 * Campaign business rules.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Jobs\Scheduler;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Create/update/duplicate campaigns, start sending and shape API responses.
 * Request/response field names follow API-CONTRACT §3.2.
 */
class CampaignService {

	/** Statuses PUT can never change (owned by send / the tick). */
	const LOCKED = array( 'sending', 'sent', 'scheduled' );

	/**
	 * Campaigns.
	 *
	 * @var CampaignRepository
	 */
	private $campaigns;

	/**
	 * Messages.
	 *
	 * @var MessageRepository
	 */
	private $messages;

	/**
	 * Constructor.
	 *
	 * @param CampaignRepository|null $campaigns Campaigns.
	 * @param MessageRepository|null  $messages  Messages.
	 */
	public function __construct( ?CampaignRepository $campaigns = null, ?MessageRepository $messages = null ) {
		$this->campaigns = $campaigns ? $campaigns : new CampaignRepository();
		$this->messages  = $messages ? $messages : new MessageRepository();
	}

	/**
	 * Find a campaign row.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public function find( $id ) {
		return $this->campaigns->find( $id );
	}

	/**
	 * List rows with stats (newest first).
	 *
	 * @param array $args page, per_page, search, status.
	 * @return array{items:array[],total:int}
	 */
	public function search( array $args ) {
		$result = $this->campaigns->search( $args );
		$stats  = $this->messages->stats_for_campaigns( wp_list_pluck( $result['items'], 'id' ) );
		$items  = array();
		foreach ( $result['items'] as $row ) {
			$item = $this->format( $row, isset( $stats[ $row['id'] ] ) ? $stats[ $row['id'] ] : array() );
			unset( $item['email_body'], $item['content'] );
			$items[] = $item;
		}
		return array(
			'items' => $items,
			'total' => $result['total'],
		);
	}

	/**
	 * Create a draft campaign.
	 *
	 * @param array $input Request body.
	 * @return array|\WP_Error Formatted campaign.
	 */
	public function create( array $input ) {
		$data           = $this->fields( $input, null );
		$data['status'] = 'draft';
		$id             = $this->campaigns->insert( $data );
		if ( ! $id ) {
			return new \WP_Error( 'wpbot_crm_campaign_save_failed', __( 'The campaign could not be saved.', 'chatbot' ), array( 'status' => 500 ) );
		}
		return $this->get( $id );
	}

	/**
	 * Update a campaign. Status is only accepted as draft|paused and never overrides sending/sent/scheduled.
	 *
	 * @param int   $id    Campaign id.
	 * @param array $input Request body.
	 * @return array|\WP_Error
	 */
	public function update( $id, array $input ) {
		$campaign = $this->campaigns->find( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		$data = $this->fields( $input, $campaign );
		if ( isset( $input['status'] ) && ! in_array( $campaign['status'], self::LOCKED, true ) && in_array( $input['status'], array( 'draft', 'paused' ), true ) ) {
			$data['status'] = (string) $input['status'];
		}
		$this->campaigns->update( $id, $data );
		return $this->get( $id );
	}

	/**
	 * Delete a campaign (queued messages go with it).
	 *
	 * @param int $id Campaign id.
	 * @return true|\WP_Error
	 */
	public function delete( $id ) {
		if ( ! $this->campaigns->find( $id ) ) {
			return self::not_found();
		}
		$this->campaigns->delete( $id );
		return true;
	}

	/**
	 * Copy a campaign as a fresh draft titled "… (Copy)".
	 *
	 * @param int $id Campaign id.
	 * @return array|\WP_Error New campaign.
	 */
	public function duplicate( $id ) {
		$campaign = $this->campaigns->find( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		$copy = array();
		foreach ( array( 'subject', 'preview_text', 'from_name', 'from_email', 'reply_to', 'content_html', 'template_id', 'audience', 'settings' ) as $key ) {
			$copy[ $key ] = $campaign[ $key ];
		}
		/* translators: %s: campaign name. */
		$copy['name']   = sprintf( __( '%s (Copy)', 'chatbot' ), $campaign['name'] );
		$copy['status'] = 'draft';
		$new_id         = $this->campaigns->insert( $copy );
		return $new_id ? $this->get( $new_id ) : new \WP_Error( 'wpbot_crm_campaign_save_failed', __( 'The campaign could not be duplicated.', 'chatbot' ), array( 'status' => 500 ) );
	}

	/**
	 * Validate and start sending (or schedule when scheduled_at is in the future).
	 *
	 * @param int $id Campaign id.
	 * @return array|\WP_Error {success, queued, status, message}
	 */
	public function start( $id ) {
		$campaign = $this->campaigns->find( $id );
		if ( ! $campaign ) {
			return self::not_found();
		}
		if ( 'sent' === $campaign['status'] ) {
			return new \WP_Error( 'wpbot_crm_campaign_sent', __( 'This campaign has already been sent. Duplicate it to send it again.', 'chatbot' ), array( 'status' => 409 ) );
		}
		if ( '' === trim( (string) $campaign['subject'] ) ) {
			return new \WP_Error( 'wpbot_crm_campaign_invalid', __( 'Add a subject line before sending this campaign.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( '' === trim( (string) $campaign['content_html'] ) ) {
			return new \WP_Error( 'wpbot_crm_campaign_invalid', __( 'The email body is empty. Add some content before sending.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$count = ( new AudienceResolver() )->count( (array) $campaign['settings'] );
		if ( $count <= 0 ) {
			return new \WP_Error( 'wpbot_crm_no_recipients', __( 'No recipients match this campaign. Choose a list or tag with subscribed contacts.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'sending' === $campaign['status'] ) {
			return array(
				'success' => true,
				'queued'  => $count,
				'status'  => 'sending',
				'message' => __( 'This campaign is already sending.', 'chatbot' ),
			);
		}

		$scheduled = (string) $campaign['scheduled_at'];
		if ( '' !== $scheduled && strtotime( $scheduled . ' UTC' ) > time() ) {
			$this->campaigns->set_status( $id, 'scheduled' );
			return array(
				'success' => true,
				'queued'  => $count,
				'status'  => 'scheduled',
				'message' => __( 'Campaign scheduled.', 'chatbot' ),
			);
		}

		$this->campaigns->set_status(
			$id,
			'sending',
			array(
				'started_at' => Dates::now(),
				'recipients' => 0,
			)
		);
		return array(
			'success' => true,
			'queued'  => $count,
			'status'  => 'sending',
			'message' => __( 'Campaign is queued and will start sending shortly.', 'chatbot' ),
		);
	}

	/**
	 * Run one tick now and report progress for one campaign.
	 *
	 * @param int $id Campaign id.
	 * @return array|\WP_Error {success, processed, remaining, status}
	 */
	public function process( $id ) {
		if ( ! $this->campaigns->find( $id ) ) {
			return self::not_found();
		}
		$ran    = Scheduler::run_now( 20 );
		$result = Scheduler::last_result();
		$after  = $this->campaigns->find( $id );
		$out    = array(
			'success'   => true,
			'processed' => $ran && isset( $result['processed'] ) ? (int) $result['processed'] : 0,
			'remaining' => $this->messages->remaining_for_campaign( $id ),
			'status'    => $after ? $after['status'] : '',
		);
		if ( ! $ran ) {
			$out['message'] = __( 'Another batch is already running. Sending continues in the background.', 'chatbot' );
		}
		return $out;
	}

	/**
	 * Formatted campaign by id.
	 *
	 * @param int $id Id.
	 * @return array|\WP_Error
	 */
	public function get( $id ) {
		$row = $this->campaigns->find( $id );
		if ( ! $row ) {
			return self::not_found();
		}
		$stats = $this->messages->stats_for_campaigns( array( $id ) );
		return $this->format( $row, isset( $stats[ $id ] ) ? $stats[ $id ] : array() );
	}

	/**
	 * Mailer context for a campaign (From/Reply-To, UTM, preview text).
	 *
	 * @param array $campaign Campaign row.
	 * @return array
	 */
	public static function send_context( array $campaign ) {
		$settings = is_array( $campaign['settings'] ) ? $campaign['settings'] : array();
		$slug     = sanitize_title( (string) $campaign['name'] );
		return array(
			'campaign_id'  => (int) $campaign['id'],
			'from_name'    => (string) $campaign['from_name'],
			'from_email'   => (string) $campaign['from_email'],
			'reply_to'     => (string) $campaign['reply_to'],
			'preview_text' => (string) $campaign['preview_text'],
			'utm'          => ! empty( $settings['utm_status'] ),
			'utm_campaign' => '' !== $slug ? $slug : 'campaign-' . (int) $campaign['id'],
		);
	}

	/**
	 * API shape (API-CONTRACT §3.2) with documented aliases.
	 *
	 * @param array $row   Campaign row.
	 * @param array $stats {sent, opened, clicked, queued}.
	 * @return array
	 */
	public function format( array $row, array $stats = array() ) {
		$settings = self::normalize_settings( is_array( $row['settings'] ) ? $row['settings'] : array() );
		$sent     = isset( $stats['sent'] ) ? (int) $stats['sent'] : (int) $row['sent'];
		$opened   = isset( $stats['opened'] ) ? (int) $stats['opened'] : 0;
		$clicked  = isset( $stats['clicked'] ) ? (int) $stats['clicked'] : 0;
		return array(
			'id'               => (int) $row['id'],
			'title'            => (string) $row['name'],
			'name'             => (string) $row['name'],
			'subject'          => (string) $row['subject'],
			'email_subject'    => (string) $row['subject'],
			'email_pre_header' => (string) $row['preview_text'],
			'preview_text'     => (string) $row['preview_text'],
			'email_body'       => (string) $row['content_html'],
			'content'          => (string) $row['content_html'],
			'from_name'        => (string) $row['from_name'],
			'from_email'       => (string) $row['from_email'],
			'reply_to'         => (string) $row['reply_to'],
			'template_id'      => (int) $row['template_id'],
			'utm_status'       => (bool) $settings['utm_status'],
			'settings'         => $settings,
			'status'           => (string) $row['status'],
			'recipients'       => (int) $row['recipients'],
			'sent_count'       => $sent,
			'failed_count'     => (int) $row['failed'],
			'queued_count'     => isset( $stats['queued'] ) ? (int) $stats['queued'] : 0,
			'open_count'       => $opened,
			'click_count'      => $clicked,
			'open_rate'        => self::rate( $opened, $sent ),
			'click_rate'       => self::rate( $clicked, $sent ),
			'scheduled_at'     => Dates::iso( $row['scheduled_at'] ),
			'started_at'       => Dates::iso( $row['started_at'] ),
			'finished_at'      => Dates::iso( $row['finished_at'] ),
			'created_at'       => Dates::iso( $row['created_at'] ),
			'updated_at'       => Dates::iso( $row['updated_at'] ),
		);
	}

	/**
	 * Map request fields (with aliases) to columns.
	 *
	 * @param array      $input    Request body.
	 * @param array|null $existing Existing row (update) or null (create).
	 * @return array
	 */
	private function fields( array $input, $existing ) {
		$data = array();
		$map  = array(
			'name'         => array( 'title', 'name' ),
			'subject'      => array( 'email_subject', 'subject' ),
			'preview_text' => array( 'email_pre_header', 'preview_text' ),
			'content_html' => array( 'email_body', 'content', 'content_html' ),
		);
		foreach ( $map as $column => $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
					$value           = (string) $input[ $key ];
					$data[ $column ] = 'content_html' === $column ? self::kses( $value ) : sanitize_text_field( $value );
					break;
				}
			}
		}
		if ( null === $existing && empty( $data['name'] ) ) {
			$data['name'] = __( 'Untitled campaign', 'chatbot' );
		}
		foreach ( array( 'from_name' => 'sanitize_text_field', 'from_email' => 'sanitize_email', 'reply_to' => 'sanitize_email' ) as $key => $clean ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
				$data[ $key ] = call_user_func( $clean, (string) $input[ $key ] );
			}
		}
		if ( isset( $input['template_id'] ) ) {
			$data['template_id'] = absint( $input['template_id'] ) ? absint( $input['template_id'] ) : null;
		}
		if ( array_key_exists( 'scheduled_at', $input ) ) {
			$data['scheduled_at'] = is_scalar( $input['scheduled_at'] ) && '' !== trim( (string) $input['scheduled_at'] ) ? Dates::to_utc( (string) $input['scheduled_at'] ) : null;
			$data['scheduled_at'] = '' === $data['scheduled_at'] ? null : $data['scheduled_at'];
		}

		$settings = $existing && is_array( $existing['settings'] ) ? $existing['settings'] : array();
		if ( isset( $input['settings'] ) ) {
			$incoming = is_string( $input['settings'] ) ? json_decode( $input['settings'], true ) : $input['settings'];
			if ( is_array( $incoming ) ) {
				if ( isset( $incoming['list_ids'] ) && ! isset( $incoming['lists'] ) ) {
					$incoming['lists'] = $incoming['list_ids'];
				}
				if ( isset( $incoming['tag_ids'] ) && ! isset( $incoming['tags'] ) ) {
					$incoming['tags'] = $incoming['tag_ids'];
				}
				$settings = array_merge( $settings, $incoming );
			}
		}
		if ( array_key_exists( 'utm_status', $input ) ) {
			$settings['utm_status'] = $input['utm_status'];
		}
		if ( isset( $input['settings'] ) || array_key_exists( 'utm_status', $input ) || null === $existing ) {
			$data['settings'] = self::normalize_settings( $settings );
			$data['audience'] = AudienceResolver::normalize( $data['settings'] );
		}
		return $data;
	}

	/**
	 * Canonical settings object; Pro-only switches forced off for free users.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function normalize_settings( array $settings ) {
		$audience = AudienceResolver::normalize( $settings );
		return array(
			'send_to'       => $audience['send_to'],
			'lists'         => $audience['lists'],
			'tags'          => $audience['tags'],
			'exclude_lists' => $audience['exclude_lists'],
			'exclude_tags'  => $audience['exclude_tags'],
			'enable_ab'     => ! empty( $settings['enable_ab'] ) && self::truthy( $settings['enable_ab'] ),
			'send_throttle' => isset( $settings['send_throttle'] ) ? max( 0, (int) $settings['send_throttle'] ) : 0,
			'all'           => 'all' === $audience['send_to'],
			'utm_status'    => isset( $settings['utm_status'] ) && self::truthy( $settings['utm_status'] ),
		);
	}

	/**
	 * "12.5%" style rate.
	 *
	 * @param int $part  Numerator.
	 * @param int $whole Denominator.
	 * @return string
	 */
	private static function rate( $part, $whole ) {
		return number_format( $whole > 0 ? ( $part / $whole ) * 100 : 0, 1, '.', '' ) . '%';
	}

	/**
	 * Loose boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function truthy( $value ) {
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Email-safe HTML filter (post tags plus inline styles and layout tables).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function kses( $html ) {
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $html;
		}
		return wp_kses_post( $html );
	}

	/**
	 * 404 error.
	 *
	 * @return \WP_Error
	 */
	private static function not_found() {
		return new \WP_Error( 'wpbot_crm_campaign_not_found', __( 'Campaign not found.', 'chatbot' ), array( 'status' => 404 ) );
	}
}
