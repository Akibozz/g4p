<?php
/**
 * Class for Affiliates Order linking and Unlinking
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       2.1.1
 * @version     1.2.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Link_Unlink_In_Order' ) ) {

	/**
	 * Main class for Affiliate Order linking and Unlinking functionality
	 */
	class AFWC_Admin_Link_Unlink_In_Order {

		/**
		 * Variable to hold instance of AFWC_Admin_Link_Unlink_In_Order
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Link_Unlink_In_Order Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 *  Constructor
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_afwc_custom_box' ) );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'link_unlink_affiliate_in_order' ) );
		}

		/**
		 * Function to add custom meta box in order add/edit screen.
		 */
		public function add_afwc_custom_box() {
			global $pagenow, $typenow;

			if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && 'shop_order' !== $typenow ) {
				return;
			}

			add_meta_box( 'afwc_order', __( 'Affiliate details', 'affiliate-for-woocommerce' ), array( $this, 'affiliate_in_order' ), 'shop_order', 'side', 'low' );
		}

		/**
		 * Function to add/remove affiliate from an order.
		 */
		public function affiliate_in_order() {
			global $post;

			$order_id = $post->ID;
			if ( empty( $order_id ) ) {
				return;
			}

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			wp_register_script( 'affiliate-user-search', AFWC_PLUGIN_URL . '/assets/js/affiliate-search.js', array( 'jquery', 'wp-i18n' ), $plugin_data['Version'], true );
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'affiliate-user-search', 'affiliate-for-woocommerce' );
			}
			wp_enqueue_script( 'affiliate-user-search' );

			wp_localize_script(
				'affiliate-user-search',
				'affiliateParams',
				array(
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'security'       => wp_create_nonce( 'afwc-search-affiliate-users' ),
					'allowSelfRefer' => afwc_allow_self_refer(),
				)
			);

			$is_commission_recorded = get_post_meta( $order_id, 'is_commission_recorded', true );
			$affiliate_data         = $this->get_order_affiliate_data( $order_id );

			$user_string = '';
			if ( 'yes' === $is_commission_recorded && ! empty( $affiliate_data ) ) {
				$user_id = afwc_get_user_id_based_on_affiliate_id( $affiliate_data['affiliate_id'] );
				if ( ! empty( $user_id ) ) {
					$user = get_user_by( 'id', $user_id );
					if ( is_object( $user ) && $user instanceof WP_User ) {
						$user_string = sprintf(
							/* translators: 1: user display name 2: user ID 3: user email */
							esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'affiliate-for-woocommerce' ),
							$user->display_name,
							absint( $user_id ),
							$user->user_email
						);
					}
				}
			}

			$allow_clear = ( isset( $affiliate_data['status'] ) && 'paid' === $affiliate_data['status'] ) ? 'false' : 'true';
			$disabled    = ( isset( $affiliate_data['status'] ) && 'paid' === $affiliate_data['status'] ) ? 'disabled' : '';

			?>
			<div class="options_group afwc-field">
				<p class="form-field">
					<label for="afwc_referral_order_of"><?php esc_attr_e( 'Assigned to affiliate', 'affiliate-for-woocommerce' ); ?></label>
					<?php echo wp_kses_post( wc_help_tip( _x( 'Search affiliate by email, username, name or user id to assign this order to them. Affiliates will see this order in their My account > Affiliates > Reports.', 'help tip for search and assign affiliate', 'affiliate-for-woocommerce' ) ) ); ?>
					<br><br>
					<select id="afwc_referral_order_of" name="afwc_referral_order_of" style="width: 100%;" class="wc-afw-customer-search" data-placeholder="<?php echo esc_attr_x( 'Search by email, username or name', 'affiliate search placeholder', 'affiliate-for-woocommerce' ); ?>" data-allow-clear="<?php echo esc_attr( $allow_clear ); ?>" data-action="afwc_json_search_affiliates" <?php echo esc_attr( $disabled ); ?>>
						<?php
						if ( ! empty( $user_id ) ) {
							?>
							<option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo esc_html( htmlspecialchars( wp_kses_post( $user_string ) ) ); ?><option>
							<?php
						}
						?>
					</select>
				</p>
			</div>
			<?php
		}

		/**
		 * Function to get affiliate data based on order_id.
		 *
		 * @param int $order_id The Order ID.
		 * @return array Affiliate Data.
		 */
		public function get_order_affiliate_data( $order_id = '' ) {
			if ( empty( $order_id ) ) {
				return;
			}

			global $wpdb;

			$get_affiliate_data_from_order_id = $wpdb->get_results( // phpcs:ignore
				$wpdb->prepare(
					"SELECT affiliate_id, status
														FROM {$wpdb->prefix}afwc_referrals
														WHERE post_id = %d AND reference = ''",
					$order_id
				),
				'ARRAY_A'
			);

			return ( ! empty( $get_affiliate_data_from_order_id[0] ) ? $get_affiliate_data_from_order_id[0] : array() );
		}

		/**
		 * Function to do DB updates when linking/unlinking affiliate from the order.
		 */
		public function link_unlink_affiliate_in_order() {
			if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) { // phpcs:ignore
				return;
			}

			$order_id = isset( $_POST['post_ID'] ) ? wc_clean( wp_unslash( $_POST['post_ID'] ) ) : ''; // phpcs:ignore
			if ( empty( $order_id ) ) {
				return;
			}

			global $wpdb;

			$affiliate_id = isset( $_POST['afwc_referral_order_of'] ) ? wc_clean( wp_unslash( $_POST['afwc_referral_order_of'] ) ) : ''; // phpcs:ignore

			if ( ! empty( $affiliate_id ) ) {
				$old_affiliate_id = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL( (CASE WHEN status = 'paid' THEN -1 ELSE affiliate_id END), 0 ) as affiliate_id
							FROM {$wpdb->prefix}afwc_referrals
							WHERE post_id = %d AND reference = ''",
						$order_id
					)
				);

				if ( ! empty( $old_affiliate_id ) ) {
					// Return if the commission status is paid.
					if ( -1 === $old_affiliate_id ) {
						return;
					}

					// Check if old affiliate id and new affiliate is different.
					if ( $old_affiliate_id !== $affiliate_id ) {

						// Unlink the old affiliate and link to the new affiliate on order.
						if ( $this->unlink_affiliate_from_order( $order_id ) ) {
							$this->link_affiliate_on_order( $order_id, $affiliate_id );
						}
					}
				} else {
					// Directly assign affiliate to order if there is no assigned affiliate.
					$this->link_affiliate_on_order( $order_id, $affiliate_id );
				}
			} else {
				// Unlinking and deleting.
				$this->unlink_affiliate_from_order( $order_id );
			}
		}

		/**
		 * Function to unlink the affiliate by order id.
		 *
		 * @param int $order_id The Order ID.
		 * @return bool.
		 */
		public function unlink_affiliate_from_order( $order_id = 0 ) {
			if ( empty( $order_id ) ) {
				return false;
			}

			global $wpdb;

			// Delete referral data of the order.
			$delete_referral = boolval(
				$wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}afwc_referrals WHERE post_id = %d AND status != %s",
						$order_id,
						esc_sql( 'paid' )
					)
				)
			);

			if ( true === $delete_referral ) {
				// Delete the affiliate meta data related to order id.
				return boolval(
					$wpdb->query( // phpcs:ignore
						$wpdb->prepare(
							"DELETE FROM {$wpdb->prefix}postmeta
								WHERE post_id = %d
								AND meta_key IN ('is_commission_recorded','afwc_order_valid_plans','afwc_set_commission','afwc_parent_commissions')",
							$order_id
						)
					)
				);

			}

			return false;
		}

		/**
		 * Function to assign the affiliate to an order.
		 *
		 * @param int $order_id     The Order ID.
		 * @param int $affiliate_id The Affiliate ID.
		 * @return void.
		 */
		public function link_affiliate_on_order( $order_id = 0, $affiliate_id = 0 ) {

			if ( empty( $order_id ) || empty( $affiliate_id ) ) {
				return;
			}

			$affiliate_api = AFWC_API::get_instance();
			$affiliate_api->track_conversion( $order_id, $affiliate_id, '', array( 'is_affiliate_eligible' => true ) );
			$order      = wc_get_order( $order_id );
			$new_status = is_object( $order ) && is_callable( array( $order, 'get_status' ) ) ? $order->get_status() : '';
			$affiliate_api->update_referral_status( $order_id, '', $new_status );

		}

	}

}

return new AFWC_Admin_Link_Unlink_In_Order();
