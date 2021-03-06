<?php
namespace lsx_cnw\classes;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

/**
 * Coupon Notification WooCommerce Email class
 *
 * @since 0.1
 * @extends \WC_Email
 */
class CouponNotificationEmail extends \WC_Email {


	/**
	 * Holds class instance
	 *
	 * @since 1.0.0
	 *
	 * @var      object \lsx_cnw\classes\CouponNotificationEmail()
	 */
	protected static $instance = null;

	/**
	 * Set email defaults
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// set ID, this simply needs to be a unique name.
		$this->id = 'lsx_cnw_coupon_notification';

		// Is a customer email.
		$this->customer_email = true;

		// this is the title in WooCommerce Email settings.
		$this->title = __( 'Coupon Notification' );

		// this is the description in WooCommerce email settings.
		$this->description = __( 'Coupon Notification Email sent to WooCommerce clients who subscribed to either Monthly or Annual subscription.' );

		// For admin area to let the user know we are sending this email to customers.
		$this->customer_email = true;

		// these are the default heading and subject lines that can be overridden using the settings.
		$this->heading = __( 'New Coupon from RW Plus!' );
		$this->subject = __( 'You have a new coupon from RW Plus.' );

		// these define the locations of the templates that this email should use, we'll just use the new order template since this email is similar.
		$this->template_html  = 'emails/lsx-coupon-notification.php';
		$this->template_plain = 'emails/plain/lsx-coupon-notification.php';
		$this->template_base  = LSX_CNW_PATH . 'templates/';

		// We tap into woocommerce_thankyou because coupon generation happens at woocommerce_before_thankyou.
		// add_action( 'woocommerce_thankyou', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'trigger' ) );

		// Call parent constructor to load any other defaults not explicity defined here.
		parent::__construct();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return    object \lsx_cnw\classes\CouponNotificationEmail()    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize Settings Form Fields
	 *
	 * @since 2.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable Notification' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification' ),
				'default' => 'yes',
			),
			'subject'            => array(
				'title'       => __( 'Email Subject' ),
				'type'        => 'text',
				/* translators: %s: default email subject */
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email Heading' ),
				'type'        => 'text',
				/* translators: %s: default email heading */
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content' ),
				'description' => __( 'Text to appear below the main email content.' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A' ),
				'type'        => 'textarea',
				'default'     => '',
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Email type options.
	 *
	 * @return array
	 */
	public function get_email_type_options() {
		$types = array( 'plain' => __( 'Plain text' ) );

		if ( class_exists( 'DOMDocument' ) ) {
			$types['html'] = __( 'HTML' );
			// $types['multipart'] = __( 'Multipart' );
		}

		return $types;
	}


	/**
	 * Determine if the email should actually be sent and setup email merge variables.
	 *
	 * @since 0.1
	 * @param int $order_id Order ID.
	 */
	public function trigger( $order_id ) {
		// bail if no order ID is present.
		if ( ! $order_id ) {
			return;
		}
		// Send welcome email only once and not on every order status change.
		if ( ! get_post_meta( $order_id, 'lsx_cnw_coupon_notification_sent', true ) ) {
			// setup order object.
			$this->object = new \WC_Order( $order_id );

			// setup email recipient.
			$this->recipient = $this->object->get_billing_email();

			// get order items as array.
			$order_items = $this->object->get_items();

			// replace variables in the subject/headings.
			$this->find[]    = '{order_date}';
			$this->replace[] = date_i18n( woocommerce_date_format(), strtotime( $this->object->get_date_paid() ) );

			$this->find[]    = '{order_number}';
			$this->replace[] = $this->object->get_order_number();

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			// woohoo, send the email!
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

			// add order note about the same.
			/* translators: %s: order id */
			$this->object->add_order_note( sprintf( __( 'Coupon notification email for order id %1$s was sent to the customer.' ), $order_id ) );

			// Set order meta to indicate that the email was sent.
			update_post_meta( $this->object->get_id(), 'lsx_cnw_coupon_notification_sent', 1 );
		}
	}

	function custom_get_order_notes( $order_id ) {
		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
		$comments = get_comments(
			array(
				'post_id' => $order_id,
				'orderby' => 'comment_ID',
				'order'   => 'DESC',
				'approve' => 'approve',
				'type'    => 'order_note',
			)
		);
		$notes    = wp_list_pluck( $comments, 'comment_content' );
		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
		return $notes;
	}

	/**
	 * Implementation of get_content_html function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_html() {
		$order_id = $this->object->get_id();

		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'customer_note'      => $this->custom_get_order_notes( $order_id ),
				'coupon'             => get_post_meta( $order_id, 'lsx_cew_coupon_code', true ),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}


	/**
	 * Implementation of get_content_plain function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_plain() {
		$order_id = $this->object->get_id();

		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'customer_note'      => $this->custom_get_order_notes( $order_id ),
				'coupon'             => get_post_meta( $order_id, 'lsx_cew_coupon_code', true ),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
} // end \WC_Coupon_Notification_Email class
