<?php
/**
 * Read-only view of the leads the host's chatbot saved before AI Marketing
 * existed (WPBot's "User Data", table wpbot_subscription).
 *
 * The module never queries the host's tables directly; it pages through this
 * interface. The only write is mark_unsubscribed(), so the chatbot does not
 * tell an unsubscribed person "you are already subscribed".
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Chatbot lead source.
 */
interface Lead_Source {

	/**
	 * Number of saved leads.
	 *
	 * @return int
	 */
	public function count();

	/**
	 * One page of leads, oldest first.
	 *
	 * @param int $after_id Last id of the previous page (0 for the first page).
	 * @param int $limit    Page size.
	 * @return array[] Each: id (int), name, email, phone, url, date (MySQL datetime, site time),
	 *                 unsubscribed (bool: the person unsubscribed since, so they are not imported as subscribed).
	 */
	public function page( $after_id, $limit );

	/**
	 * Tell the chatbot that this address unsubscribed.
	 *
	 * @param string $email Email address.
	 * @return void
	 */
	public function mark_unsubscribed( $email );
}
