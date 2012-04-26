<?php
/*
Plugin Name: WooCommerce Bollettino Postale Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce with a Bollettino Postale gateway.
Version: 1.0
Author: Rob Clarke
Author URI: 

  Copyright: © 2012 Rob Clarke
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_bollettino_postale_init', 0);

function woocommerce_bollettino_postale_init() {
  class WC_Bollettino_Postale extends WC_Payment_Gateway {

    /**
    * Gateway class
    */

    public function __construct() {

      if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

      /**
        * Localisation
        */
      load_plugin_textdomain('wc-bollettino_postale', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
      $this->id        = 'bollettino_postale';
      $this->icon       = apply_filters('woocommerce_bollettino_postale_icon', '');
      $this->has_fields     = false;
      $this->method_title     = __( 'Bollettino Postale', 'woocommerce' );
      // Load the form fields.
      $this->init_form_fields();
    
      // Load the settings.
      $this->init_settings();
    
      // Define user set variables
      $this->title       = $this->settings['title'];
      $this->description      = $this->settings['description'];
      $this->account_name     = $this->settings['account_name'];
      $this->account_number   = $this->settings['account_number'];
  
      // Actions
      add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
      add_action('woocommerce_thankyou_bollettino_postale', array(&$this, 'thankyou_page'));
    
      // Customer Emails
      add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
    } 

    /**
       * Initialise Gateway Settings Form Fields
       */
      function init_form_fields() {
    
        $this->form_fields = array(
        'enabled' => array(
                'title' => __( 'Abilita/Disabilita', 'woocommerce' ), 
                'type' => 'checkbox', 
                'label' => __( 'Abilita Bollettino Postale', 'woocommerce' ), 
                'default' => 'yes'
              ), 
        'title' => array(
                'title' => __( 'Titolo', 'woocommerce' ), 
                'type' => 'text', 
                'description' => __( 'Definisci il titolo del sistema di pagamento.', 'woocommerce' ), 
                'default' => __( 'Bollettino Postale', 'woocommerce' )
              ),
        'description' => array(
                'title' => __( 'Messaggio personalizzato', 'woocommerce' ), 
                'type' => 'textarea', 
                'description' => __( 'Spiega al cliente come procedere con un bollettino postale. Segnala che la merce non sarà inviata fino al ricevimento del pagamento.', 'woocommerce' ), 
                'default' => __('Paga con un bollettino postale. Mandaci una mail appena hai fatto il versamento indicando il numero d\'ordine e provvederemo all\'invio della merce.', 'woocommerce')
              ),
        'account_name' => array(
                'title' => __( 'Nome Conto', 'woocommerce' ), 
                'type' => 'text', 
                'description' => '', 
                'default' => ''
              ),
        'account_number' => array(
                'title' => __( 'Numero Conto', 'woocommerce' ), 
                'type' => 'text', 
                'description' => '', 
                'default' => ''
              ),
        );
    
      } // End init_form_fields()
    
    /**
     * Admin Panel Options 
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options() {
        ?>
        <h3><?php _e('Bollettino Postale', 'woocommerce'); ?></h3>
        <p><?php _e('Permetti pagamenti con Bollettino Postale.', 'woocommerce'); ?></p>
        <table class="form-table">
        <?php
          // Generate the HTML For the settings form.
          $this->generate_settings_html();
        ?>
      </table><!--/.form-table-->
        <?php
      } // End admin_options()


      /**
      * There are no payment fields for postal order, but we want to show the description if set.
      **/
      function payment_fields() {
        if ($this->description) echo wpautop(wptexturize($this->description));
      }

      function thankyou_page() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    
      ?><h2><?php _e('Our Details', 'woocommerce') ?></h2><ul class="order_details bollettino_postale_details"><?php
    
      $fields = apply_filters('woocommerce_bollettino_postale_fields', array(
        'account_name'   => __('Nome Conto', 'woocommerce'), 
        'account_number'=> __('Numero Conto', 'woocommerce'),  
      ));
    
      foreach ($fields as $key=>$value) :
          if(!empty($this->$key)) :
            echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
          endif;
      endforeach;
    
      ?></ul><?php
      }
    
      /**
      * Add text to user email
      **/
      function email_instructions( $order, $sent_to_admin ) {
      
        if ( $sent_to_admin ) return;
      
        if ( $order->status !== 'on-hold') return;
      
        if ( $order->payment_method !== 'bollettino_postale') return;
      
      if ($this->description) echo wpautop(wptexturize($this->description));
    
      ?><h2><?php _e('Informazioni', 'woocommerce') ?></h2><ul class="order_details bollettino_postale_details"><?php
    
      $fields = apply_filters('woocommerce_bollettino_postale_fields', array(
        'account_name'   => __('Nome Conto', 'woocommerce'), 
        'account_number'=> __('Numero Conto', 'woocommerce'),  
      ));
    
      foreach ($fields as $key=>$value) :
          if(!empty($this->$key)) :
            echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
          endif;
      endforeach;
    
      ?></ul><?php
      }

      /**
      * Process the payment and return the result
      **/
      function process_payment( $order_id ) {
        global $woocommerce;
      
      $order = new WC_Order( $order_id );
    
      // Mark as on-hold (we're awaiting the payment)
      $order->update_status('on-hold', __('In attesa di pagamento Bollettino Postale', 'woocommerce'));
    
      // Reduce stock levels
      $order->reduce_order_stock();

      // Remove cart
      $woocommerce->cart->empty_cart();
    
      // Empty awaiting payment session
      unset($_SESSION['order_awaiting_payment']);
    
      // Return thankyou redirect
      return array(
        'result'   => 'success',
        'redirect'  => add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('thanks'))))
      );
      }

  }

  /**
   * Add the gateway to WooCommerce
   **/
  function add_bollettino_postale_gateway( $methods ) {
    $methods[] = 'WC_Bollettino_Postale'; return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'add_bollettino_postale_gateway' );
}
