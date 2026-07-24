<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPBotGCDownload
{
    private $download_url = 'https://github.com/qcloud/gc/raw/master/wpbotgc.zip';
    private $filename = 'wpbotgc.zip';

    public function __construct() {
        add_action( 'wp_ajax_qcld_wp_chatbot_gc_client_download', array( $this, 'downloadgc' ) );
        add_action( 'wp_ajax_qcld_wp_chatbot_gc_client_extract',  array( $this, 'extractgc' ) );
    }

    /**
     * Initialise WP_Filesystem and return the global instance.
     */
    private function get_filesystem() {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem;
    }

    /**
     * Create a directory using WP_Filesystem.
     */
    public function create_folder( $gcdirectory ) {
        $fs = $this->get_filesystem();
        if ( ! $fs->is_dir( $gcdirectory ) ) {
            return $fs->mkdir( $gcdirectory, FS_CHMOD_DIR );
        }
        return true;
    }

    /**
     * Create a blank index.php guard file using WP_Filesystem.
     */
    public function create_file( $filename ) {
        $fs = $this->get_filesystem();
        if ( $fs->exists( $filename ) ) {
            return true;
        }
        return $fs->put_contents( $filename, '<?php //silence is golden', FS_CHMOD_FILE );
    }

    public function downloadgc() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Unauthorized access', 'chatbot' ) ) );
            wp_die();
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
            wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Failed in Security check', 'chatbot' ) ) );
            wp_die();
        }

        $fs           = $this->get_filesystem();
        $gcdirectory  = QCLD_wpCHATBOT_GC_DIRNAME;

        if ( ! $fs->is_dir( $gcdirectory ) ) {
            $this->create_folder( $gcdirectory );
        }

        if ( ! $fs->exists( $gcdirectory . '/index.php' ) ) {
            $this->create_file( $gcdirectory . '/index.php' );
        }

        if ( ! $fs->is_dir( $gcdirectory ) ) {
            wp_send_json( array( 'status' => 'error', 'content' => esc_html__( 'Server does not allow creating files and folders.', 'chatbot' ) ) );
            wp_die();
        }

        $zip_file = $gcdirectory . '/' . $this->filename;

        $remote_response = wp_remote_get( $this->download_url, array(
            'timeout'  => 60,
            'stream'   => true,
            'filename' => $zip_file,
        ) );

        if ( is_wp_error( $remote_response ) ) {
            wp_send_json( array( 'status' => 'error', 'content' => esc_html( $remote_response->get_error_message() ) ) );
            wp_die();
        }

        $http_code = wp_remote_retrieve_response_code( $remote_response );
        if ( 200 !== $http_code ) {
            wp_send_json( array( 'status' => 'error', 'content' => esc_html__( 'Remote server returned an unexpected response.', 'chatbot' ) ) );
            wp_die();
        }

        wp_send_json( array( 'status' => 'success', 'content' => esc_html__( 'File downloaded successfully.', 'chatbot' ) ) );
        wp_die();
    }

    public function extractgc() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Unauthorized access', 'chatbot' ) ) );
            wp_die();
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
            wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Failed in Security check', 'chatbot' ) ) );
            wp_die();
        }

        $fs          = $this->get_filesystem();
        $gcdirectory = QCLD_wpCHATBOT_GC_DIRNAME;
        $gcfilename  = $gcdirectory . '/' . $this->filename;

        // Verify the zip exists before attempting to open it.
        if ( ! $fs->exists( $gcfilename ) ) {
            wp_send_json( array( 'status' => 'error', 'content' => esc_html__( 'File not found.', 'chatbot' ) ) );
            wp_die();
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $gcfilename ) ) {
            wp_send_json( array( 'status' => 'error', 'content' => esc_html__( 'Could not open zip archive.', 'chatbot' ) ) );
            wp_die();
        }

        // Validate every entry: block path traversal and absolute paths.
        $real_dest = realpath( $gcdirectory );
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $stat     = $zip->statIndex( $i );
            $entry    = $stat['name'];

            if (
                strpos( $entry, '../' ) !== false ||
                strpos( $entry, '..' . DIRECTORY_SEPARATOR ) !== false ||
                '/' === substr( $entry, 0, 1 )
            ) {
                $zip->close();
                $fs->delete( $gcfilename );
                wp_send_json( array( 'status' => 'error', 'content' => esc_html__( 'Invalid zip structure — path traversal detected.', 'chatbot' ) ) );
                wp_die();
            }

            // Ensure resolved path stays within the destination directory.
            $resolved = realpath( $real_dest . DIRECTORY_SEPARATOR . $entry );
            if ( $resolved !== false && strpos( $resolved, $real_dest ) !== 0 ) {
                $zip->close();
                $fs->delete( $gcfilename );
                wp_send_json( array( 'status' => 'error', 'content' => esc_html__( 'Invalid zip structure — entry escapes destination.', 'chatbot' ) ) );
                wp_die();
            }
        }

        $zip->extractTo( $gcdirectory );
        $zip->close();

        // Remove the zip after successful extraction.
        $fs->delete( $gcfilename );

        wp_send_json( array( 'status' => 'success', 'content' => esc_html__( 'Files extracted successfully.', 'chatbot' ) ) );
        wp_die();
    }
}

new WPBotGCDownload();
