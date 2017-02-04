<?php
	echo '<img src="' . plugins_url( 'html/assets/img/wrong.svg', dirname(__FILE__) ) . '" > ';
?>

<h1>Error</h1>

<?php if (isset($contact_url)): ?>
<p>It seems that your helmet serial number has been forged, please <a href="<?php echo $contact_url; ?>">contact us</a> for more information.</p>
<?php else: ?>
<p>It seems that your helmet serial number has been forged, please contact us for more information.</p>
<?php endif; ?>

<?php if (isset($contact_email)): ?>
<p><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a></p>
<?php endif; ?>