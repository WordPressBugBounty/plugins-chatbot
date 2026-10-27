<?php
/**
 * Stores recording chunks as gzip files in a protected uploads folder.
 *
 * Layout: uploads/qc-insights-{random}/{ab}/{session_uuid}/{pageview_uuid}-{seq}.json.gz
 * Files are never served directly; the admin reads them through a
 * capability-checked REST route.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Hot path on the ingest request; WP_Filesystem may need credentials on some hosts.

final class Local_File_Store implements Recording_Store {

	const SUFFIX_OPTION = 'qcin_storage_suffix';
	const TEST_FILE     = 'qcin-protection-test.txt';

	/**
	 * Base folder (no trailing slash).
	 *
	 * @var string|null
	 */
	private $base = null;

	/**
	 * Base folder, created and protected on first use.
	 *
	 * @return string
	 */
	public function base_dir() {
		if ( null !== $this->base ) {
			return $this->base;
		}
		$suffix = get_option( self::SUFFIX_OPTION );
		if ( ! is_string( $suffix ) || ! preg_match( '/^[a-z0-9]{12}$/', $suffix ) ) {
			$suffix = strtolower( wp_generate_password( 12, false, false ) );
			update_option( self::SUFFIX_OPTION, $suffix, false );
		}
		$uploads    = wp_upload_dir( null, false );
		$this->base = untrailingslashit( $uploads['basedir'] ) . '/qc-insights-' . $suffix;
		if ( ! is_dir( $this->base ) ) {
			wp_mkdir_p( $this->base );
		}
		$this->protect( $this->base );
		return $this->base;
	}

	/**
	 * Public URL of the base folder (used only to test that it is blocked).
	 *
	 * @return string
	 */
	public function base_url() {
		$uploads = wp_upload_dir( null, false );
		return untrailingslashit( $uploads['baseurl'] ) . '/' . basename( $this->base_dir() );
	}

	/**
	 * Write deny rules and an empty index into a folder.
	 *
	 * @param string $dir Folder.
	 */
	private function protect( $dir ) {
		$files = array(
			'.htaccess'  => "# WPBot Insights: recordings are private.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);
		foreach ( $files as $name => $content ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Folder for a session.
	 *
	 * @param string $session_uuid Session id.
	 * @return string
	 */
	private function session_dir( $session_uuid ) {
		return $this->base_dir() . '/' . substr( $session_uuid, 0, 2 ) . '/' . $session_uuid;
	}

	/**
	 * {@inheritDoc}
	 */
	public function put( $session_uuid, $pageview_uuid, $seq, $bytes ) {
		$dir = $this->session_dir( $session_uuid );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$file = sprintf( '%s/%s-%05d.json.gz', $dir, $pageview_uuid, (int) $seq );
		return file_put_contents( $file, $bytes, LOCK_EX );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pageview( $session_uuid, $pageview_uuid ) {
		$dir   = $this->session_dir( $session_uuid );
		$files = glob( $dir . '/' . $pageview_uuid . '-*.json.gz' );
		if ( empty( $files ) ) {
			return array();
		}
		sort( $files, SORT_STRING );
		$out = array();
		foreach ( $files as $file ) {
			$data = file_get_contents( $file );
			if ( false !== $data ) {
				$out[] = $data;
			}
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_session( $session_uuid ) {
		if ( ! preg_match( '/^[0-9a-f-]{36}$/', $session_uuid ) ) {
			return;
		}
		$this->rrmdir( $this->session_dir( $session_uuid ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_all() {
		$base = $this->base_dir();
		foreach ( (array) glob( $base . '/*', GLOB_ONLYDIR ) as $dir ) {
			$this->rrmdir( $dir );
		}
	}

	/**
	 * Remove a folder tree inside the base folder.
	 *
	 * @param string $dir Folder.
	 */
	private function rrmdir( $dir ) {
		$base = $this->base_dir();
		if ( 0 !== strpos( $dir, $base . '/' ) || ! is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$parent = dirname( $dir );
		if ( $parent !== $base && is_dir( $parent ) && 2 === count( (array) scandir( $parent ) ) ) {
			@rmdir( $parent ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function health() {
		$base     = $this->base_dir();
		$writable = is_dir( $base ) && wp_is_writable( $base );
		$test     = $base . '/' . self::TEST_FILE;
		if ( $writable && ! file_exists( $test ) ) {
			file_put_contents( $test, 'qcin' );
		}

		$protected = null;
		$response  = wp_remote_get(
			$this->base_url() . '/' . self::TEST_FILE,
			array(
				'timeout'   => 5,
				'sslverify' => false,
			)
		);
		if ( ! is_wp_error( $response ) ) {
			$code      = (int) wp_remote_retrieve_response_code( $response );
			$body      = (string) wp_remote_retrieve_body( $response );
			$protected = ! ( 200 === $code && 'qcin' === trim( $body ) );
		}

		return array(
			'store'     => 'local',
			'path'      => str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $base ) ),
			'writable'  => $writable,
			'protected' => $protected,
		);
	}
}
