<?php
/**
 * Content calendar: reads WordPress post dates and statuses (no copy of the
 * schedule), reschedules drafts and scheduled posts, turns ideas into
 * dated drafts, and warns about crowded days and unfinished scheduled posts.
 *
 * Every date is in the site's time zone, as WordPress shows it. A draft
 * without a set date ("floating") is shown on its current date and marked
 * as such; moving it gives it a real date.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Calendar;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Topics\Ideas;
use QuantumCloud\AiWriter\Writing\Prompts;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Calendar service.
 */
final class Calendar {

	/**
	 * Statuses shown on the calendar.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'draft', 'pending', 'future', 'publish', 'private' );

	/**
	 * Statuses that can be moved.
	 *
	 * @var string[]
	 */
	const MOVABLE = array( 'draft', 'pending', 'future' );

	/**
	 * Post types the calendar can show.
	 *
	 * @return array<string,string> name => label.
	 */
	public static function post_types() {
		$out = array();
		foreach ( get_post_types(
			array(
				'public'       => true,
				'show_in_rest' => true,
			),
			'objects'
		) as $type ) {
			if ( 'attachment' !== $type->name && current_user_can( $type->cap->edit_posts ) ) {
				$out[ $type->name ] = $type->labels->name;
			}
		}
		return $out;
	}

	/**
	 * Calendar items between two local dates (inclusive).
	 *
	 * @param string $start   Y-m-d (site time zone).
	 * @param string $end     Y-m-d.
	 * @param array  $filters post_types (string[]), statuses (string[]), mine (bool), author (int).
	 * @return array{items: array[], warnings: array[]}
	 */
	public static function items( $start, $end, array $filters = array() ) {
		$types    = array_keys( self::post_types() );
		$types    = ! empty( $filters['post_types'] ) ? array_values( array_intersect( $types, (array) $filters['post_types'] ) ) : $types;
		$statuses = ! empty( $filters['statuses'] ) ? array_values( array_intersect( self::STATUSES, (array) $filters['statuses'] ) ) : self::STATUSES;
		$items    = array();
		if ( array() !== $types && array() !== $statuses ) {
			$query = array(
				'post_type'        => $types,
				'post_status'      => $statuses,
				'posts_per_page'   => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- At most two months of posts.
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'ASC',
				'date_query'       => array(
					array(
						'after'     => $start . ' 00:00:00',
						'before'    => $end . ' 23:59:59',
						'inclusive' => true,
						'column'    => 'post_date',
					),
				),
			);
			if ( ! empty( $filters['mine'] ) ) {
				$query['author'] = get_current_user_id();
			} elseif ( ! empty( $filters['author'] ) ) {
				$query['author'] = absint( $filters['author'] );
			}
			foreach ( get_posts( $query ) as $post ) {
				if ( current_user_can( 'edit_post', $post->ID ) || ( 'publish' === $post->post_status && current_user_can( 'read_post', $post->ID ) ) ) {
					$items[] = self::item( $post );
				}
			}
		}

		/**
		 * Add calendar items from other modules (Social, AI Marketing). Each
		 * item: id (string), type, source_module, title, date (Y-m-d H:i:s,
		 * site time zone), status, edit_url, movable (bool).
		 *
		 * @param array[] $items Items.
		 * @param string  $start Y-m-d.
		 * @param string  $end   Y-m-d.
		 */
		$items = (array) apply_filters( 'qcld_wpbot_writer/calendar_items', $items, $start, $end );

		return array(
			'items'    => $items,
			'warnings' => self::warnings( $items ),
		);
	}

