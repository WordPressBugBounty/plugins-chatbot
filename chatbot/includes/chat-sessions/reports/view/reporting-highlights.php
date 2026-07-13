<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">

<link href="<?php echo esc_url( QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/reports/view/assets/style.css' ); ?>" rel="stylesheet">

<main id="main" class="main clearfix">

	<div class="pagetitle">
		<h1>Bot - Reporting Dashboard</h1>
	</div><!-- End Page Title -->

	<section class="section dashboard">
		<div class="row">

		<!-- Left side columns -->
		<div class="col-lg-12">
			<div class="row">

			<!-- Card 1 -->
			<div class="col-xxl-4 col-md-6">
				<div class="card info-card sales-card">

				<div class="card-body">
					<h5 class="card-title">Total Conversations</span></h5>

					<div class="d-flex align-items-center">
					<div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
						<i class="bi bi-wechat"></i>
					</div>
					<div class="ps-3">
						<h6>
						<?php echo intval( botreports_get_total_conversation_count() ); ?>
						</h6>
						<span class="text-success small pt-1 fw-bold">
						<?php echo intval( botreports_get_todays_conversation_count() ); ?>
						</span> 
						<span class="text-muted small pt-2 ps-1">Today</span>,
						<span class="text-success small pt-1 fw-bold">
						<?php echo intval( botreports_get_weeks_conversation_count() ); ?>
						</span> 
						<span class="text-muted small pt-2 ps-1">Last Week</span>

					</div>
					</div>
				</div>

				</div>
			</div><!-- End Card 1 -->
			<!-- Card 1 -->
