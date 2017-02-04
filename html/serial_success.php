<?php
	echo '<img src="' . plugins_url( 'html/assets/img/right.svg', dirname(__FILE__) ) . '" > ';
?>
<h1>Your helmet is certified</h1>
<ul>
	<li><strong>Serial :</strong> <?php echo $serial->serial; ?></li>
	<li><strong>Model :</strong> <?php echo $serial->getProductData('model'); ?></li>
	<li><strong>Customer :</strong> <?php echo $serial->getCustomerData('name'); ?></li>
</ul>