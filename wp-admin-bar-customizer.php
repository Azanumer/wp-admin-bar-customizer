<?php
/**
 * Plugin Name: WP Admin Bar Customizer
 * Plugin URI: https://github.com/Azanumer/wp-admin-bar-customizer
 * Description: Brand the WordPress admin bar — add your own links, remove clutter nodes, set a custom colour, and hide the bar for selected roles. No code needed.
 * Version: 1.0.0
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * Text Domain: wp-admin-bar-customizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WABC_VERSION', '1.0.0' );
define( 'WABC_OPTION', 'wabc_settings' );

/** Default nodes that can be hidden. Key = admin bar node ID. */
function wabc_removable_nodes() {
	return array(
		'wp-logo'      => __( 'WordPress logo menu', 'wp-admin-bar-customizer' ),
		'site-name'    => __( 'Site name', 'wp-admin-bar-customizer' ),
		'comments'     => __( 'Comments bubble', 'wp-admin-bar-customizer' ),
		'new-content'  => __( '+ New menu', 'wp-admin-bar-customizer' ),
		'updates'      => __( 'Updates bubble', 'wp-admin-bar-customizer' ),
		'search'       => __( 'Search form', 'wp-admin-bar-customizer' ),
		'my-account'   => __( 'My Account menu', 'wp-admin-bar-customizer' ),
		'customize'    => __( 'Customize link', 'wp-admin-bar-customizer' ),
		'edit'         => __( 'Edit link', 'wp-admin-bar-customizer' ),
		'view'         => __( 'View link', 'wp-admin-bar-customizer' ),
	);
}

function wabc_get_settings() {
	$defaults = array(
		'color'        => '#23282d',
		'remove'       => array(),
		'hide_roles'   => array(),
		'custom_links' => array(),
	);
	$saved = get_option( WABC_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		return $defaults;
	}
	return array_merge( $defaults, $saved );
}

/**
 * Hide the admin bar for selected roles.
 * Runs before the bar is rendered; admins are never hidden (safety).
 */
add_filter( 'show_admin_bar', 'wabc_maybe_hide_bar', 20 );
function wabc_maybe_hide_bar( $show ) {
	if ( ! $show || is_admin() || current_user_can( 'manage_options' ) ) {
		return $show;
	}
	$settings = wabc_get_settings();
	if ( empty( $settings['hide_roles'] ) ) {
		return $show;
	}
	$user = wp_get_current_user();
	if ( ! $user || 0 === $user->ID ) {
		return $show;
	}
	foreach ( (array) $user->roles as $role ) {
		if ( in_array( $role, (array) $settings['hide_roles'], true ) ) {
			return false;
		}
	}
	return $show;
}

/**
 * Remove nodes + add custom links. Runs late so core and plugin nodes exist.
 */
add_action( 'admin_bar_menu', 'wabc_customize_bar', 999 );
function wabc_customize_bar( $wp_admin_bar ) {
	$settings = wabc_get_settings();

	foreach ( (array) $settings['remove'] as $node_id ) {
		$wp_admin_bar->remove_node( sanitize_key( $node_id ) );
	}

	foreach ( (array) $settings['custom_links'] as $link ) {
		if ( empty( $link['title'] ) || empty( $link['url'] ) ) {
			continue;
		}
		$args = array(
			'id'     => 'wabc-' . sanitize_key( $link['title'] ),
			'title'  => esc_html( $link['title'] ),
			'href'   => esc_url( $link['url'] ),
			'parent' => ! empty( $link['parent'] ) ? sanitize_key( $link['parent'] ) : false,
			'meta'   => array(
				'target' => ! empty( $link['new_tab'] ) ? '_blank' : '',
				'title'  => esc_attr( $link['title'] ),
			),
		);
		$wp_admin_bar->add_node( $args );
	}
}

