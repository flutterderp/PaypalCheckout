<?php
/**
 * PaypalCheckout
 *
 * Uses PayPal Checkout, Orders, etc. v2 to process order payments
 *
 * @link https://developer.paypal.com/demo/checkout/#/pattern/client
 * @link https://developer.paypal.com/demo/checkout/#/pattern/server
 * @link https://developer.paypal.com/docs/checkout/standard/integrate/
 */

define('CONFIG_FILE', __DIR__ . '/settings.json');

class PaypalCheckout
{
	private $config;
	protected $api_uri;
	protected $client_id;
	protected $client_secret;
	protected $ch;
	protected $limit  = 25;
	protected $offset = 0;
	public $endpoint  = '';
	public $today;
	public $token;
	public $token_type;
	public $uxtime;

	function __construct(bool $useSandbox = false)
	{
		$this->today         = new DateTime('now', new DateTimeZone('UTC'));
		$config              = json_decode(file_get_contents(CONFIG_FILE), false);
		$this->api_url     = 'https://api-m.' . ($useSandbox === true ? 'sandbox.' : '') . 'paypal.com';
		$this->client_id     = $config->PaypalConfig->client_id;
		$this->client_secret = $config->PaypalConfig->client_secret;
		$this->ch            = curl_init();
		$this->uxtime        = $this->today->format('U');
	}


	function __destruct()
	{
		curl_close($this->ch);
	}

	/**
	 * Sets the endpoint to use in the cURL request
	 *
	 * @param $endpoint string
	 */
	function setEndpoint(string $endpoint = '/v2/checkout/orders')
	{
		$this->endpoint = filter_var($endpoint, FILTER_SANITIZE_URL);
	}

