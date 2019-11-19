<?php
	//load classes init method
	use NeatekTinkoff\NeatekTinkoff\NeatekTinkoff;
	add_action('init', array('PMProGateway_tinkoff', 'init'));

	/**
	 * PMProGateway_gatewayname Class
	 *
	 * Handles tinkoff integration.
	 *
	 */
	class PMProGateway_tinkoff extends PMProGateway
	{
		function PMProGateway($gateway = NULL)
		{
			$this->gateway = $gateway;
			return $this->gateway;
		}										

		/**
		 * Run on WP init
		 *
		 * @since 1.8
		 */
		static function init()
		{
			//make sure tinkoff is a gateway option
			add_filter('pmpro_gateways', array('PMProGateway_tinkoff', 'pmpro_gateways'));

			//add fields to payment settings
			add_filter('pmpro_payment_options', array('PMProGateway_tinkoff', 'pmpro_payment_options'));
			add_filter('pmpro_payment_option_fields', array('PMProGateway_tinkoff', 'pmpro_payment_option_fields'), 10, 2);

			//add some fields to edit user page (Updates)
			add_action('pmpro_after_membership_level_profile_fields', array('PMProGateway_tinkoff', 'user_profile_fields'));
			add_action('profile_update', array('PMProGateway_tinkoff', 'user_profile_fields_save'));

			//updates cron
			add_action('pmpro_activation', array('PMProGateway_tinkoff', 'pmpro_activation'));
			add_action('pmpro_deactivation', array('PMProGateway_tinkoff', 'pmpro_deactivation'));
			add_action('pmpro_cron_tinkoff_subscription_updates', array('PMProGateway_tinkoff', 'pmpro_cron_tinkoff_subscription_updates'));

			function my_pmpro_required_billing_fields($fields){
				if(is_array($fields)){
					unset($fields['bfirstname']);
					unset($fields['blastname']);
					unset($fields['baddress1']);
					unset($fields['baddress2']);
					unset($fields['bcity']);
					unset($fields['bstate']);
					unset($fields['bzipcode']);
					unset($fields['bcountry']);
					unset($fields['bphone']);
					unset($fields['bemail']);
					unset($fields['CardType']);
					unset($fields['AccountNumber']);
					unset($fields['ExpirationMonth']);
					unset($fields['ExpirationYear']);
					unset($fields['CVV']);
				}
				return $fields;
			}
			add_action('pmpro_required_billing_fields', 'my_pmpro_required_billing_fields');

			//code to add at checkout if tinkoff is the current gateway
			$gateway = pmpro_getOption("gateway");
			if($gateway == "tinkoff")
			{
				add_action('pmpro_checkout_preheader', array('PMProGateway_tinkoff', 'pmpro_checkout_preheader'));
				add_filter('pmpro_checkout_order', array('PMProGateway_tinkoff', 'pmpro_checkout_order'));
				add_filter('pmpro_include_billing_address_fields', array('PMProGateway_tinkoff', 'pmpro_include_billing_address_fields'));
				// add_filter('pmpro_include_cardtype_field', array('PMProGateway_tinkoff', 'pmpro_include_billing_address_fields'));
				add_filter('pmpro_include_payment_information_fields', array('PMProGateway_tinkoff', 'pmpro_include_payment_information_fields'));
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_tinkoff', 'pmpro_checkout_default_submit_button'));
			}
		}

		/**
		 * Make sure tinkoff is in the gateways list
		 *
		 * @since 1.8
		 */
		static function pmpro_gateways($gateways)
		{
			if(empty($gateways['tinkoff']))
				$gateways['tinkoff'] = __('tinkoff', 'pmpro');

			return $gateways;
		}

		/**
		 * Get a list of payment options that the tinkoff gateway needs/supports.
		 *
		 * @since 1.8
		 */
		static function getGatewayOptions()
		{
			$options = array(
				'sslseal',
				'nuclear_HTTPS',
				'gateway_environment',
				'currency',
				'use_ssl',
				'tax_state',
				'tax_rate',
				'accepted_credit_cards',
				'terminal_key',
				'password',
				'rate'
			);

			return $options;
		}

		/**
		 * Set payment options for payment settings page.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_options($options)
		{
			//get tinkoff options
			$tinkoff_options = PMProGateway_tinkoff::getGatewayOptions();

			//merge with others.
			$options = array_merge($tinkoff_options, $options);

			return $options;
		}

		/**
		 * Display fields for tinkoff options.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_option_fields($values, $gateway)
		{
		?>
		<tr class="pmpro_settings_divider gateway gateway_tinkoff" <?php if($gateway != "tinkoff") { ?>style="display: none;"<?php } ?>>
			<td colspan="2">
				<?php _e('tinkoff Settings', 'pmpro'); ?>
			</td>
		</tr>
		<tr class="gateway gateway_tinkoff" <?php if($gateway != "tinkoff") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="terminal_key"><?php _e('TerminalKey', 'pmpro');?>:</label>
			</th>
			<td>
				<input type="text" id="terminal_key" name="terminal_key" size="60" value="<?php echo esc_attr($values['terminal_key'])?>" />
			</td>
		</tr>
		<tr class="gateway gateway_tinkoff" <?php if($gateway != "tinkoff") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="password"><?php _e('Password', 'pmpro');?>:</label>
			</th>
			<td>
				<input type="text" id="password" name="password" size="60" value="<?php echo esc_attr($values['password'])?>" />
			</td>
		</tr>
		<tr class="gateway gateway_tinkoff" <?php if($gateway != "tinkoff") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="rate"><?php _e('Rate', 'pmpro');?>:</label>
			</th>
			<td>
				<input type="text" id="rate" name="rate" size="60" value="<?php echo esc_attr($values['rate'])?>" />
			</td>
		</tr>
		<?php
		}

		/**
		 * Filtering orders at checkout.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_order($morder)
		{
			return $morder;
		}

		/**
		 * Code to run after checkout
		 *
		 * @since 1.8
		 */
		static function pmpro_after_checkout($user_id, $morder)
		{
		}
		
		/**
		 * Use our own payment fields at checkout. (Remove the name attributes.)		
		 * @since 1.8
		 */
		static function pmpro_include_payment_information_fields($include)
		{
			return false;
		}

		/**
		 * Fields shown on edit user page
		 *
		 * @since 1.8
		 */
		static function user_profile_fields($user)
		{
		}

		/**
		 * Process fields from the edit user page
		 *
		 * @since 1.8
		 */
		static function user_profile_fields_save($user_id)
		{
		}

		/**
		 * Cron activation for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_activation()
		{
			wp_schedule_event(time(), 'daily', 'pmpro_cron_tinkoff_subscription_updates');
		}

		/**
		 * Cron deactivation for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_deactivation()
		{
			wp_clear_scheduled_hook('pmpro_cron_tinkoff_subscription_updates');
		}

		/**
		 * Cron job for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_cron_tinkoff_subscription_updates()
		{
		}

		static function pmpro_checkout_preheader()
		{
			global $gateway, $pmpro_level;
			if($gateway == "tinkoff" && !pmpro_isLevelFree($pmpro_level)){}
		}

		static function pmpro_checkout_default_submit_button($show)
		{
			global $gateway, $pmpro_requirebilling;
			//show our submit buttons
			?>

			<h1><? 
				$options = get_option('PMProGateway_tinkoff' ); 
				var_dump($options);
			?></h1>

			<span id="pmpro_submit_span">
				<input type="hidden" name="submit-checkout" value="1" />
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout process_card" value="<?php if($pmpro_requirebilling) { _e('Submit and Check Out', 'pmpro'); } else { _e('Submit and Confirm', 'pmpro');}?> &raquo;" />
			</span>

			<?php
			//don't show the default
			return false;
		}

		static function pmpro_include_billing_address_fields($include)
		{
			if(!pmpro_getOption("tinfoff_billingaddress"))
				$include = false;
			return $include;
		}
		
		function process(&$order)
		{

			//check for initial payment
			if(floatval($order->InitialPayment) == 0){
				//auth first, then process
				if($this->authorize($order)){						
					$this->void($order);										
					if(!pmpro_isLevelTrial($order->membership_level))
					{
						//subscription will start today with a 1 period trial (initial payment charged separately)
						$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
						$order->TrialBillingPeriod = $order->BillingPeriod;
						$order->TrialBillingFrequency = $order->BillingFrequency;													
						$order->TrialBillingCycles = 1;
						$order->TrialAmount = 0;
						
						//add a billing cycle to make up for the trial, if applicable
						if(!empty($order->TotalBillingCycles))
							$order->TotalBillingCycles++;
					}
					elseif($order->InitialPayment == 0 && $order->TrialAmount == 0)
					{
						//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
						$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";														
						$order->TrialBillingCycles++;
						
						//add a billing cycle to make up for the trial, if applicable
						if($order->TotalBillingCycles)
							$order->TotalBillingCycles++;
					}
					else
					{
						//add a period to the start date to account for the initial payment
						$order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time("timestamp"))) . "T0:0:0";
					}
					
					$order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);
					return $this->subscribe($order);
				}
				else
				{
					if(empty($order->error))
						$order->error = __("Unknown error: Authorization failed.", "pmpro");
					return false;
				}
			}
			else
			{
				//charge first payment
				if($this->charge($order))
				{							
					//set up recurring billing					
					if(pmpro_isLevelRecurring($order->membership_level))
					{						
						if(!pmpro_isLevelTrial($order->membership_level))
						{
							//subscription will start today with a 1 period trial
							$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
							$order->TrialBillingPeriod = $order->BillingPeriod;
							$order->TrialBillingFrequency = $order->BillingFrequency;													
							$order->TrialBillingCycles = 1;
							$order->TrialAmount = 0;
							
							//add a billing cycle to make up for the trial, if applicable
							if(!empty($order->TotalBillingCycles))
								$order->TotalBillingCycles++;
						}
						elseif($order->InitialPayment == 0 && $order->TrialAmount == 0)
						{
							//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
							$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";														
							$order->TrialBillingCycles++;
							
							//add a billing cycle to make up for the trial, if applicable
							if(!empty($order->TotalBillingCycles))
								$order->TotalBillingCycles++;
						}
						else
						{
							//add a period to the start date to account for the initial payment
							$order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $this->BillingFrequency . " " . $this->BillingPeriod, current_time("timestamp"))) . "T0:0:0";
						}
						
						$order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);
						if($this->subscribe($order))
						{
							return true;
						}
						else
						{
							if($this->void($order))
							{
								if(!$order->error)
									$order->error = __("Unknown error: Payment failed.", "pmpro");
							}
							else
							{
								if(!$order->error)
									$order->error = __("Unknown error: Payment failed.", "pmpro");
								
								$order->error .= " " . __("A partial payment was made that we could not void. Please contact the site owner immediately to correct this.", "pmpro");
							}
							
							return false;								
						}
					}
					else
					{
						//only a one time charge
						$order->status = "success";	//saved on checkout page											
						return true;
					}
				}
				else
				{
					if(empty($order->error))
						$order->error = __("Unknown error: Payment failed.", "pmpro");
					
					return false;
				}	
			}	
		}
		
		/*
			Run an authorization at the gateway.

			Required if supporting recurring subscriptions
			since we'll authorize $1 for subscriptions
			with a $0 initial payment.
		*/
		function authorize(&$order)
		{
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();
			
			//code to authorize with gateway and test results would go here

			//simulate a successful authorization
			$order->payment_transaction_id = "TEST" . $order->code;
			$order->updateStatus("authorized");													
			return true;					
		}
		
		/*
			Void a transaction at the gateway.

			Required if supporting recurring transactions
			as we void the authorization test on subs
			with a $0 initial payment and void the initial
			payment if subscription setup fails.
		*/
		function void(&$order)
		{
			//need a transaction id
			if(empty($order->payment_transaction_id))
				return false;
			
			//code to void an order at the gateway and test results would go here

			//simulate a successful void
			$order->payment_transaction_id = "TEST" . $order->code;
			$order->updateStatus("voided");					
			return true;
		}
				
		/*
			Make a charge at the gateway.

			Required to charge initial payments.
		*/
		function charge(&$order)
		{
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();
			
			//simulate a successful charge
			$tinkoff = $this->tinkoff_pay($order);
			$tinkoff->doRedirect(); 
			$tinkoff->getResultResponse();

			$order->updateStatus("success");
			$order->payment_transaction_id = "TEST" . $order->code;
			
			return true;						
		}

		public function tinkoff_pay(&$order){
			global $current_user;
			$user_email = $current_user->user_email;
			$display_name = $current_user->display_name;
			$current_locale = pll_current_language();
			global $pmpro_level;	
			$initial_payment_raw = $pmpro_level->initial_payment; 
			$rate = 70; // Курс
			$initial_payment = $initial_payment_raw * $rate * 100;
			

			require_once 'tinkoff.class.php';

			$tinkoff = new NeatekTinkoff(
				array(
					array(
						'TerminalKey' => '1558724162733',
						'Password'    => 'fpw0sasfasm9zklh',
					),
					array(
						'db_name' => '',
						'db_host' => '',
						'db_user' => '',
						'db_pass' => '',
					),
				)
			);


			//code to charge with gateway and test results would go here
			$tinkoff->AddMainInfo(
				array(
					'OrderId'     => $order->code, // Не будет работать при подключении к БД, будет автоматически ставиться свой номер заказа из базы данных, рекомендуется всегда оставлять значение = 1 при использовании PDO DB
					'Description' => __("1 year for a member ", "pmpro").$display_name, // Описание заказа
					'Language'    => $current_locale, // Язык интерфейса Тинькофф
				)
			);
			$tinkoff->SetRecurrent(); // Указать что рекуррентный платёж, можно не указывать
			$tinkoff->AddItem(
				array(
					'Name'     => __("1 year for a member ", "pmpro").$display_name, // Максимум 128 символов
					'Price'    => (float) $initial_payment, // В копейках
					"Quantity" => (float) 1.00, // Вес или количество
					"Tax"      => "none", // В чеке НДС
				)
			);
			$tinkoff->SetOrderEmail($user_email); // Обязательно указать емайл
			$tinkoff->SetTaxation('usn_income'); // Тип налогообложения 
			$tinkoff->Init(); // Инициализация заказа, и запись в БД если прописаны настройки
			return $tinkoff;
		}
		
		/*
			Setup a subscription at the gateway.

			Required if supporting recurring subscriptions.
		*/
		function subscribe(&$order)
		{
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();
			
			//filter order before subscription. use with care.
			$order = apply_filters("pmpro_subscribe_order", $order, $this);
			
			//code to setup a recurring subscription with the gateway and test results would go here

			//simulate a successful subscription processing
			$order->status = "success";		
			$order->subscription_transaction_id = "TEST" . $order->code;				
			return true;
		}	
		
		/*
			Update billing at the gateway.

			Required if supporting recurring subscriptions and
			processing credit cards on site.
		*/
		function update(&$order)
		{
			//code to update billing info on a recurring subscription at the gateway and test results would go here

			//simulate a successful billing update
			return true;
		}
		
		/*
			Cancel a subscription at the gateway.

			Required if supporting recurring subscriptions.
		*/
		function cancel(&$order)
		{
			//require a subscription id
			if(empty($order->subscription_transaction_id))
				return false;
			
			//code to cancel a subscription at the gateway and test results would go here

			//simulate a successful cancel			
			$order->updateStatus("cancelled");					
			return true;
		}	
		
		/*
			Get subscription status at the gateway.

			Optional if you have code that needs this or
			want to support addons that use this.
		*/
		function getSubscriptionStatus(&$order)
		{
			//require a subscription id
			if(empty($order->subscription_transaction_id))
				return false;
			
			//code to get subscription status at the gateway and test results would go here

			//this looks different for each gateway, but generally an array of some sort
			return array();
		}

		/*
			Get transaction status at the gateway.

			Optional if you have code that needs this or
			want to support addons that use this.
		*/
		function getTransactionStatus(&$order)
		{			
			//code to get transaction status at the gateway and test results would go here

			//this looks different for each gateway, but generally an array of some sort
			return array();
		}
	}