	/**
	 * One post as a calendar item.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function item( $post ) {
		$type     = get_post_type_object( $post->post_type );
		$floating = in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) && '0000-00-00 00:00:00' === $post->post_date_gmt;
		$movable  = in_array( $post->post_status, self::MOVABLE, true ) && current_user_can( 'edit_post', $post->ID );
		if ( 'future' === $post->post_status ) {
			$movable = $movable && $type && current_user_can( $type->cap->publish_posts ) && current_user_can( Module::cap( 'publish' ) );
		}
		return array(
			'id'            => 'post-' . $post->ID,
			'post_id'       => (int) $post->ID,
			'type'          => 'post',
			'source_module' => 'wordpress',
			'post_type'     => $post->post_type,
			'type_label'    => $type ? $type->labels->singular_name : $post->post_type,
			'title'         => '' !== $post->post_title ? html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) : __( '(no title)', 'chatbot' ),
			'date'          => $post->post_date,
			'status'        => $post->post_status,
			'floating'      => $floating,
			'edit_url'      => (string) get_edit_post_link( $post->ID, 'raw' ),
			'movable'       => $movable,
			'unfinished'    => 'future' === $post->post_status ? self::unfinished( $post ) : array(),
			'author'        => get_the_author_meta( 'display_name', (int) $post->post_author ),
			// CR-16: the article format the piece is (or will be) written in.
			'format'        => (string) get_post_meta( $post->ID, \QuantumCloud\AiWriter\Formats\Formats::META, true ),
		);
	}

	/**
	 * Why a scheduled post looks unfinished.
	 *
	 * @param \WP_Post $post Post.
	 * @return string[]
	 */
	public static function unfinished( $post ) {
		$reasons = array();
		$text    = wp_strip_all_tags( excerpt_remove_blocks( $post->post_content ) );
		if ( '' === trim( $post->post_title ) ) {
			$reasons[] = __( 'No title', 'chatbot' );
		}
		if ( Prompts::word_count( $text ) < 100 ) {
			$reasons[] = __( 'Very little text', 'chatbot' );
		}
		if ( preg_match( '/\[[^\]\[]{3,80}\]/', $text ) && ! preg_match( '/^\s*\[[a-z_-]+[^\]]*\]\s*$/m', $text ) ) {
			$reasons[] = __( '[Placeholders] still to fill in', 'chatbot' );
		}
		if ( post_type_supports( $post->post_type, 'thumbnail' ) && ! has_post_thumbnail( $post ) ) {
			$reasons[] = __( 'No featured image', 'chatbot' );
		}
		return $reasons;
	}

	/**
	 * Warnings: two posts going out on one day; unfinished scheduled posts.
	 *
	 * @param array[] $items Items.
	 * @return array[] date, kind, message, ids.
	 */
	public static function warnings( array $items ) {
		$by_day   = array();
		$warnings = array();
		foreach ( $items as $item ) {
			if ( in_array( $item['status'], array( 'future', 'publish' ), true ) && 'post' === $item['type'] ) {
				$by_day[ substr( $item['date'], 0, 10 ) ][] = $item['id'];
			}
			if ( 'future' === $item['status'] && ! empty( $item['unfinished'] ) ) {
				$warnings[] = array(
					'date'    => substr( $item['date'], 0, 10 ),
					'kind'    => 'unfinished',
					/* translators: 1: title, 2: reasons. */
					'message' => sprintf( __( '"%1$s" is scheduled but looks unfinished: %2$s.', 'chatbot' ), $item['title'], implode( ', ', $item['unfinished'] ) ),
					'ids'     => array( $item['id'] ),
				);
			}
		}
		foreach ( $by_day as $day => $ids ) {
			if ( count( $ids ) >= 2 ) {
				$warnings[] = array(
					'date'    => $day,
					'kind'    => 'crowded',
					/* translators: %d: number of posts. */
					'message' => sprintf( _n( '%d post goes out on this day.', '%d posts go out on this day. Spread them out so each gets attention.', count( $ids ), 'chatbot' ), count( $ids ) ),
					'ids'     => $ids,
				);
			}
		}
		return $warnings;
	}