<div class="col-xxl-4 col-md-6">
    <div class="card info-card sales-card feedback-card" data-type="like" style="cursor:pointer;">
        <div class="card-body">
            <h5 class="card-title">Positive Feedback</h5>
            <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-hand-thumbs-up"></i>
                </div>
                <div class="ps-3">
                    <h6><?php echo intval( wpbot_get_report_stats_count()['likes'] ); ?></h6>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-xxl-4 col-md-6">
    <div class="card info-card sales-card feedback-card" data-type="dislike" style="cursor:pointer;">
        <div class="card-body">
            <h5 class="card-title">Negative Feedback</h5>
            <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-hand-thumbs-down"></i>
                </div>
                <div class="ps-3">
                    <h6><?php echo intval( wpbot_get_report_stats_count()['dislikes'] ); ?></h6>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="feedback-results" style="margin-top:30px;"></div>



			<!-- Card 2 -->
			<div class="col-xxl-4 col-md-6">
				<div class="card info-card sales-card">

				<div class="card-body">
					<h5 class="card-title">Conversations in Last 30 Days</span></h5>

					<div class="d-flex align-items-center">
					<div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
						<i class="bi bi-bar-chart-steps"></i>
					</div>
					<div class="ps-3">
						<h6>
						<?php echo intval( botreports_get_last30days_conversation_count() ); ?>
						</h6>
					</div>
					</div>
				</div>

				</div>
			</div><!-- End Card 2 -->

			<!-- Card 3 -->
			<div class="col-xxl-4 col-md-6">
				<div class="card info-card sales-card">

				<div class="card-body">
					<h5 class="card-title">Average Daily Conversation</span></h5>

					<div class="d-flex align-items-center">
					<div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
						<i class="bi bi-bar-chart"></i>
					</div>
					<div class="ps-3">
						<h6><?php echo esc_html( botreports_get_last30days_conversation_average() ); ?></h6>
						<span class="text-muted small">In</span>
						<span class="text-success small pt-1 fw-bold">Last 30</span> 
						<span class="text-muted small pt-2 ps-1">Days</span>
					</div>
					</div>
				</div>

				</div>
			</div><!-- End Card 3 -->

			<?php
			echo '<pre>';
			botreports_get_last30days_conversation_density();
			echo '</pre>';
			?>

			<!-- Reports: Conversation Density Chart -->
			<div class="col-lg-12">
				<div class="card" style="max-width: 100% !important;">
					<div class="card-body">
					<h5 class="card-title">Conversation Density Chart</h5>

					<!-- Area Chart -->
					<div id="areaChart" style="min-height: 365px;">
						<div id="apexchartsa0c3a8xu" class="apexcharts-canvas apexchartsa0c3a8xu apexcharts-theme-light">

						</div>
						<div class="apexcharts-menu">
							<div class="apexcharts-menu-item exportSVG" title="Download SVG">Download SVG</div>
							<div class="apexcharts-menu-item exportPNG" title="Download PNG">Download PNG</div>
							<div class="apexcharts-menu-item exportCSV" title="Download CSV">Download CSV</div>
						</div>
					</div>

					<?php

						$densityTable = botreports_get_last30days_conversation_density();

						$dates                      = array();
						$conversation_count_in_date = array();

					foreach ( $densityTable as $data ) {
						array_push( $dates, $data->CONVERSATION_DATE );
						array_push( $conversation_count_in_date, $data->CONVERSATION_NUM );
					}

					?>

					<script>
						document.addEventListener("DOMContentLoaded", () => {
						const series = {
							"monthDataSeries1": {
							"count": <?php echo json_encode( $conversation_count_in_date ); ?>,
							"dates": <?php echo json_encode( $dates ); ?>,
							},
						}
						new ApexCharts(document.querySelector("#areaChart"), {
							series: [{
								name: "Conversation Count: ",
								data: series.monthDataSeries1.count
							}],
							chart: {
								type: 'bar',
								height: 350,
								zoom: {
									enabled: false
								}
							},
							plotOptions: {
								bar: {
								horizontal: false
								}
							},
							dataLabels: {
								enabled: true
							},
							labels: series.monthDataSeries1.dates,
							xaxis: {
								type: 'datetime',
							},
							yaxis: {
								opposite: true
							},
							legend: {
								horizontalAlign: 'left'
							}
						}).render();
						});
					</script>
					<!-- End Area Chart -->

					</div>
				</div>
			</div><!-- End Reports: Conversation Density Chart -->

			<!-- Recent Conversations -->
			<div class="col-lg-12 clearfix">
				<div class="card" style="max-width: 100% !important;">

				<div class="card-body">
					<h5 class="card-title">Recent 5 Conversations</span></h5>

					<?php

						$results = botreports_get_last5_conversations();

						$numberOfRecords = count( $results );

					if ( $numberOfRecords > 0 ) :

						?>

					<table class="table table table-hover">
					<thead>
						<tr class="first-row text-center table-primary">
						<th scope="col">#</th>
						<th scope="col">Date & Time</th>
						<th scope="col">Session ID</th>
						<th scope="col">User Interaction Count</th>
						<th scope="col">Action</th>
						</tr>
					</thead>
					<tbody>

						<?php

						$counter = count( $results ) === 5 ? 5 : count( $results );

						foreach ( $results as $result ) {

							?>

						<tr class="text-center">
							<th scope="row">
							<a href="<?php echo esc_url( admin_url( "admin.php?page=wbcs-botsessions-page&userid={$result->user_id}" ) ); ?>">
								<?php echo esc_html( $counter ); ?>
							</a>
							</th>
							<td><?php echo esc_html( date( 'd M, Y g:i A', strtotime( $result->date ) ) ); ?></td>
							<td><?php echo esc_html( $result->session_id ); ?></td>
							<td><?php echo intval( get_user_interaction_count( $result->conversation ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( "admin.php?page=wbcs-botsessions-page&userid={$result->user_id}" ) ); ?>" class="btn btn-primary">
									<i class="bi bi-eyeglasses"></i> View Chat
								</a>
							</td>
						</tr>

							<?php

							--$counter;

						}

						?>


					</tbody>

					</table>

					<?php else : ?>

					<div class="alert alert-info">No chat records were found in the database.</div>

					<?php endif; ?>

				</div>

				</div>
			</div><!-- End Recent Conversations -->
			<!-- Reports Conversations -->
			<div class="col-lg-12 clearfix">
				<div class="card" style="max-width: 100% !important;">

				<div class="card-body">
					<h5 class="card-title">Conversations Reports</span></h5>

					<?php

						$reports_count = wpbot_get_report_stats_count()['reports_only'];

						

					if ( $reports_count > 0 ) :

						?>

					<table class="table table table-hover">
					<thead>
						<tr class="first-row text-center table-primary">
						<th scope="col">#</th>
						<th scope="col">Id</th>
						<th scope="col">Email</th>
						<th scope="col">Message</th>
						<th scope="col">Report Text</th>
						<th scope="col">Date</th>
						</tr>
					</thead>
					<tbody>

						<?php
						$rests = wpbot_get_reports_list();
						$counter = count( $rests ) === 5 ? 5 : count( $rests );
						
						foreach ( $rests as $report ) {
						$meta = maybe_unserialize( $report['meta_info'] );
						$email = isset($meta['email']) ? $meta['email'] : '';
						$report_text = isset($meta['report_text']) ? $meta['report_text'] : '';
							?>

						<tr class="text-center">
							<th scope="row">
							<a href="<?php echo esc_url( admin_url( "admin.php?page=wbcs-botsessions-page&userid={$result->user_id}" ) ); ?>">
								<?php echo esc_html( $counter ); ?>
							</a>
							</th>
							<td><?php echo esc_html( $report['id'] ); ?></td>
							<td><?php echo esc_html( $email ); ?></td>
							<td><?php echo esc_html( $report['message'] ); ?></td>
							<td><?php echo esc_html( $report_text ); ?></td>
							<td><?php echo esc_html( $report['created_at'] ); ?></td>
						</tr>

							<?php

							--$counter;

						}

						?>


					</tbody>

					</table>

					<?php else : ?>

					<div class="alert alert-info">No chat records were found in the database.</div>

					<?php endif; ?>

				</div>

				</div>
			</div><!-- End Reports Conversations -->

			</div>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page' ) ); ?>" class="btn btn-primary">
				<i class="bi bi-gear-wide-connected me-1"></i> Manage All Conversations
			</a>

			<button id="wpbot-clear-feedback" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Clear All Feedback</button>
            <div id="wpbot-clear-feedback-msg"></div>

		</div><!-- End Left side columns -->

		</div>
	</section>

	</main><!-- End #main -->

	<script src="<?php echo esc_url( QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/reports/view/assets/apexcharts.min.js' ); ?>"></script>
	<script>
		jQuery(document).ready(function($) {
    $(".feedback-card").on("click", function() {
        let type = $(this).data("type");
        $("#feedback-results").html("<p>Loading...</p>");

        $.post(ajaxurl, {
            action: "wpbot_get_feedback_ajax",
            feedback_type: type
        }, function(response) {
            if (response.success) {
                $("#feedback-results").html(response.data.html);
            } else {
                $("#feedback-results").html("<p>No results found.</p>");
            }
        });
    });
});
jQuery(document).on("click", ".wpbot-page-link", function(e) {
    e.preventDefault();

    let page = jQuery(this).data("page");
    let type = jQuery(this).data("type");

    jQuery.post(ajaxurl, {
        action: "wpbot_get_feedback_ajax",
        feedback_type: type,
        page: page
    }, function(response) {
        if (response.success) {
            jQuery("#feedback-results").html(response.data.html);
        }
    });
});
jQuery(document).on("click", "#wpbot-clear-feedback", function (e) {
    console.log('kardi');
  e.preventDefault();

  if (!confirm("Are you sure you want to delete all feedback?")) {
    return;
  }

  jQuery.post(ajaxurl, {
    action: "wpbot_clear_all_feedback"
  }, function (response) {
    if (response.success) {
      jQuery("#wpbot-clear-feedback-msg").text(response.data.message).css("color", "green");
      // Optionally reload counters
      location.reload();
    } else {
      jQuery("#wpbot-clear-feedback-msg").text(response.data.message).css("color", "red");
    }
  });
});

</script>