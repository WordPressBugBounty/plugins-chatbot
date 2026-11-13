<?php
/**
 * Turns the Create post form into one draft per account, runs every
 * network's checks and saves the post.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Support\Brand_Access;
use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Accounts\Connector;
use QuantumCloud\Social\Media\Media;
use QuantumCloud\Social\Media\Public_Media_Check;
use QuantumCloud\Social\Module;
use QuantumCloud\Social\Networks\Draft;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Composer.
 */
final class Composer {

	/**
	 * Build drafts and checks from form input.
	 *
	 * @param array $input title, accounts (ids), text, link, images (attachment ids),
	 *                     overrides (network => [text]), extras (network => array), schedule (site time Y-m-d H:i or ''), status.
	 * @return array{targets:array, checks:array, problems:string[], schedule_at:string}
	 */
	public static function build( array $input ) {
		$title     = sanitize_text_field( isset( $input['title'] ) ? $input['title'] : '' );
		$text      = sanitize_textarea_field( isset( $input['text'] ) ? $input['text'] : '' );
		$link      = esc_url_raw( isset( $input['link'] ) ? (string) $input['link'] : '' );
		$images    = array_slice( array_filter( array_map( 'absint', isset( $input['images'] ) ? (array) $input['images'] : array() ) ), 0, 10 );
		$overrides = isset( $input['overrides'] ) && is_array( $input['overrides'] ) ? $input['overrides'] : array();
		$extras    = isset( $input['extras'] ) && is_array( $input['extras'] ) ? $input['extras'] : array();
		$campaign  = '' !== $title ? $title : wp_trim_words( $text, 6, '' );
		$video     = self::video( isset( $input['video'] ) ? absint( $input['video'] ) : 0 );
		/**
		 * Networks that take a video (the Master module adds them). Network id => true.
		 *
		 * @param array<string,bool> $networks Networks.
		 */
		$video_ok = (array) apply_filters( 'qcld_wpbot_social/video_networks', array() );

		$targets  = array();
		$checks   = array();
		$problems = array();
		$public   = null;
		$ids      = array();
		foreach ( isset( $input['accounts'] ) ? (array) $input['accounts'] : array() as $entry ) {
			// "manual:whatsapp" picks a Post yourself network; its account row is made on first use.
			$ids[] = is_string( $entry ) && 0 === strpos( $entry, 'manual:' ) ? Account_Store::ensure_manual( substr( $entry, 7 ) ) : absint( $entry );
		}
		foreach ( array_unique( array_filter( $ids ) ) as $account_id ) {
			$account = Account_Store::get( $account_id );
			if ( ! $account ) {
				continue;
			}
			if ( ! \QuantumCloud\Social\Support\Brand_Access::can( (int) $account['brand_id'] ) ) {
				$problems[] = __( 'You are not allowed to post to one of the chosen accounts.', 'chatbot' );
				continue;
			}
			$network = Module::instance()->network( $account['network'] );
			if ( ! $network ) {
				continue;
			}
			$override = isset( $overrides[ $account['network'] ] ) ? $overrides[ $account['network'] ] : '';
			$override = is_array( $override ) ? ( isset( $override['text'] ) ? (string) $override['text'] : '' ) : (string) $override;
			$net_text = '' !== trim( $override ) ? sanitize_textarea_field( $override ) : $text;
			$net_imgs = array();
			$shape    = array();
			foreach ( $images as $attachment_id ) {
				$image = Media::image( $attachment_id, $account['network'] );
				if ( $image ) {
					$net_imgs[] = array(
						'url'  => $image['url'],
						'mime' => $image['mime'],
					);
					$ratio      = Media::ratio_problem( $account['network'], $image['width'], $image['height'] );
					if ( '' !== $ratio ) {
						$shape[] = $ratio;
					}
				}
			}
			$net_extras = isset( $extras[ $account['network'] ] ) ? self::clean_extras( (array) $extras[ $account['network'] ] ) : array();
			if ( $video && ! empty( $video_ok[ $account['network'] ] ) ) {
				$net_extras['video'] = $video;
			}
			$draft  = new Draft(
				$net_text,
				Tracked_Links::tag( $link, $account['network'], $campaign, (int) $account['id'] ),
				$net_imgs,
				$net_extras
			);
			$issues = array_merge( $network->validate( $draft ), $shape );
			if ( $video && empty( $video_ok[ $account['network'] ] ) && array() === $net_imgs ) {
				$issues[] = __( 'This network does not take a video here. Add an image for it, or leave it out.', 'chatbot' );
			}
			$limits = $network->limits();
			if ( ! empty( $limits['needs_public_media'] ) && array() !== $net_imgs ) {
				$public = null === $public ? Public_Media_Check::result() : $public;
				if ( ! $public['ok'] ) {
					$issues[] = $public['reason'];
				}
			}
			if ( 'ok' !== $account['status'] ) {
				$issues[] = __( 'This account needs to be reconnected.', 'chatbot' );
			}
			$label    = $network->label() . ' · ' . $account['name'];
			$checks[] = array(
				'account_id' => $account['id'],
				'label'      => $label,
				'length'     => mb_strlen( $draft->text() ),
				'max'        => isset( $limits['max_text'] ) ? (int) $limits['max_text'] : 0,
				'problems'   => array_values( array_unique( $issues ) ),
				/**
				 * Information for one target that does not block posting (for example a network's price per post).
				 *
				 * @param string[] $notes   Notes.
				 * @param Draft    $draft   The network's draft.
				 * @param array    $account Account.
				 */
				'notes'      => array_values( array_filter( array_map( 'strval', (array) apply_filters( 'qcld_wpbot_social/check_notes', array(), $draft, $account ) ) ) ),
			);
			foreach ( array_unique( $issues ) as $issue ) {
				$problems[] = $label . ': ' . $issue;
			}
			$targets[] = array(
				'account_id' => $account['id'],
				'network'    => $account['network'],
				'draft'      => $draft,
			);
		}
		if ( array() === $targets ) {
			$problems[] = __( 'Choose at least one account.', 'chatbot' );
		}
		$schedule = isset( $input['schedule'] ) ? (string) $input['schedule'] : '';
		if ( 'queue' === $schedule ) {
			/**
			 * The next free queue slot for these accounts (the Master module's queue), as GMT "Y-m-d H:i:s", or ''.
			 *
			 * @param string $gmt      Slot.
			 * @param int[]  $accounts Account ids.
			 */
			$at = (string) apply_filters( 'qcld_wpbot_social/queue_time', '', array_map( 'intval', wp_list_pluck( $targets, 'account_id' ) ) );
			if ( '' === $at && array() !== $targets ) {
				$problems[] = __( 'There is no free queue slot for these accounts. Add slots in Settings › Publishing, or pick a time.', 'chatbot' );
			}
		} else {
			if ( ! self::schedule_valid( $schedule ) ) {
				// Never turn a time we can't read into "now" (QA SF3): only an empty time means now.
				$problems[] = __( 'Use the date and time format YYYY-MM-DD HH:MM for the schedule.', 'chatbot' );
			}
			$at = self::schedule_gmt( $schedule );
		}
		return array(
			'targets'     => $targets,
			'checks'      => $checks,
			'problems'    => $problems,
			'schedule_at' => $at,
		);
	}

