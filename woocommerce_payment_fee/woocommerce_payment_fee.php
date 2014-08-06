<?php
/*
Plugin Name: WooCommerce Cod Fee
Plugin URI: http://developers.overplace.com
Description: Sovrapprezzo Pagamenti
Version: 0.1
Author: UT Overplace
Author URI: http://www.overplace.com
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


if(in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	if(!class_exists('woocommerce_cod_fee')){
		class woocommerce_cod_fee{
			
			private $current_gateway_title = '';
			
			private $current_gateway_extra_charges = '';
			
			private $plugin_url;
			
			public function __construct(){
				add_action( 'admin_head', array($this, 'add_form_fields'));
				add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 10, 1 );
				add_action( 'init', array(&$this, 'add_js_extra_fee'), 10);
				add_action( 'woocommerce_order_items_table', array($this, 'add_order_item_table'),20, 1);
			}
			
			public function add_js_extra_fee(){
				wp_enqueue_script( 'wc-add-extra-charges', $this->plugin_url().'/wccf.js', array('wc-checkout'), false, true );
			}
			
			/**
			 * Add a row to the order item table
			 */
			public function add_order_item_table(WC_Order $order){
				$extra_charges = get_option( 'woocommerce_'.$order->payment_method.'_extra_charges');
				$extra_charges_type_value = get_option('woocommerce_'.$order->payment_method.'_extra_charges_type');
				
				if($extra_charges && $extra_charges_type_value){
					$currency = get_woocommerce_currency_symbol();
					?><tr>
						<th scope="row"><?php _e('Extra fee'); ?>:</th>
						<th scope="row"><span class="amount"><?php echo $currency.' '.$extra_charges; ?></span> <small><?php echo __('on the payment method ').$order->payment_method_title; ?></small></th>
					</tr>
					<?php 
				}
			}
			
			/**
			 * Add an option to the payment method admin settings panel
			 */
			public function add_form_fields(){
				global $woocommerce;

				$current_tab        = ( empty( $_GET['tab'] ) ) ? '' : sanitize_text_field( urldecode( $_GET['tab'] ) );
				$current_section    = ( empty( $_REQUEST['section'] ) ) ? '' : sanitize_text_field( urldecode( $_REQUEST['section'] ) );
				
				if($current_tab == 'checkout' && $current_section!=''){
					$gateways = $woocommerce->payment_gateways->payment_gateways();
					
					foreach($gateways as $gateway){
						if(strtolower(get_class($gateway))==$current_section){
							$current_gateway = $gateway -> id;
							$extra_charges_id = 'woocommerce_'.$current_gateway.'_extra_charges';
							$extra_charges_type = $extra_charges_id.'_type';
							if(isset($_REQUEST['save'])){
								
								update_option( $extra_charges_id, $_REQUEST[$extra_charges_id] );
								update_option( $extra_charges_type, $_REQUEST[$extra_charges_type] );
							}
								
							$extra_charges = get_option( $extra_charges_id);
							$extra_charges_type_value = get_option($extra_charges_type);
						}
					}
		
					?>
		            <script>
				            jQuery(document).ready(function($){
				                $data = '<h4><?php _e('Add payment based extra fee'); ?></h4><table class="form-table">';
				                $data += '<tr valign="top">';
				                $data += '<th scope="row" class="titledesc"><?php _e('Amount'); ?></th>';
				                $data += '<td class="forminp">';
				                $data += '<fieldset>';
				                $data += '<input style="" name="<?php echo $extra_charges_id?>" id="<?php echo $extra_charges_id?>" type="text" value="<?php echo $extra_charges?>"/>';
				                $data += '<br /></fieldset></td></tr>';
				                $data += '<tr valign="top">';
				                $data += '<th scope="row" class="titledesc"><?php _e('Extra fee type'); ?></th>';
				                $data += '<td class="forminp">';
				                $data += '<fieldset>';
				                $data += '<select name="<?php echo $extra_charges_type?>"><option <?php if($extra_charges_type_value=="add") echo "selected=selected"?> value="add"><?php _e('Amount to add to the cart total price'); ?></option>';
				                $data += '<option <?php if($extra_charges_type_value=="percentage") echo "selected=selected"?> value="percentage"><?php _e('Percentage to add to the cart total price'); ?></option>';
				                $data += '<br /></fieldset></td></tr></table>';
				                $('.form-table:last').after($data);
				
				            });
					</script>
				<?php
				}
			}
			
			/**
			 * Recalculate the cart total considering the extra fee
			 */
			public function calculate_totals( $totals ) {
			    global $woocommerce;
			    $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
			    $current_gateway = '';

			    if ( ! empty( $available_gateways ) ) {
			        if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
			            $current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
			        } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
			            $current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
			        } else {
			            $current_gateway =  current( $available_gateways );
			
			        }
			    }
			    
			    if($current_gateway!=''){
			        $current_gateway_id = $current_gateway -> id;
			        $extra_charges_id = 'woocommerce_'.$current_gateway_id.'_extra_charges';
			        $extra_charges_type = $extra_charges_id.'_type';
			        $extra_charges = (float)get_option( $extra_charges_id);
			        $extra_charges_type_value = get_option( $extra_charges_type);
			         
			        if($extra_charges){
			           
						if($extra_charges_type_value=="percentage"){
							if($totals->tax_display_cart === 'incl'){
								$totals->fee_total = round(($totals->subtotal/100)*$extra_charges,2);
							}elseif($totals->tax_display_cart === 'excl'){
								$totals->fee_total = round(($totals->cart_contents_total/100)*$extra_charges);
							}
						}else{
							if($totals->tax_display_cart == 'incl'){
								$totals->fee_total = $extra_charges;
							}elseif($totals->tax_display_cart == 'excl'){
								$totals->fee_total = $extra_charges;
							}
						}
	 					
			            $this -> current_gateway_title = $current_gateway -> title;
			            $this -> current_gateway_extra_charges = $extra_charges;
			            $this -> current_gateway_extra_charges_type_value = $extra_charges_type_value;
			            add_action( 'woocommerce_review_order_before_order_total',  array( $this, 'add_payment_gateway_extra_charges_row'));
			        }
			    }
			    return $totals;
			}
		
			function add_payment_gateway_extra_charges_row(){
			    ?>
				 <tr class="payment-extra-charge">
				        <th><?php _e('Extra fee'); ?> <?php echo $this->current_gateway_title?></th>
				        <td>
				        <?php if($this->current_gateway_extra_charges_type_value=="percentage"){
				            echo $this->current_gateway_extra_charges.'%';
				        }else{
				         	echo woocommerce_price($this->current_gateway_extra_charges);
				     	}
				     	?>
				     	</td>
				 </tr>
				 <?php
			}
		
		    public function plugin_url() {
		        if ( !empty($this->plugin_url) ) return $this->plugin_url;
		        return $this->plugin_url = untrailingslashit( plugins_url( '/', __FILE__ ) );
		    }
		
		    public function plugin_path() {
		        if ( $this->plugin_path ) return $this->plugin_path;
		        return $this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
		    }
		
		}
	}
	$wcf = new woocommerce_cod_fee();
}



