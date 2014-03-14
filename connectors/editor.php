<?php

class WP_Stream_Connector_Editor extends WP_Stream_Connector {

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'editor';

	/**
	 * Actions registered for this context
	 *
	 * @var array
	 */
	public static $actions = array(
		'admin_init',
		'admin_footer',
	);

	/**
	 * Actions registered for this context
	 *
	 * @var array
	 */
	private static $edited_file = array();

	/**
	 * Register all context hooks
	 *
	 * @return void
	 */
	public static function register() {
		parent::register();
		add_filter( 'wp_redirect', array( __CLASS__, 'log_changes_on_redirect' ) );
	}

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Editor', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated' => __( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'file' => __( 'File', 'stream' ),
		);
	}

	/**
	 * Check if edition of file is requested
	 *
	 * @return bool Whether valid edition request is sent
	 */
	public static function is_edition_requested() {
		if ( 'theme-editor' !== get_current_screen()->id ) {
			return false;
		}

		if( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		}

		if( ! isset( $_POST['action'] ) || 'update' !== $_POST['action'] ) {
			return false;
		}

		$theme_name = ( isset( $_POST['theme'] ) && $_POST['theme'] ? $_POST['theme'] : get_stylesheet() );

		$theme = wp_get_theme( $theme_name );

		if ( ! $theme->exists() || ( $theme->errors() && 'theme_no_stylesheet' === $theme->errors()->get_error_code() ) ) {
			return false;
		}

		return true;
	}

	public static function callback_admin_init() {
		if ( ! is_admin() || 'theme-editor' !== get_current_screen()->id ) {
			return;
		}

		if( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if( ! isset( $_POST['action'] ) || 'update' !== $_POST['action'] ) {
			return;
		}

		$theme_name = ( isset( $_POST['theme'] ) && $_POST['theme'] ? $_POST['theme'] : get_stylesheet() );

		$theme = wp_get_theme( $theme_name );

		if ( ! $theme->exists() || ( $theme->errors() && 'theme_no_stylesheet' === $theme->errors()->get_error_code() ) ) {
			return;
		}

		$allowed_files = $theme->get_files( 'php', 1 );
		$style_files = $theme->get_files( 'css' );
		$allowed_files['style.css'] = $style_files['style.css'];

		if ( empty( $_POST['file'] ) ) {
			$file_name = 'style.css';
			$file_path = $allowed_files['style.css'];
		} else {
			$file_name = $_POST['file'];
			$file_path = $theme->get_stylesheet_directory() . '/' . $relative_file;
		}

		$file_contents_before = file_get_contents( $file_path );

		self::$edited_file = compact(
			$file_name,
			$file_path,
			$file_contents_before,
			$theme
		);
	}

	public static function callback_admin_footer() {
		if( is_admin() && 'theme-editor' === get_current_screen()->id ) : ?>
			<script>
				var $content = jQuery('#newcontent');

				jQuery('<textarea></textarea>')
					.val($content.val())
					.attr('name', 'oldcontent')
					.hide()
					.insertBefore($content);
			</script>
		<?php endif;
	}

	public static function log_changes_on_redirect( $location ) {
		if ( is_admin() && 'theme-editor' === get_current_screen()->id && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['action'] ) && 'update' === $_POST['action'] ) {

			if ( isset( $_POST['theme'] ) && $_POST['theme'] ) {
				$stylesheet = $_POST['theme'];
			} else {
				$stylesheet = get_stylesheet();
			}

			$theme = wp_get_theme( $stylesheet );

			if ( $theme->exists() && ! ($theme->errors() && 'theme_no_stylesheet' == $theme->errors()->get_error_code() ) ) {
				$allowed_files = $theme->get_files( 'php', 1 );
				$has_templates = ! empty( $allowed_files );
				$style_files = $theme->get_files( 'css' );
				$allowed_files['style.css'] = $style_files['style.css'];
				$allowed_files += $style_files;

				if ( empty( $_POST['file'] ) ) {
					$relative_file = 'style.css';
					$file = $allowed_files['style.css'];
				} else {
					$relative_file = $_POST['file'];
					$file = $theme->get_stylesheet_directory() . '/' . $relative_file;
				}

				$file_contents = file_get_contents( $file );

				if ( $file_contents !== $_POST['oldcontent'] ) {
					$properties = array(
						'file_html'  => sprintf(
							'<a href="%s">%s</a>',
							esc_attr( admin_url( sprintf(
								'theme-editor.php?theme=%s&file=%s',
								$theme->get_template(),
								$relative_file
							) ) ),
							$relative_file
						),
						'theme_html' => sprintf(
							'<a href="%s">%s</a>',
							esc_attr( admin_url( sprintf( 'themes.php?theme=%s', $theme->get_template() ) ) ),
							$theme
						),
						'editor_opening_html' => sprintf( '<a href="%s">', esc_attr( admin_url( 'theme-editor.php' ) ) ),
						'editor_closing_html' => '</a>',
						'file'       => $relative_file,
						'theme'      => $theme,
						'closing'    => '</a>',
						'new_value'  => $file_contents,
						'old_value'  => $_POST['oldcontent'],
					);

					self::log(
						__( '%1$s file of %2$s theme was updated via %3$sEditor%4$s', 'stream' ),
						$properties,
						null,
						array( 'file' => 'updated' )
					);
				}
			}
		}

		return $location;
	}

}
