<?php
/**
 * Paymentsense Gateway Template
 *
 * @package WooCommerce_Paymentsense_Gateway
 * @subpackage WooCommerce_Paymentsense_Gateway/templates
 * @author Paymentsense
 * @link http://www.paymentsense.co.uk/
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

?>
<form name="pms<?php echo esc_attr( $args['target'] ); ?>" action="<?php echo esc_url( $args['acs_url'] ); ?>"
	method="post" target="<?php echo esc_attr( $args['target'] ); ?>" id="pms<?php echo esc_attr( $args['target'] ); ?>">
	<input name="TermUrl" type="hidden" value="<?php echo esc_url( $args['term_url'] ); ?>"/>
	<input name="MD" type="hidden" value="<?php echo esc_attr( $args['crossref'] ); ?>"/>
	<input name="PaReq" type="hidden" value="<?php echo esc_attr( $args['pareq'] ); ?>"/>
	<iframe id="ACSFrame" name="ACSFrame" src="<?php echo esc_url( $args['spinner'] ); ?>"
			width="100%" height="400" style="overflow-y:scroll;border:0"></iframe>
	<a href="<?php echo esc_url( $args['cancel_url'] ); ?>"><?php esc_html_e( 'Cancel order & restore cart', 'woocommerce-paymentsense' ); ?></a>
	<script type="text/javascript">
		document.getElementById("pms<?php echo esc_attr( $args['target'] ); ?>").submit();
	</script>
</form>
