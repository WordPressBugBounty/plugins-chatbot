<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">

<link href="<?php echo esc_url( QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/reports/view/assets/style.css' ); ?>" rel="stylesheet">

<?php
if ( ! empty( $result ) ) :
	$deleteurl = admin_url( 'admin.php?page=wbcs-botsessions-notansweredpage&action=deleteall' );
	?>

<br>

<a href="<?php echo esc_url( $deleteurl ); ?>" class="btn btn-primary" >
	<i class="bi bi-trash me-1"></i> <?php echo esc_html( 'Delete All Records' ); ?>
</a>

<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page' ) ); ?>" class="btn btn-secondary">
	<i class="bi bi-gear-wide-connected me-1"></i> Conversation List
</a>

<div class="qchero_slider_table_area qcld-session-yes-record-found">

	<table class="table table-bordered">
		<thead>
			<tr class="table-primary">
				<th class="text-center">
					<?php echo esc_html__( 'User\'s Query', 'chatbot' ); ?>
				</th>
				<th class="text-center">
					<?php echo esc_html__( 'Count', 'chatbot' ); ?>
				</th>
				<th class="text-center">
					<?php echo esc_html__( 'Action', 'chatbot' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>

		<?php
		foreach ( $result as $row ) :

			$delurl = admin_url( 'admin.php?page=wbcs-botsessions-notansweredpage&id=' . $row->id . '&act=delete' );
			?>

			<tr>
				<td class="text-left"><?php echo esc_html( $row->query ); ?></td>
				<td class="text-center"><?php echo esc_html( $row->count ); ?></td>
				<td class="text-center">
					<a href="<?php echo esc_html( $delurl ); ?>" class="btn btn-danger" onclick="return confirm('are you sure?')">
						<i class="bi bi-trash me-1"></i> <?php echo esc_html( 'Delete' ); ?>
					</a>
				</td>
			</tr>

		<?php endforeach; ?>

		</tbody>
	</table>

</div>

<?php else : ?>
	<div class="qcld-session-no-record-found" style="text-align: center;background: #fff;margin-top: 10px;padding: 20px;font-size: 20px;">No record Found!</div>
<?php endif; ?>