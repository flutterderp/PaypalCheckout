<?php
ini_set('display_errors', 'on');

require_once(__DIR__ . '/PaypalCheckout.php');

/* if(!isset($_SERVER['HTTP_HOST']) || empty($__SERVER['HTTP_HOST']))
{
	$_SERVER['HTTP_HOST'] = $_ENV['HOSTNAME'];
} */

// Create environment
$pp          = new PaypalCheckout(true);
$header_opts = array();
$authorized  = $pp->authorise();

if($authorized === true)
{
	$phpinput = file_get_contents('php://input');
	$postvars = json_decode($phpinput, true);

	$capture = $pp->capturePayment($postvars);

	/* if(isset($capture->debug_id)) : ?>
		<p>
			<b>Name:</b> <?php echo $capture->name; ?><br>
			<b>Message:</b> <?php echo $capture->message; ?><br>
			<b>Debug ID:</b> <?php echo $capture->debug_id; ?><br>
		</p>

		<?php foreach($capture->details as $detail) : ?>
			<p>
			<?php if(isset($detail->field)) : ?>
				<b>Field:</b> <?php echo $detail->field; ?><br>
			<?php endif; ?>

			<?php if(isset($detail->location)) : ?>
				<b>Location:</b> <?php echo $detail->location; ?><br>
			<?php endif; ?>

			<b>Issue:</b> <?php echo $detail->issue; ?><br>
			<b>Description:</b> <?php echo $detail->description; ?>
			</p>
		<?php endforeach; ?>
	<?php endif; */

	if(isset($capture->status) && $capture->status === 'COMPLETED')
	{
		// Process the payment
		/**
		 * $capture->id
		 * $capture->status
		 * $capture->purchase_units[$i]->payments->captures
		 */
		$order_info = array();

		foreach($capture->purchase_units as $i => $unit)
		{
			array_walk($unit->payments->captures, function($v, $k) use (&$order_info, $i)
			{
				if($v->final_capture === true) {
					$order_info['unit' . $i]['txn_id']       = $v->id;
					$order_info['unit' . $i]['gross_amount'] = $v->seller_receivable_breakdown->gross_amount->value;
					$order_info['unit' . $i]['paypal_fee']   = $v->seller_receivable_breakdown->paypal_fee->value;
					$order_info['unit' . $i]['net_amount']   = $v->seller_receivable_breakdown->net_amount->value;
				}
			});
		}

		// error_log(json_encode($order_info) . PHP_EOL, 3, __DIR__ . '/debug.log');
	}

	echo json_encode($capture);
	// print_r($capture);
}
