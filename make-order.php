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

	$order = $pp->createOrder($postvars, '', 'https://example.com/return', 'https://example.com/cancel');

	/* if(isset($order->debug_id)) : ?>
		<p>
			<b>Name:</b> <?php echo $order->name; ?><br>
			<b>Message:</b> <?php echo $order->message; ?><br>
			<b>Debug ID:</b> <?php echo $order->debug_id; ?><br>
		</p>

		<?php foreach($order->details as $detail) : ?>
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

	echo json_encode($order);
	// print_r($order);
}