	/**
	 * Move a post to another day, keeping its time of day (9:00 for a
	 * floating draft). Scheduled posts must stay in the future.
	 *
	 * @param int    $post_id Post.
	 * @param string $date    Y-m-d (site time zone).
	 * @param string $time    Optional H:i.
	 * @return array|WP_Error Updated item.
	 */
	public static function move( $post_id, $date, $time = '' ) {
		$post = get_post( $post_id );
		if ( ! $post || ! self::valid_date( $date ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_move', __( 'Choose a valid day.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$item = self::item( $post );
		if ( ! $item['movable'] ) {
			$message = 'future' === $post->post_status
				? __( 'Only people who may publish can move scheduled posts.', 'chatbot' )
				: __( 'Published posts are not moved from the calendar. Change the date in the editor if you need to.', 'chatbot' );
			return new WP_Error( 'qcld_wpbot_writer_cannot_move', $message, array( 'status' => 403 ) );
		}
		if ( '' === $time || ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$time = $item['floating'] ? '09:00' : substr( $post->post_date, 11, 5 );
		}
		$local = $date . ' ' . $time . ':00';
		$gmt   = get_gmt_from_date( $local );
		if ( 'future' === $post->post_status && strtotime( $gmt . ' UTC' ) <= time() ) {
			return new WP_Error( 'qcld_wpbot_writer_past', __( 'A scheduled post must stay in the future. To publish it now, open it and click Publish.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = wp_update_post(
			array(
				'ID'            => $post->ID,
				'post_date'     => $local,
				'post_date_gmt' => $gmt,
				'edit_date'     => true,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::item( get_post( $post->ID ) );
	}

	/**
	 * Turn an idea into a draft dated on a day.
	 *
	 * @param int    $idea_id   Idea.
	 * @param string $date      Y-m-d.
	 * @param string $post_type Post type.
	 * @return array|WP_Error New item.
	 */
	public static function place_idea( $idea_id, $date, $post_type = 'post' ) {
		$idea = Ideas::get( $idea_id );
		if ( ! $idea || ! self::valid_date( $date ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_idea', __( 'This idea no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$type = get_post_type_object( $post_type );
		if ( ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_create', __( 'You are not allowed to create this kind of content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$local   = $date . ' 09:00:00';
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'     => $post_type,
					'post_status'   => 'draft',
					'post_title'    => $idea['title'],
					'post_author'   => get_current_user_id(),
					'post_date'     => $local,
					'post_date_gmt' => get_gmt_from_date( $local ),
					'edit_date'     => true,
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_qcld_wpbot_writer_idea_id', (int) $idea_id );
		// CR-16: the planned draft keeps the idea's suggested format (changeable on the calendar).
		$format = ! empty( $idea['format']['id'] ) ? \QuantumCloud\AiWriter\Formats\Formats::automatic( $idea['format']['id'] ) : '';
		if ( '' !== $format ) {
			update_post_meta( $post_id, \QuantumCloud\AiWriter\Formats\Formats::META, $format );
		}
		Ideas::set_status( $idea_id, 'planned', $post_id );
		\QuantumCloud\AiWriter\Topics\Map_Sync::idea_placed( $idea, (int) $post_id );
		return self::item( get_post( $post_id ) );
	}

	/**
	 * Whether a string is a real Y-m-d date.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function valid_date( $date ) {
		return is_string( $date ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) && checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * CR-16: choose the format of a planned piece ('' for a usual article).
	 *
	 * @param int    $post_id Post.
	 * @param string $format  Format id.
	 * @return array|WP_Error Item.
	 */
	public static function set_format( $post_id, $format ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You cannot change this piece.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$format = \QuantumCloud\AiWriter\Formats\Formats::sanitise( $format );
		if ( '' === $format ) {
			delete_post_meta( $post->ID, \QuantumCloud\AiWriter\Formats\Formats::META );
		} else {
			update_post_meta( $post->ID, \QuantumCloud\AiWriter\Formats\Formats::META, $format );
		}
		return self::item( get_post( $post->ID ) );
	}
}
