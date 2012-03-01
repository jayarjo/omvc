<?php include(App::$conf['dir']['views'] . "/header.php" ); ?>

<?php if ($items) : 
	$schema = $this->{App::$ctrl}->table_schema;
?>
<table class="table table-striped table-bordered table-condensed">
<thead>
	<tr>
	<?php foreach ($items[0] as $key => $value) { 
		if (!isset($schema[$key])) continue; ?>
		<th><?php echo !empty($schema[$key]['Comment']) ? $schema[$key]['Comment'] : $key; ?></th>
	<?php } ?>
	</tr>
</thead>
<tbody>
<?php foreach ($items as $item) : ?>
	<tr>
	<?php foreach ($item as $key => $value) { ?>
		<td><?php echo $value; ?></td>
	<?php } ?>
	</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<?php include(App::$conf['dir']['views'] . "/footer.php" ); ?>