	/**
	 * Save as the current person: the same gate for every caller (REST, bulk
	 * import): a Master add-on may take the save over (approval), and people
	 * without Publish may only save drafts.
	 *
	 * @param array $input Form input.
	 * @return array|WP_Error
	 */
	public static function save_as_user( array $input ) {
		// Brand membership first: nobody files a post into a brand they are not in.
		if ( ! empty( $input['brand_id'] ) && ! Brand_Access::can( (int) $input['brand_id'] ) ) {
			return new WP_Error( 'qcld_wpbot_social_forbidden', __( 'You are not a member of this brand.', 'chatbot' ), array( 'status' => 403 ) );
		}
		/**
		 * Take over a save (the Master module's approval: a post from someone
		 * without Publish waits for an approver). Return an array or WP_Error to answer.
		 *
		 * @param array|WP_Error|null $answer Null to carry on.
		 * @param array               $input  Create post input ("_system" true when no person acts, see save_for()).
		 */
		$answer = apply_filters( 'qcld_wpbot_social/save_intercept', null, $input );
		if ( null !== $answer ) {
			return $answer;
		}
		if ( ( ! isset( $input['status'] ) || 'draft' !== $input['status'] ) && ! current_user_can( 'wpbot_social_publish' ) ) {
			return new WP_Error( 'qcld_wpbot_social_forbidden', __( 'You can save drafts, but you are not allowed to publish.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return self::save( $input );
	}

	/**
	 * Save for someone who is not the person at the screen: auto-share (the
	 * WordPress post's author) and Automator (the workflow's owner). The same
	 * approval rules apply as in Create post (QA #77). With no actor (0), only
	 * the rules that don't depend on a person apply (networks that always need
	 * approval). An actor without Publish gets a draft to review, never an error.
	 *
	 * @param array $input Form input.
	 * @param int   $actor User id (0 = nobody).
	 * @return array|WP_Error
	 */
	public static function save_for( array $input, $actor ) {
		if ( isset( $input['status'] ) && 'draft' === $input['status'] ) {
			return self::save( $input );
		}
		$actor = (int) $actor;
		$was   = get_current_user_id();
		if ( $actor !== $was ) {
			wp_set_current_user( $actor );
		}
		if ( $actor ) {
			$saved = self::save_as_user( $input );
			if ( is_wp_error( $saved ) && 'qcld_wpbot_social_forbidden' === $saved->get_error_code() ) {
				$flags     = isset( $input['flags'] ) && is_array( $input['flags'] ) ? $input['flags'] : array();
				$flags[''] = array( __( 'Its author can\'t publish on Social. Review it, then schedule it.', 'chatbot' ) );
				$saved     = self::save(
					array_merge(
						$input,
						array(
							'status'   => 'draft',
							'flags'    => $flags,
							'brand_id' => Brand_Access::can( isset( $input['brand_id'] ) ? (int) $input['brand_id'] : 0 ) && isset( $input['brand_id'] ) ? $input['brand_id'] : 0,
						)
					)
				);
			}
		} else {
			$input['_system'] = true;
			$answer           = apply_filters( 'qcld_wpbot_social/save_intercept', null, $input );
			$saved            = null !== $answer ? $answer : self::save( $input );
		}
		if ( $actor !== $was ) {
			wp_set_current_user( $was );
		}
		return $saved;
	}

	/**
	 * Save a post from form input (drafts skip the checks).
	 *
	 * @param array $input Form input.
	 * @return array|WP_Error ['id' => post id, 'checks' => …]
	 */
	public static function save( array $input ) {
		$built  = self::build( $input );
		$status = isset( $input['status'] ) && 'draft' === $input['status'] ? 'draft' : 'scheduled';
		if ( 'scheduled' === $status && array() !== $built['problems'] ) {
			return new WP_Error(
				'qcld_wpbot_social_checks',
				implode( ' ', $built['problems'] ),
				array(
					'status' => 400,
					'checks' => $built['checks'],
				)
			);
		}
		if ( 'scheduled' === $status && '' !== $built['schedule_at'] && strtotime( $built['schedule_at'] . ' UTC' ) < time() - 300 ) {
			return new WP_Error( 'qcld_wpbot_social_past', __( 'That time has already passed. Choose a time in the future, or Post now.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$id = Post_Store::create(
			array(
				'title'       => sanitize_text_field( isset( $input['title'] ) ? $input['title'] : '' ),
				'brand_id'    => isset( $input['brand_id'] ) ? Connector::brand( $input['brand_id'] ) : 0,
				'schedule_at' => $built['schedule_at'],
				'status'      => $status,
				'source_type' => sanitize_key( isset( $input['source_type'] ) ? $input['source_type'] : '' ),
				'source_id'   => isset( $input['source_id'] ) ? absint( $input['source_id'] ) : 0,
				'meta'        => array(
					'text'      => sanitize_textarea_field( isset( $input['text'] ) ? $input['text'] : '' ),
					'link'      => esc_url_raw( isset( $input['link'] ) ? (string) $input['link'] : '' ),
					'images'    => array_map( 'absint', isset( $input['images'] ) ? (array) $input['images'] : array() ),
					'video'     => isset( $input['video'] ) ? absint( $input['video'] ) : 0,
					'overrides' => self::clean_overrides( isset( $input['overrides'] ) && is_array( $input['overrides'] ) ? $input['overrides'] : array() ),
					'flags'     => self::clean_flags( isset( $input['flags'] ) && is_array( $input['flags'] ) ? $input['flags'] : array() ),
					'extras'    => isset( $input['extras'] ) && is_array( $input['extras'] ) ? array_map(
						static function ( $extras ) {
							return self::clean_extras( $extras );
						},
						array_filter( $input['extras'], 'is_array' )
					) : array(),
				),
			),
			$built['targets']
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// Saving a draft opened in Create post replaces that draft.
		$old    = isset( $input['draft_id'] ) ? Post_Store::get( absint( $input['draft_id'] ) ) : null;
		$me     = get_current_user_id();
		$source = sanitize_key( isset( $input['source_type'] ) ? $input['source_type'] : '' );
		$may    = $old && Brand_Access::can( (int) $old['brand_id'] ) && ( current_user_can( 'wpbot_social_publish' ) || $me === (int) $old['author_id'] );
		if ( $may && 'draft' === $old['status'] && (int) $id !== (int) $old['id'] && $source === (string) $old['source_type'] ) {
			Post_Store::delete( $old['id'] );
		}
		if ( 'scheduled' === $status && strtotime( $built['schedule_at'] . ' UTC' ) <= time() + 60 ) {
			Scheduler::kick();
		}
		/**
		 * A post was saved (the Master module records queue slots and evergreen posts).
		 *
		 * @param int    $id          Post id.
		 * @param array  $input       Create post input (options holds extra choices such as evergreen).
		 * @param string $status      draft | scheduled.
		 * @param string $schedule_at GMT.
		 * @param int[]  $accounts    Account ids.
		 */
		do_action( 'qcld_wpbot_social/post_saved', (int) $id, $input, $status, $built['schedule_at'], array_map( 'intval', wp_list_pluck( $built['targets'], 'account_id' ) ) );
		return array(
			'id'     => $id,
			'checks' => $built['checks'],
		);
	}

	/**
	 * What Create post needs to reopen a draft.
	 *
	 * @param array $post Post_Store::get() row.
	 * @return array{draft_id:int, title:string, text:string, link:string, images:array, accounts:int[], overrides:array, flags:array, schedule:string}
	 */
	public static function prefill( array $post ) {
		$meta = isset( $post['meta'] ) && is_array( $post['meta'] ) ? $post['meta'] : array();
		return array(
			'draft_id'  => (int) $post['id'],
			'title'     => (string) $post['title'],
			'text'      => isset( $meta['text'] ) ? (string) $meta['text'] : '',
			'link'      => isset( $meta['link'] ) ? (string) $meta['link'] : '',
			'images'    => array_values(
				array_filter(
					array_map(
						static function ( $id ) {
							$thumb = $id ? wp_get_attachment_image_url( (int) $id, 'thumbnail' ) : false;
							return $thumb ? array(
								'id'    => (int) $id,
								'thumb' => $thumb,
							) : null;
						},
						isset( $meta['images'] ) ? (array) $meta['images'] : array()
					)
				)
			),
			'video'     => ! empty( $meta['video'] ) && get_post( (int) $meta['video'] ) ? array(
				'id'   => (int) $meta['video'],
				'name' => basename( (string) get_attached_file( (int) $meta['video'] ) ),
			) : null,
			'accounts'  => array_values( array_unique( array_map( 'intval', wp_list_pluck( (array) $post['targets'], 'account_id' ) ) ) ),
			'overrides' => isset( $meta['overrides'] ) ? (array) $meta['overrides'] : array(),
			'flags'     => isset( $meta['flags'] ) ? (array) $meta['flags'] : array(),
			'extras'    => isset( $meta['extras'] ) ? (array) $meta['extras'] : array(),
			'source'    => array(
				'type' => (string) $post['source_type'],
				'id'   => (int) $post['source_id'],
			),
			'schedule'  => ! empty( $post['schedule_at'] ) && '0000-00-00 00:00:00' !== $post['schedule_at'] ? get_date_from_gmt( $post['schedule_at'], 'Y-m-d\TH:i' ) : '',
		);
	}

	/**
	 * Per-network text overrides: network => text.
	 *
	 * @param array $overrides Input (network => ['text' => …] or text).
	 * @return array<string,string>
	 */
	private static function clean_overrides( array $overrides ) {
		$out = array();
		foreach ( $overrides as $network => $value ) {
			$text = is_array( $value ) ? ( isset( $value['text'] ) ? $value['text'] : '' ) : $value;
			$text = sanitize_textarea_field( (string) $text );
			if ( '' !== trim( $text ) ) {
				$out[ sanitize_key( (string) $network ) ] = $text;
			}
		}
		return $out;
	}

	/**
	 * Honesty-check flags carried from Write with AI: network => string[].
	 *
	 * @param array $flags Input.
	 * @return array<string,string[]>
	 */
	private static function clean_flags( array $flags ) {
		$out = array();
		foreach ( $flags as $network => $list ) {
			$list = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) $list, 0, 10 ) ) ) );
			if ( array() !== $list ) {
				$out[ sanitize_key( (string) $network ) ] = $list;
			}
		}
		return $out;
	}

	/**
	 * Site-time "Y-m-d H:i" to GMT, or now when empty.
	 *
	 * @param string $local Site time.
	 * @return string GMT Y-m-d H:i:s.
	 */
	public static function schedule_gmt( $local ) {
		$local = trim( (string) $local );
		if ( '' === $local || ! self::schedule_valid( $local ) ) {
			return current_time( 'mysql', true );
		}
		return get_gmt_from_date( substr( str_replace( 'T', ' ', $local ), 0, 16 ) . ':00' );
	}

	/**
	 * Whether a schedule is empty ("now") or a site-time "Y-m-d H:i" (seconds allowed).
	 *
	 * @param string $local Schedule.
	 * @return bool
	 */
	public static function schedule_valid( $local ) {
		$local = trim( (string) $local );
		return '' === $local || 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])[ T]([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $local );
	}

