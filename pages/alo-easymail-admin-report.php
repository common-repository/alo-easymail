<?php
/**
 * Newsletter report in dashboard
 *
 * @package WordPress
 * @subpackage ALO EasyMail plugin
 */


if ( !current_user_can( "edit_newsletters" ) ) 	wp_die( __('Cheatin&#8217; uh?') );

check_admin_referer('alo-easymail_report');
global $wpdb;

/*
 * Checks Required vars
 */
if ( isset( $_REQUEST['newsletter'] ) ) {
	$newsletter = (int)$_REQUEST['newsletter'];
	if ( get_post_type( $newsletter ) != "newsletter" ) wp_die( __('The required newsletter does not exist', "alo-easymail") ); 
	if ( !get_post( $newsletter ) ) wp_die( __('The required newsletter does not exist', "alo-easymail") );
	if ( !alo_em_user_can_edit_newsletter( $newsletter ) ) wp_die( __('Cheatin&#8217; uh?') );
	$offset =  ( isset( $_REQUEST['offset'] ) && is_numeric( $_REQUEST['offset'] ) ) ? (int)$_REQUEST['offset'] : 0;
} else {
	wp_die(__('Cheatin&#8217; uh?') );
}


if ( $newsletter ) {

    // Lang
    $lang = ( isset($_REQUEST['lang'])) ? $_REQUEST['lang'] : false;

	$newsletter_post = alo_em_get_newsletter( $newsletter );
	
	$per_page = apply_filters ( 'alo_easymail_report_recipients_per_page', 250, $newsletter );

	if ( ! $newsletter_post ) {
		wp_die( "The requested page doesn't exists." );
	} else {

		$report_url = admin_url( 'admin.php?page=alo-easymail-admin-report');
		$report_url = add_query_arg( array(
			'newsletter' => $newsletter,
			'lang'       => $lang,
		), $report_url );
		$report_url = wp_nonce_url( $report_url, 'alo-easymail_report' );
		?>

        <h1 class="wp-heading-inline">
            <?php _e("Newsletter report", "alo-easymail") ?>:
            "<?php echo get_the_title( $newsletter ) ?>"
        </h1>

		<div class="easymail_report wrap">

            <!-- Newsletter's general details -->
            <table id="par-1" class="alo-easymail-header-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e("Subject", "alo-easymail");  ?></th>
                        <td><?php echo get_the_title( $newsletter ) ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e("Scheduled by", "alo-easymail") ?></th>
                        <td><?php echo get_user_meta( $newsletter_post->post_author, 'nickname', true ) ?></td>
                    </tr>
					<tr>
						<th scope="row" style="vertical-align: top"><ul style="list-style: none;"><li><?php _e("Recipients", "alo-easymail") ?></li></ul></th>
						<td><?php
							$recipients = alo_em_get_recipients_from_meta( $newsletter );
							echo alo_em_recipients_short_summary ( $recipients, false );
						?></td>
					</tr>
                    <tr>
                        <th scope="row"><?php _e("Start", "alo-easymail") ?></th>
                        <td><?php echo date_i18n( __( 'j M Y @ G:i', "alo-easymail" ), strtotime( $newsletter_post->post_date ) ) ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e("Completed", "alo-easymail") ?></th>
                        <td><?php
	                        $end = get_post_meta ( $newsletter, "_easymail_completed", current_time( 'mysql', 0 ) );
	                        echo ( $end ) ? date_i18n( __( 'j M Y @ G:i', "alo-easymail" ), strtotime( $end ) ) : __("No", "alo-easymail" );
                        ?></td>
                    </tr>
                    <tr class="hide-on-print">
                        <th scope="row" style="vertical-align: top"><?php _e("Main body", "alo-easymail") ?> (<?php _e("without formatting", "alo-easymail") ?>)</th>
                        <td><div id="mailbody"><?php echo nl2br( strip_tags( alo_em_translate_text ( $lang, $newsletter_post->post_content ), "<img>") );?></div></td>
                    </tr>
                </tbody>
            </table>

			<!-- Newsletter's recipients list -->
			<div id="par-2">
			
				<?php
				// If archived
				if ( $archived_raw = alo_em_is_newsletter_recipients_archived ( $newsletter ) ) {
					$archived_meta = $archived_raw[0];
					$tot_recipients 	= $archived_meta['tot'];
					$already_sent 		= $archived_meta['sent'];
					$sent_with_success 	= $archived_meta['success'];
					$sent_with_error 	= $archived_meta['error'];
					$unique_views 		= $archived_meta['uniqview'];
					$unique_clicks 		= $archived_meta['uniqclick'];

				// If regular, not archived
				} else {
					// List of recipients, paged
					$recipients = alo_em_get_newsletter_recipients( $newsletter, false, $offset, $per_page ); 
				
					// Total number of recipients
					$tot_recipients = alo_em_count_newsletter_recipients ( $newsletter );
				
					// Other info
					$already_sent = alo_em_count_newsletter_recipients_already_sent ( $newsletter );
					$sent_with_success = alo_em_count_newsletter_recipients_already_sent_with_success( $newsletter );
					$sent_with_error = alo_em_count_newsletter_recipients_already_sent_with_error( $newsletter );
					$unique_views = count( alo_em_all_newsletter_trackings ( $newsletter, '' ) );
					$unique_clicks = count ( alo_em_all_newsletter_trackings_except_views ( $newsletter ) );
				}
				?>		
			
				<?php // Archive (delete) detailed info of recipients
				if ( isset($_GET['archive']) && alo_em_get_newsletter_status( $newsletter ) == "sent" ) :
					$archived_recipients = array( 'tot' => $tot_recipients, 'sent' => $already_sent, 'success' => $sent_with_success, 'error' => $sent_with_error, 'uniqview' => $unique_views, 'uniqclick' => $unique_clicks );
					add_post_meta ( $newsletter, "_easymail_archived_recipients", $archived_recipients );
					alo_em_delete_newsletter_recipients ( $newsletter );
					echo "<div class=\"easymail-alert\">". __("Detailed report was archived", "alo-easymail") ."</div>\n";
				endif; ?>	
			
				<table class="summary">
					<thead><tr>
						<th scope="col"><?php _e("Total recipients", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Sendings done", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Sendings succesful", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Sendings failed", "alo-easymail") ?></th>
						<th scope="col"><?php 
							echo __("Unique views", "alo-easymail") . " "; 
							echo alo_em_help_tooltip( 
								__("The plugin tries to count how many recipients open the newsletter", "alo-easymail"). ". "
								. __("The number includes max a view per recipient", "alo-easymail"). ". "
							);
						?></th>						
						<th scope="col"><?php 
							echo __("Clicks", "alo-easymail") . " "; 
							echo alo_em_help_tooltip( 
								__("The number includes max a view per recipient", "alo-easymail"). ". "
							);						
						?></th>
					</tr></thead>
				<tbody>
					<tr>
						<td class="tot center" style="width:20%"><?php echo $tot_recipients; ?></td>
						<td class="done center" style="width:20%"><?php echo $already_sent ?></td>
						<td class="success center" style="width:15%"><?php echo $sent_with_success ?></td>
						<td class="error center" style="width:15%"><?php echo $sent_with_error  ?>	</td>
						<td class="views center" style="width:15%"><?php echo $unique_views  ?></td>		
						<td class="success center" style="width:15%"><?php echo $unique_clicks ?><?php
						if ( $unique_clicks >0 ) : ?>
                            <a class="hide-on-print" href="<?php echo add_query_arg( 'show_clicked', '1', $report_url ); ?>" title="<?php esc_attr_e(__("click to view list of clicked links", "alo-easymail")) ?>">
                                <img src="<?php echo ALO_EM_PLUGIN_URL ?>/images/16-arrow-right.png" />
                            </a>
                        <?php endif; ?>
						</td>
					</tr>
					<tr style="font-size: 50%">
						<td class="tot center">100%</td>
						<td class="done center"><?php echo alo_em_rate_on_total($already_sent, $tot_recipients); ?>%</td>
						<td class="success center"><?php echo alo_em_rate_on_total($sent_with_success, $tot_recipients); ?>%</td>
						<td class="error center"><?php echo alo_em_rate_on_total($sent_with_error, $tot_recipients);  ?>%</td>
						<td class="views center"><?php echo alo_em_rate_on_total($unique_views, $tot_recipients);  ?>%</td>		
						<td class="success center"><?php echo alo_em_rate_on_total($unique_clicks, $tot_recipients); ?>%</td>
					</tr>
				</tbody>
				</table>
			
			<?php // Archive button
			if ( !isset($_GET['isnewwin']) ) { 
				if ( alo_em_is_newsletter_recipients_archived ( $newsletter ) ) {
					if ( !isset($_GET['archive']) ) echo "<div class=\"easymail-alert\">". __("Detailed report was archived", "alo-easymail") ."</div>\n";
				} else if ( alo_em_get_newsletter_status( $newsletter ) == "sent" ) { ?>
				<div id="par-3" class="hide-on-print">
					<a href="<?php echo add_query_arg( 'archive', '1', $report_url ) ?>" class="easymail-navbutton button-archive" onclick='javascript:if( confirm("<?php echo esc_js( __("Are you sure?", "alo-easymail")." " .__("You are about to DELETE the detailed info about recipients", "alo-easymail").". " . __("This action cannot be undone", "alo-easymail") ) ?>") == false ) return false;' title="<?php esc_attr_e(__("You are about to DELETE the detailed info about recipients", "alo-easymail")) ?>">
					<?php _e("Delete the detailed report of recipients", "alo-easymail") ?></a> 
					<?php echo alo_em_help_tooltip( __("You are about to DELETE the detailed info about recipients", "alo-easymail").". " .__("This action deletes the detailed info about recipients (see below) and keeps only the summary (see above)", "alo-easymail"). ". " .__("It reduces the data in database tables and make the plugin queries and actions faster", "alo-easymail"). ". " ); ?>
				</div>
			<?php } // if ( get_post_meta 
			} // if ( !isset($_GET['isnewwin']) )  ?>		

<?php
// Table with clicked links
if ( isset($_GET['show_clicked']) ) : ?>

<a href="<?php echo $report_url; // echo wp_nonce_url(ALO_EM_PLUGIN_URL . '/pages/alo-easymail-admin-report.php?newsletter='.$newsletter.'&lang='.$lang, 'alo-easymail_report'); ?>" class="easymail-navbutton" style="margin-top:15px;display: inline-block;">&laquo; <?php _e("Back to list of recipients", "alo-easymail") ?></a>

		<table class="recipient-summary">
			<thead>
			<tr>
				<th scope="col"><?php _e("Requested URL", "alo-easymail") ?></th>
				<th scope="col"><?php _e("Visits", "alo-easymail") ?></th>
			</tr>
		</thead>

		<tbody>
		<?php
		// Get all clicked url, grouped by visits
		$urls = $wpdb->get_results ( $wpdb->prepare( "SELECT request, COUNT(*) as num_visits FROM {$wpdb->prefix}easymail_stats WHERE newsletter=%d AND request!='' GROUP BY request ORDER BY num_visits DESC", $newsletter ) );
		//echo "<pre>"; print_r($urls);echo "</pre>";

		if ( $urls ) {
			$class = "";
			$n = 0;
			foreach ( $urls as $url  ) {
				$class = ('' == $class) ? "style='background-color:#eee;'" : "";
				$n ++;
				echo "<tr $class ><td><a href=\"".$url->request."\" target=\"_blank\" title=\"". esc_attr( sprintf( __( 'Visit %s' ), esc_url($url->request) ) )."\">" . $url->request ."</a></td>";
				echo "<td><strong>" . $url->num_visits ."</strong></td>";			 
				echo "</tr>";
			}
		}
		?>
	</tbody></table>


<?php
// Table with recipients
elseif ( !alo_em_is_newsletter_recipients_archived ( $newsletter ) ) : 	?>			
				<?php 
				$tot_pages = @ceil( $tot_recipients / $per_page ); 
				if ( $tot_pages > 1 ) : ?>
				<!-- Pagination -->	
				<ul id="easymail_report_tabs" class="ui-tabs-nav">
					<?php for( $i=0; $i < $tot_pages; $i++ ) : 
						$to_offset = ( $i * $per_page ); 
						$active = ( $offset == $to_offset ) ? "ui-tabs-selected ui-state-active" : "";
						$atitle = __("Recipients", "alo-easymail").": ". ($to_offset+1) ." - ". ( ( $i < $tot_pages-1 ) ? $to_offset + $per_page : $tot_recipients ); ?>		
						<li class="ui-state-default ui-corner-top <?php echo $active ?>">
                            <a href="<?php echo add_query_arg( 'offset', $to_offset, $report_url ) ?>" title="<?php echo $atitle ?>">
                                <?php echo $to_offset+1 ?>
                            </a>
                        </li>
					<?php endfor; ?>
				</ul>
				<?php endif; // if ( $tot_pages > 1 ) ?>
				
				<table class="recipient-summary">
					<thead>
					<tr>
						<th scope="col"></th>
						<th scope="col"><?php _e("E-mail", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Name", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Language", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Sent", "alo-easymail") ?></th>
						<th scope="col"><?php _e("Viewed", "alo-easymail") ?></th>						
						<th scope="col"><?php _e("Clicks", "alo-easymail") ?></th>
					</tr>
				</thead>

				<tbody>
				<?php
				$class = "";
				$n = $offset;
				foreach ($recipients as $recipient) {
					$class = ('' == $class) ? "style='background-color:#eee;'" : "";
					$n ++;
					echo "<tr $class ><td>".$n."</td><td>".$recipient->email."</td><td>".$recipient->name."</td>";
					echo "<td class='center'>";
					if ( isset( $recipient->lang ) ) echo alo_em_get_lang_flag( $recipient->lang, 'name' ) ;
					echo "</td>";

					echo "<td class='center'>".( ( $recipient->result == "1" ) ? __("Yes", "alo-easymail" ) : __("No", "alo-easymail" ) )." <img src='".ALO_EM_PLUGIN_URL."/images/".( ( $recipient->result == "1" ) ? "yes.png":"no.png" ) ."' alt='". ( ( $recipient->result == "1" ) ? __("Yes", "alo-easymail" ) : __("No", "alo-easymail" ) ) ."' />";
					if ( $recipient->result == "-3" ) echo " <img src='".ALO_EM_PLUGIN_URL."/images/16-email-bounce.png' alt='". esc_attr( __("Bounced", "alo-easymail" ) ) ."' title='". esc_attr( __("Bounced", "alo-easymail" ) .': '. __("the message was rejected by recipient mail server", "alo-easymail" ) ) ."' />";
					echo "</td>";
					
					echo "<td class='center'>";
					echo ( ( $recipient->result == "1" && alo_em_recipient_is_tracked ( $recipient->ID, '' ) ) ? __("Yes", "alo-easymail" ) : __("No", "alo-easymail" ) )." <img src='".ALO_EM_PLUGIN_URL."/images/".( ( $recipient->result == "1" && alo_em_recipient_is_tracked ( $recipient->ID, '' ) )? "yes.png":"no.png" ) ."' />";
					if ( count( alo_em_get_recipient_trackings( $recipient->ID, '' ) ) > 1 ) echo " ". count( alo_em_get_recipient_trackings( $recipient->ID, '' ) );
					echo "</td>";

					echo "<td class='center'>";
					$clicks = alo_em_get_recipient_trackings_except_views( $recipient->ID );
					echo ( ( $recipient->result == "1" && $clicks ) ? __("Yes", "alo-easymail" ) : __("No", "alo-easymail" ) )." <img src='".ALO_EM_PLUGIN_URL."/images/".( ( $recipient->result == "1" && alo_em_get_recipient_trackings_except_views ( $recipient->ID) )? "yes.png":"no.png" ) ."' />";
					if ( is_array( $clicks ) && !empty($clicks) ) {
						//echo " ". count( $clicks ).': ';
						$unique_links = array();
						foreach( $clicks as $i => $click ) {
							if ( !isset( $unique_links[ $click->request ] ) ) {
								$unique_links[ $click->request ] = 0;
							}
							$unique_links[ $click->request ] ++;
						}
						foreach( $unique_links as $link => $n_clicks ) {
							echo $n_clicks.'<small>x</small><span class="clicked-links dashicons dashicons-admin-links" title="'.esc_url($link).'"></span>';
						}
					}
					echo "</td>";
					 
					echo "</tr>";
					//echo "<pre>"; print_r($clicks);echo "</pre>";
				}
				?>
			</tbody></table>

<?php endif; // if ( !alo_em_is_newsletter_recipients_archived ( $newsletter ) ) : ?>	
			
			</div>
			
		</div> <!-- end slider -->

	<?php } // end if $newsletter
} // edn if (isset($_REQUEST['id']) && (int)$_REQUEST['id'])
exit;