<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Payeer_Blocks_Support extends AbstractPaymentMethodType
{
  protected $name = 'payeer';

  public function initialize() 
  {
    $this->settings = get_option( 'woocommerce_payeer_settings', [] );
  }

  public function is_active() 
  {
    $payment_gateways_class = WC()->payment_gateways();
    $payment_gateways = $payment_gateways_class->payment_gateways();
    return $payment_gateways[$this->name]->is_available();
  }

  public function get_payment_method_script_handles()
  {
    wp_register_script(
      'wc-payeer-payments-blocks',
      plugin_dir_url(__FILE__) . 'checkout.js',
      [],
      null,
      true
    );
    return [ 'wc-payeer-payments-blocks' ];
  }

  public function get_payment_method_data() 
  {
    return [
      'title' => $this->get_setting( 'title' ),
      'description' => $this->get_setting( 'description' ),
      'supports' => $this->get_supported_features()
    ];
  }
  
  public function get_supported_features() 
  {
    $payment_gateways = WC()->payment_gateways->payment_gateways();
    return $payment_gateways[$this->name]->supports;
  }
}