	/**
	 * A Media Library video for the drafts, or null.
	 *
	 * @param int $attachment_id Attachment.
	 * @return array{id:int, url:string, mime:string, bytes:int, seconds:int, width:int, height:int}|null
	 */
	public static function video( $attachment_id ) {
		if ( ! $attachment_id || ! current_user_can( 'upload_files' ) ) {
			return null;
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, array( 'video/mp4', 'video/quicktime', 'video/webm' ), true ) ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		$file = get_attached_file( $attachment_id );
		return array(
			'id'      => (int) $attachment_id,
			'url'     => (string) wp_get_attachment_url( $attachment_id ),
			'mime'    => $mime,
			'bytes'   => $file && file_exists( $file ) ? (int) filesize( $file ) : ( isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0 ),
			'seconds' => isset( $meta['length'] ) ? (int) $meta['length'] : 0,
			'width'   => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'  => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
		);
	}

	/**
	 * Allowed network extras.
	 *
	 * @param array $extras Extras.
	 * @return array
	 */
	private static function clean_extras( array $extras ) {
		$out = array();
		foreach ( array( 'gbp_type', 'gbp_title', 'gbp_start', 'gbp_end', 'gbp_coupon', 'gbp_terms', 'gbp_button' ) as $key ) {
			if ( isset( $extras[ $key ] ) ) {
				$out[ $key ] = substr( sanitize_text_field( (string) $extras[ $key ] ), 0, 300 );
			}
		}
		// Formats (Master): thread follow-ups and a first comment.
		if ( isset( $extras['posts'] ) ) {
			$posts        = is_array( $extras['posts'] ) ? $extras['posts'] : preg_split( '/\n\s*\n/', (string) $extras['posts'] );
			$out['posts'] = array_slice( array_values( array_filter( array_map( 'trim', array_map( 'sanitize_textarea_field', (array) $posts ) ) ) ), 0, 10 );
			$out['posts'] = array_map(
				static function ( $post ) {
					return mb_substr( $post, 0, 3000 );
				},
				$out['posts']
			);
		}
		if ( isset( $extras['first_comment'] ) ) {
			$out['first_comment'] = mb_substr( trim( sanitize_textarea_field( (string) $extras['first_comment'] ) ), 0, 2200 );
		}
		if ( isset( $extras['ig_format'] ) && in_array( $extras['ig_format'], array( 'feed', 'reel', 'story' ), true ) ) {
			$out['ig_format'] = $extras['ig_format'];
		}
		if ( isset( $extras['yt_privacy'] ) && in_array( $extras['yt_privacy'], array( 'private', 'unlisted', 'public' ), true ) ) {
			$out['yt_privacy'] = $extras['yt_privacy'];
		}
		foreach ( array( 'title', 'alt' ) as $key ) {
			if ( isset( $extras[ $key ] ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( (string) $extras[ $key ] ), 0, 'title' === $key ? 200 : 500 );
			}
		}
		return array_filter(
			$out,
			static function ( $value ) {
				return '' !== $value && array() !== $value;
			}
		);
	}
}
