<?php 
/*
  Plugin Name: Payeer - модуль оплаты для WooCommerce
  Plugin URI: https://payeer.com
  Description: Модуль для приема платежей в платежной системе Payeer.
  Version: 1.0.4
  Author: Payeer
  Author URI: https://payeer.com
  Copyright: © 2010-2024 Payeer.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH'))
{
	exit;
}

add_action('plugins_loaded', 'woocommerce_payeer', 0);

function woocommerce_payeer()
{
	if (!class_exists('WC_Payment_Gateway'))
	{
		return;
	}
	
	if (class_exists('WC_PAYEER'))
	{
		return;
	}
		
	class WC_PAYEER extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$plugin_dir = plugin_dir_url(__FILE__);
			$this->id = 'payeer';
			$this->icon = apply_filters('woocommerce_payeer_icon', $plugin_dir . 'payeer.png');
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option('title');
			$this->payeer_url = $this->get_option('payeer_url');
			$this->payeer_merchant = $this->get_option('payeer_merchant');
			$this->payeer_secret_key = $this->get_option('payeer_secret_key');
			$this->email_error = $this->get_option('email_error');
			$this->ip_filter = $this->get_option('ip_filter');
			$this->log_file = $this->get_option('log_file');
			$this->description = $this->get_option('description');
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_payeer_status'));
			if (!$this->is_valid_for_use())
			{
				$this->enabled = false;
			}
		}

		function is_valid_for_use()
		{
			return true;
		}

		public function admin_options() 
		{
			?>
			<h3><?php _e('Payeer', 'woocommerce'); ?></h3>
			<p><?php _e('Настройка приема электронных платежей через Payeer.', 'woocommerce'); ?></p>

			<?php if ( $this->is_valid_for_use() ) : ?>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
				
			<?php else : ?>
				<div class="inline error">
					<p>
						<strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: 
						<?php _e('Payeer не поддерживает валюты Вашего магазина.', 'woocommerce' ); ?>
					</p>
				</div>
				<?php
			endif;
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Включить/Выключить', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Включен', 'woocommerce'),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Название', 'woocommerce'),
					'type' => 'text', 
					'description' => __( 'Это название, которое пользователь видит во время выбора способа оплаты.', 'woocommerce' ), 
					'default' => __('Payeer', 'woocommerce')
				),
				'payeer_url' => array(
					'title' => __('URL мерчанта', 'woocommerce'),
					'type' => 'text',
					'description' => __('url для оплаты в системе Payeer', 'woocommerce'),
					'default' => 'https://payeer.com/merchant/'
				),
				'payeer_merchant' => array(
					'title' => __('Идентификатор магазина', 'woocommerce'),
					'type' => 'text',
					'description' => __('Идентификатор магазина, зарегистрированного в системе "PAYEER".<br/>Узнать его можно в <a href="https://payeer.com/account/">аккаунте Payeer</a>: "Аккаунт -> Мой магазин -> Изменить".', 'woocommerce'),
					'default' => ''
				),
				'payeer_secret_key' => array(
					'title' => __('Секретный ключ', 'woocommerce'),
					'type' => 'password',
					'description' => __('Секретный ключ оповещения о выполнении платежа,<br/>который используется для проверки целостности полученной информации<br/>и однозначной идентификации отправителя.<br/>Должен совпадать с секретным ключем, указанным в <a href="https://payeer.com/account/">аккаунте Payeer</a>: "Аккаунт -> Мой магазин -> Изменить".', 'woocommerce'),
					'default' => ''
				),
				'log_file' => array(
					'title' => __('Путь до файла для журнала оплат через Payeer (например, /payeer_orders.log)', 'woocommerce'),
					'type' => 'text',
					'description' => __('Если путь не указан, то журнал не записывается', 'woocommerce'),
					'default' => ''
				),
				'ip_filter' => array(
					'title' => __('IP фильтр', 'woocommerce'),
					'type' => 'text',
					'description' => __('Список доверенных ip адресов, можно указать маску', 'woocommerce'),
					'default' => ''
				),
				'email_error' => array(
					'title' => __('Email для ошибок', 'woocommerce'),
					'type' => 'text',
					'description' => __('Email для отправки ошибок оплаты', 'woocommerce'),
					'default' => ''
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce' ),
					'default' => 'Оплата с помощью Payeer'
				)
			);
		}

		function payment_fields()
		{
			if ($this->description)
			{
				echo wpautop(wptexturize($this->description));
			}
		}
		
		function process_payment($order_id)
		{
      $order = wc_get_order($order_id);
      $m_url = $this->payeer_url . '?';
      $m_shop	= $this->payeer_merchant;
			$m_orderid = $order_id;
			$m_amount = number_format($order->order_total, 2, '.', '');
			$m_curr	= $order->order_currency == 'RUR' ? 'RUB' : $order->order_currency;
			$m_desc = base64_encode('Оплата заказа ' . $order_id);
			$m_key = $this->payeer_secret_key;
			$arHash = array
			(
				$m_shop,
				$m_orderid,
				$m_amount,
				$m_curr,
				$m_desc,
				$m_key
			);
			
			$sign = strtoupper(hash('sha256', implode(":", $arHash)));
      
      $params = [
        'm_shop' => $m_shop,
        'm_orderid' => $m_orderid,
        'm_amount' => $m_amount,
        'm_curr' => $m_curr,
        'm_desc' => $m_desc,
        'm_sign' => $sign,
        'lang' => 'ru',
      ];
      
      $m_url .= http_build_query($params, null, '&');
      return array('result' => 'success', 'redirect' => $m_url);
		}
		
		function check_payeer_status()
		{
			if (isset($_GET['payeer']) && $_GET['payeer'] == 'result')
			{
				if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
				{
					$err = false;
					$message = '';
					
					// запись логов
					
					$log_text = 
						"--------------------------------------------------------\n" .
						"operation id       " . $_POST['m_operation_id'] . "\n" .
						"operation ps       " . $_POST['m_operation_ps'] . "\n" .
						"operation date     " . $_POST['m_operation_date'] . "\n" .
						"operation pay date " . $_POST['m_operation_pay_date'] . "\n" .
						"shop               " . $_POST['m_shop'] . "\n" .
						"order id           " . $_POST['m_orderid'] . "\n" .
						"amount             " . $_POST['m_amount'] . "\n" .
						"currency           " . $_POST['m_curr'] . "\n" .
						"description        " . base64_decode($_POST['m_desc']) . "\n" .
						"status             " . $_POST['m_status'] . "\n" .
						"sign               " . $_POST['m_sign'] . "\n\n";
					
					$log_file = $this->log_file;
					
					if (!empty($log_file))
					{
						file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
					}
					
					// проверка цифровой подписи и ip

					$sign_hash = strtoupper(hash('sha256', implode(":", array(
						$_POST['m_operation_id'],
						$_POST['m_operation_ps'],
						$_POST['m_operation_date'],
						$_POST['m_operation_pay_date'],
						$_POST['m_shop'],
						$_POST['m_orderid'],
						$_POST['m_amount'],
						$_POST['m_curr'],
						$_POST['m_desc'],
						$_POST['m_status'],
						$this->payeer_secret_key
					))));
					
					$valid_ip = true;
					$sIP = str_replace(' ', '', $this->ip_filter);
					
					if (!empty($sIP))
					{
						$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
						if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
						'(' . $arrIP[1] . '|\*{1})(\.)' .
						'(' . $arrIP[2] . '|\*{1})(\.)' .
						'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
						{
							$valid_ip = false;
						}
					}
					
					if (!$valid_ip)
					{
						$message .= " - ip-адрес сервера не является доверенным\n" .
						"   доверенные ip: " . $sIP . "\n" .
						"   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
						$err = true;
					}

					if ($_POST["m_sign"] != $sign_hash)
					{
						$message .= " - не совпадают цифровые подписи\n";
						$err = true;
					}
				
					if (!$err)
					{
						// загрузка заказа
						
						$order = wc_get_order($_POST['m_orderid']);
            $order_currency = $order->get_currency();
            $order_total = $order->get_total();
            $order_status = $order->get_status();
						$order_curr = ($order_currency == 'RUR') ? 'RUB' : $order_currency;
						$order_amount = number_format($order_total, 2, '.', '');
				
						// проверка суммы и валюты
					
						if ($_POST['m_amount'] != $order_amount)
						{
							$message .= " - неправильная сумма\n";
							$err = true;
						}

						if ($_POST['m_curr'] != $order_curr)
						{
							$message .= " - неправильная валюта\n";
							$err = true;
						}
						
						// проверка статуса
						
						if (!$err)
						{
							switch ($_POST['m_status'])
							{
								case 'success':
								
									if ($order_status != 'wc-processing')
									{
										$order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
										WC()->cart->empty_cart();
									}

									break;
									
								default:
									$message .= " - статус платежа не является success\n";
									$order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
									$err = true;
									break;
							}
						}
					}
					
					if ($err)
					{
						$to = $this->email_error;

						if (!empty($to))
						{
							$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n" . $message . "\n" . $log_text;
							$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
							"Content-type: text/plain; charset=utf-8 \r\n";
							mail($to, 'Ошибка оплаты', $message, $headers);
						}
						
						die($_POST['m_orderid'] . '|error');
					}
					else
					{
						die($_POST['m_orderid'] . '|success');
					}
				}
				else
				{
					wp_die('IPN Request Failure');
				}
			}
			else if (isset($_GET['payeer']) && ($_GET['payeer'] == 'calltrue' || $_GET['payeer'] == 'callfalse'))
			{
				WC()->cart->empty_cart();
				$order = wc_get_order($_GET['m_orderid']);
				wp_redirect($this->get_return_url($order));
			}
		}
	}

	function add_payeer_gateway($methods)
	{
		$methods[] = 'WC_PAYEER';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_payeer_gateway');
  
  add_action('before_woocommerce_init', 'payeer_declare_cart_checkout_blocks_compatibility');

  function payeer_declare_cart_checkout_blocks_compatibility()
  {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) 
    {
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
  }

  add_action('woocommerce_blocks_loaded', 'payeer_blocks_support');

  function payeer_blocks_support()
  {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) 
    {
      require_once dirname(__FILE__) . '/includes/blocks/class-wc-gateway-payeer-blocks-support.php';
      add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) 
        {
          $payment_method_registry->register(new WC_Payeer_Blocks_Support);
        }
      );
    }
  }
}
?>