/** Custom admin-bar colour (front end + wp-admin). */
add_action( 'wp_head', 'wabc_print_bar_css' );
add_action( 'admin_head', 'wabc_print_bar_css' );
function wabc_print_bar_css() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	$settings = wabc_get_settings();
	$color    = sanitize_hex_color( $settings['color'] );
	if ( ! $color || '#23282d' === strtolower( $color ) ) {
		return; // Default colour — nothing to override.
	}
	// Darken the colour slightly for the hover state.
	$hover = wabc_adjust_brightness( $color, -18 );
	printf(
		"<style>#wpadminbar{background:%1\$s !important}#wpadminbar .ab-top-menu>li:hover>.ab-item,#wpadminbar .ab-top-menu>li.hover>.ab-item{background:%2\$s !important}</style>\n",
		esc_attr( $color ),
		esc_attr( $hover )
	);
}

/** Shift a #rrggbb colour's brightness by $steps (-255..255). */
function wabc_adjust_brightness( $hex, $steps ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$out = '#';
	for ( $i = 0; $i < 3; $i++ ) {
		$channel = (int) hexdec( substr( $hex, $i * 2, 2 ) ) + $steps;
		$out    .= str_pad( dechex( max( 0, min( 255, $channel ) ) ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

add_action( 'admin_menu', 'wabc_register_menu' );
function wabc_register_menu() {
	add_options_page(
		__( 'Admin Bar Customizer', 'wp-admin-bar-customizer' ),
		__( 'Admin Bar', 'wp-admin-bar-customizer' ),
		'manage_options',
		'wabc-admin-bar-customizer',
		'wabc_render_page'
	);
}

function wabc_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-admin-bar-customizer' ) );
	}

	$settings = wabc_get_settings();
	$notice   = '';

	if ( isset( $_POST['wabc_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wabc_nonce'] ), 'wabc_save' ) ) {
		$color = isset( $_POST['wabc_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['wabc_color'] ) ) : '#23282d';
		$settings['color'] = $color ? $color : '#23282d';

		$remove = isset( $_POST['wabc_remove'] ) ? array_map( 'sanitize_key', (array) $_POST['wabc_remove'] ) : array();
		$allowed = array_keys( wabc_removable_nodes() );
		$settings['remove'] = array_values( array_intersect( $remove, $allowed ) );

		$hide_roles = isset( $_POST['wabc_hide_roles'] ) ? array_map( 'sanitize_key', (array) $_POST['wabc_hide_roles'] ) : array();
		$all_roles  = array_keys( get_editable_roles() );
		$settings['hide_roles'] = array_values( array_intersect( $hide_roles, $all_roles ) );

		// Custom links: parallel arrays title/url/parent/new_tab.
		$titles  = isset( $_POST['wabc_link_title'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['wabc_link_title'] ) ) : array();
		$urls    = isset( $_POST['wabc_link_url'] ) ? array_map( 'esc_url_raw', wp_unslash( (array) $_POST['wabc_link_url'] ) ) : array();
		$parents = isset( $_POST['wabc_link_parent'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['wabc_link_parent'] ) ) : array();
		$newtab  = isset( $_POST['wabc_link_new_tab'] ) ? array_map( 'sanitize_key', wp_unslash( (array) $_POST['wabc_link_new_tab'] ) ) : array();
		$links   = array();
		foreach ( $titles as $i => $title ) {
			$url = isset( $urls[ $i ] ) ? trim( (string) $urls[ $i ] ) : '';
			if ( '' === trim( $title ) || '' === $url ) {
				continue;
			}
			$links[] = array(
				'title'   => $title,
				'url'     => $url,
				'parent'  => isset( $parents[ $i ] ) ? $parents[ $i ] : '',
				'new_tab' => isset( $newtab[ $i ] ) ? 1 : 0,
			);
		}
		$settings['custom_links'] = array_slice( $links, 0, 20 );

		update_option( WABC_OPTION, $settings );
		$notice = __( 'Admin bar settings saved.', 'wp-admin-bar-customizer' );
	}

	$roles = get_editable_roles();
	$links = $settings['custom_links'];
	// Always show at least 3 empty link rows.
	while ( count( $links ) < 3 ) {
		$links[] = array(
			'title'   => '',
			'url'     => '',
			'parent'  => '',
			'new_tab' => 0,
		);
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Admin Bar Customizer', 'wp-admin-bar-customizer' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'wabc_save', 'wabc_nonce' ); ?>

			<h2><?php esc_html_e( 'Colour', 'wp-admin-bar-customizer' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wabc_color"><?php esc_html_e( 'Bar colour', 'wp-admin-bar-customizer' ); ?></label></th>
					<td><input type="color" id="wabc_color" name="wabc_color" value="<?php echo esc_attr( $settings['color'] ); ?>">
					<p class="description"><?php esc_html_e( 'Default WordPress grey is #23282d. Pick your brand colour.', 'wp-admin-bar-customizer' ); ?></p></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Remove items', 'wp-admin-bar-customizer' ); ?></h2>
			<p><?php esc_html_e( 'Checked items are hidden from the admin bar for everyone.', 'wp-admin-bar-customizer' ); ?></p>
			<?php foreach ( wabc_removable_nodes() as $node_id => $label ) : ?>
				<label style="display:block;margin:4px 0;">
					<input type="checkbox" name="wabc_remove[]" value="<?php echo esc_attr( $node_id ); ?>" <?php checked( in_array( $node_id, (array) $settings['remove'], true ) ); ?>>
					<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $node_id ); ?></code>
				</label>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Hide the bar for roles', 'wp-admin-bar-customizer' ); ?></h2>
			<p><?php esc_html_e( 'The bar is hidden on the front end for these roles. Administrators are never hidden (safety — so you can always get back here).', 'wp-admin-bar-customizer' ); ?></p>
			<?php foreach ( $roles as $slug => $role ) : ?>
				<?php if ( 'administrator' === $slug ) continue; ?>
				<label style="display:block;margin:4px 0;">
					<input type="checkbox" name="wabc_hide_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['hide_roles'], true ) ); ?>>
					<?php echo esc_html( $role['name'] ); ?>
				</label>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Custom links', 'wp-admin-bar-customizer' ); ?></h2>
			<p><?php esc_html_e( 'Add your own links to the bar — support page, analytics, client dashboard. Parent is optional: leave blank for a top-level link, or enter a node ID (e.g. site-name) to nest it under an existing menu.', 'wp-admin-bar-customizer' ); ?></p>
			<table class="widefat fixed">
				<thead><tr>
					<th><?php esc_html_e( 'Title', 'wp-admin-bar-customizer' ); ?></th>
					<th><?php esc_html_e( 'URL', 'wp-admin-bar-customizer' ); ?></th>
					<th><?php esc_html_e( 'Parent node (optional)', 'wp-admin-bar-customizer' ); ?></th>
					<th><?php esc_html_e( 'New tab', 'wp-admin-bar-customizer' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $links as $i => $link ) : ?>
						<tr>
							<td><input type="text" name="wabc_link_title[]" value="<?php echo esc_attr( $link['title'] ); ?>" class="widefat"></td>
							<td><input type="url" name="wabc_link_url[]" value="<?php echo esc_attr( $link['url'] ); ?>" class="widefat" placeholder="https://…"></td>
							<td><input type="text" name="wabc_link_parent[]" value="<?php echo esc_attr( $link['parent'] ); ?>" class="widefat" placeholder="site-name"></td>
							<td><input type="checkbox" name="wabc_link_new_tab[<?php echo (int) $i; ?>]" value="1" <?php checked( ! empty( $link['new_tab'] ) ); ?>></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save settings', 'wp-admin-bar-customizer' ) ); ?>
		</form>
	</div>
	<?php
}
