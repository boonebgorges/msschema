<?php

class MSSchema_Admin {
	public function __construct() {
		$this->page_url = network_admin_url( 'admin.php?page=msschema' );

		add_action( 'network_admin_menu', array( $this, 'add_admin_menu' ) );
	}

	public function add_admin_menu() {
		if ( ! empty( $_GET['page'] ) && 'msschema' === $_GET['page'] && ! empty( $_GET['action'] ) ) {
			$this->start();
		}

		add_menu_page(
			'MS Schema',
			'MS Schema',
			'delete_users',
			'msschema',
			array( $this, 'admin_menu_markup' )
		);
	}

	public function admin_menu_markup() {
		$success = false;
		if ( isset( $_GET['success'] ) ) {
			$success = true;
			if ( 'modtables' === $_GET['success'] ) {
				$message = 'Tables modified.';
			}
		}

		?>
		<div class="wrap">
			<h2>MS Schema setup</h2>

			<?php if ( $success ) : ?>
				<div id="message" class="success">
					<p><?php echo $message ?></p>
				</div>
			<?php endif ?>

			<form action="<?php echo $this->page_url ?>&action=modtables" method="post">
				<div>
					<h3>Modify tables</h3>
					<input type="submit" name="submit" value="Modify Tables" />
				</div>
			</form>

			<form action="<?php echo $this->page_url ?>&action=migratesites" method="post">
				<div>
					<h3>Migrate sites</h3>
					<input type="submit" name="submit" value="Migrate Sites" />
				</div>
			</form>
		</div>

		<?php
	}

	public function start() {
		switch ( $_GET['action'] ) {
			case 'modtables' :
				$this->modtables();
				break;

			case 'migratesites' :
				$this->migratesites();
				break;
		}
	}

	private function modtables() {
		global $wpdb;

		$tables = msschema_get_root_blog_tables();

		// Add the blog_id column
		foreach ( $tables as $table ) {
			$sql = "ALTER TABLE $table ADD blog_id BIGINT(20)";
			$wpdb->query( $sql );
		}

		wp_redirect( $this->page_url . '&success=modtables' );
	}

	private function migratesites() {
		global $wpdb;

		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

		foreach ( $blog_ids as $blog_id ) {
			$tables = msschema_get_blog_tables( $blog_id );
			$blog_prefix = $wpdb->get_blog_prefix( $blog_id );
			foreach ( $tables as $table ) {
				$tbase = substr( $table, strlen( $blog_prefix ) );
				$root_table = $wpdb->base_prefix . $tbase;

				// Blog 1 content is already there, but we need to fill
				// in the blog_id column
				if ( $blog_prefix === $wpdb->base_prefix ) {
					$wpdb->query( "UPDATE {$root_table} SET blog_id = {$blog_id}" );
				} else {
					// Going to hack it here.
					// Import all stuff, then update blog_id where it
					// hasn't yet been set. This can't really be run
					// more than once

					// Must skip the auto-increment
					$describe = $wpdb->get_results( "DESCRIBE {$table}" );
					$cols = array();
					foreach ( $describe as $d ) {
						if ( 'PRI' != $d->Key ) {
							$cols[] = $d->Field;
						}
					}

					$ccols = implode( ',', $cols );

					$wpdb->query( "INSERT INTO {$root_table} ({$ccols}) SELECT {$ccols} FROM {$table}" );
					$wpdb->query( "UPDATE {$root_table} SET blog_id = {$blog_id} WHERE blog_id IS NULL" );
				}
			}
		}
	}
}
new MSSchema_Admin();