	/**
	 * Sets cURL options
	 *
	 * @param $http_header  array
	 * @param $post_opts    array|string
	 * @param $request_type string
	 *
	 */
	protected function setOptions(array $http_header, $post_opts = '', string $request_type = 'GET')
	{
		// $http_header[] = 'Authorization: ' . $auth_header;
		// $http_header[] = 'Accept: application/json';
		// $http_header[] = 'Content-Type: application/x-www-form-urlencoded';
		$http_header[] = 'Accept-Encoding: gzip, deflate';
		$http_header[] = 'Cache-Control: no-cache';
		$http_header[] = 'Connection: keep-alive';
		$http_header[] = 'User-Agent: PHP-CLI/' . PHP_VERSION;
		// $http_header[] = 'Content-Length: ';

		/* if(!empty($addtl_headers))
		{
			$http_header = array_merge($http_header, $addtl_headers);
		} */

		curl_setopt($this->ch, CURLOPT_URL, $this->api_url . $this->endpoint);
		curl_setopt($this->ch, CURLOPT_HEADER, false);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_ENCODING, '');
		curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		// curl_setopt($this->ch, CURLOPT_VERBOSE, true);
		curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $request_type);
		// curl_setopt($this->ch, CURLOPT_POST, (strtoupper($request_type === 'POST') ? true : false));

		switch(true)
		{
			case(is_array($post_opts) && $request_type === 'POST') :
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($post_opts));
				break;

			case($request_type === 'POST') :
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_opts);
				break;

			default :
				break;
		}

		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $http_header);
	}

	/**
	 * Sends and processes the cURL request
	 *
	 * @return $response mixed JSON-formatted string or boolean indicating authorization succeeded
	 */
	protected function sendRequest()
	{
		$response = curl_exec($this->ch);
		$err      = curl_error($this->ch);

		if($err)
		{
			$err_response = '{"error": "cURL Error #:' . $err . '"}';

			return $this->formatResponse($err_response);
		}
		else
		{
			$response = $this->formatResponse($response);

			return $response;
		}
	}

	/**
	 * Formats the cURL response into arrays we can more easily parse
	 *
	 * @param $response The response from cURL
	 * @return $return The formatted response
	 */
	protected function formatResponse($response = '')
	{
		$header_exists = curl_getinfo($this->ch, CURLOPT_HEADER);
		$header_size   = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

		if($header_exists === true)
		{
			$resp_headers  = substr($response, 0, $header_size);
			$resp_headers  = explode("\r\n", trim($resp_headers));
			$response_body = substr($response, $header_size);
		}
		else
		{
			$response_body = $response;
		}

		/* foreach($resp_headers as $resp_header)
		{
			$tmp = explode(': ', $resp_header);

			if(isset($tmp[1]))
			{
				$response_headers[$tmp[0]] = $tmp[1];
			}
		} */

		/* $return['headers'] = $response_headers;
		$return['body']    = json_decode($response_body, false);
		$return['body']    = ($return['body'] === null) ? array('text' => $response_body) : $return['body']; */

		$return = json_decode($response_body, false);

		return $return;
	}

	/**
	 * PayPal REST APIs use OAuth 2.0 access tokens to authenticate requests. Your access token authorizes
	 * you to use the PayPal REST API server.
	 *
	 * @link https://developer.paypal.com/api/rest/authentication/
	 *
	 * @param $retry int
	 *
	 * @todo Possible caching of the token?
	 */
	function authorise(int $retry = 0)
	{
		$this->setEndpoint('/v1/oauth2/token');

		$auth_string = base64_encode($this->client_id . ':' . $this->client_secret);
		$header_opts = array();
		$post_opts   = array();

		$header_opts[] = 'Authorization: Basic ' . $auth_string;
		$header_opts[] = 'Content-Type: application/x-www-form-urlencoded';

		$post_opts['grant_type']                = 'client_credentials';
		$post_opts['ignoreCache']               = true;
		$post_opts['return_authn_schemes']      = true;
		$post_opts['return_client_metadata']    = true;
		$post_opts['return_unconsented_scopes'] = true;

		$this->setOptions($header_opts, $post_opts, 'POST');

		$send_auth = $this->sendRequest();

		if(isset($send_auth->access_token))
		{
			$this->token      = $send_auth->access_token;
			$this->token_type = $send_auth->token_type;

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * An order represents a payment between two or more parties. Use the Orders API to create, update,
	 * retrieve, authorize, and capture orders.
	 *
	 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_create
	 *
	 * @param $amount     array
	 * @param $refId      string
	 * @param $return_url string
	 * @param $cancel_url string
	 * @param $order
	 *
	 * @todo Possible caching of the token?
	 */
	function createOrder(array $inputvars, string $refId, string $return_url = '', string $cancel_url = '')
	{
		$this->setEndpoint('/v2/checkout/orders');

		$header_opts = array();
		$post_opts   = array();

		$header_opts[] = 'Authorization: Bearer ' . $this->token;
		$header_opts[] = 'Content-Type: application/json';
		$header_opts[] = 'Prefer: return=representation';
		// $header_opts[] = 'PayPal-Request-Id: ';
		// $header_opts[] = '';

		$post_opts['intent']                                                                  = 'CAPTURE';
		// payer is “deprecated” but using customer doesn't seem to pass our info to the address fields?
		$post_opts['payer']['name']['given_name']                                             = $inputvars['address']['first_name'];
		$post_opts['payer']['name']['surname']                                                = $inputvars['address']['last_name'];
		$post_opts['payer']['address']['address_line_1']                                      = $inputvars['address']['address_line_1'];
		$post_opts['payer']['address']['address_line_2']                                      = $inputvars['address']['address_line_2'];
		$post_opts['payer']['address']['admin_area_2']                                        = $inputvars['address']['admin_area_2'];
		$post_opts['payer']['address']['admin_area_1']                                        = $inputvars['address']['admin_area_1'];
		$post_opts['payer']['address']['postal_code']                                         = $inputvars['address']['postal_code'];
		$post_opts['payer']['address']['country_code']                                        = $inputvars['address']['country_code'];
		$post_opts['payer']['email_address']                                                  = $inputvars['address']['email_address'];
		$post_opts['payer']['phone']['phone_type']                                            = 'MOBILE';
		$post_opts['payer']['phone']['phone_number']['national_number']                       = preg_replace("/(\D)/", '', $inputvars['address']['phone_number']);
		$post_opts['purchase_units']                                                          = array();
		$post_opts['purchase_units'][0]['custom_id']                                          = filter_var($inputvars['custom_id'], FILTER_SANITIZE_SPECIAL_CHARS);
		$post_opts['purchase_units'][0]['invoice_id']                                         = filter_var($inputvars['invoice_id'], FILTER_SANITIZE_SPECIAL_CHARS);
		$post_opts['purchase_units'][0]['reference_id']                                       = filter_var($inputvars['reference_id'], FILTER_SANITIZE_SPECIAL_CHARS);
		$post_opts['purchase_units'][0]['amount']                                             = array();
		$post_opts['purchase_units'][0]['amount']['currency_code']                            = 'USD';
		$post_opts['purchase_units'][0]['amount']['value']                                    = round($inputvars['amount'], 2);
		$post_opts['purchase_units'][0]['amount']['breakdown']                                = array();
		$post_opts['purchase_units'][0]['amount']['breakdown']['item_total']                  = array();
		$post_opts['purchase_units'][0]['amount']['breakdown']['item_total']['currency_code'] = 'USD';
		$post_opts['purchase_units'][0]['amount']['breakdown']['item_total']['value']         = round($inputvars['amount'], 2);
		$post_opts['purchase_units'][0]['application_context']                                = array();
		$post_opts['purchase_units'][0]['application_context']['return_url']                  = $return_url;
		$post_opts['purchase_units'][0]['application_context']['cancel_url']                  = $cancel_url;

		$this->setOptions($header_opts, json_encode($post_opts), 'POST');

		$order = $this->sendRequest();

		return $order;
	}

	/**
	 * An order represents a payment between two or more parties. Use the Orders API to create, update,
	 * retrieve, authorize, and capture orders.
	 *
	 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_capture
	 *
	 * @param $inputvars  array
	 * @param $return_url string
	 * @param $cancel_url string
	 * @param $order
	 *
	 * @todo Possible caching of the token?
	 */
	function capturePayment(array $inputvars)
	{
		$orderID = preg_replace("/\W/", '', $inputvars['orderID']);

		$this->setEndpoint('/v2/checkout/orders/' . $orderID . '/capture');

		$header_opts = array();
		$post_opts   = array();

		$header_opts[] = 'Authorization: Bearer ' . $this->token;
		$header_opts[] = 'Content-Type: application/json';
		$header_opts[] = 'Prefer: return=representation';
		// $header_opts[] = 'PayPal-Request-Id: ';
		// $header_opts[] = '';

		// $post_opts['orderID']       = $orderID;
		$post_opts['paymentSource'] = $inputvars['paymentSource'];

		$this->setOptions($header_opts, json_encode($post_opts), 'POST');

		$order = $this->sendRequest();

		return $order;
	}
}
