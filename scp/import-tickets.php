<?php
/*************************************************************************
    import-ticket.php

    Handles all tickets related actions.

    Siyaram Malav <info@cnelindia.com>
    Copyright (c)  2006-2013 osTicket
    http://www.hirewordpressexperts.com
**********************************************************************/


require('staff.inc.php');

$redirect = false;

if ($redirect) {
    if ($msg)
        Messages::success($msg);
    Http::redirect($redirect);
}

//Navigation
$nav->setTabActive('tickets');


//ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

$conn = mysqli_connect("localhost:3306","user","Pakistan/123!","computer_os");

if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  exit();
}
date_default_timezone_set("America/New_York");

function hwe_get_form_field($form_id){
	global $conn;

	if(!empty($form_id)){
		$form_fields_query = mysqli_query($conn, "SELECT id, label, type FROM ost_form_field WHERE form_id = '$form_id'");



		if( mysqli_num_rows($form_fields_query) > 0 ){
			$fields = array();
			while($row = mysqli_fetch_assoc($form_fields_query)){
				$field_id = $row['id'];
				$field_label = $row['label'];
				$field_type = $row['type'];

				$fields[$field_id] =  array('label' => $field_label, 'type' => $field_type);
			}

			return $fields;
		}
		else{
			return false;
		}

		return $fields;
	}
	else{
		 return false;
	}
}


function hwe_create_form_entry($form_id, $object_id, $object_type, $sort, $extra){
	global $conn;

	mysqli_query($conn, "INSERT INTO ost_form_entry SET form_id = '$form_id', object_id = '$object_id', object_type = '$object_type', sort = '$sort', extra = '$extra', created = NOW(), updated = NOW()");

	$entry_id = mysqli_insert_id($conn);

	return $entry_id;
}

function hwe_create_form_entry_value($entry_id, $field_id, $value, $value_id = NULL){
	global $conn;

	if(!empty($value_id)){
		mysqli_query($conn,"INSERT INTO ost_form_entry_values SET entry_id = '$entry_id', field_id = '$field_id', value = '$value', value_id = '$value_id'");
	}
	else{
		mysqli_query($conn,"INSERT INTO ost_form_entry_values SET entry_id = '$entry_id', field_id = '$field_id', value = '$value'");
	}

}

function hwe_get_os_ticket_user_id($User_Email){
	global $conn;

	$user_id = false;
	if($User_Email){

		$user_email_query = mysqli_query($conn, "SELECT user_id FROM ost_user_email WHERE address = '$User_Email'");
		$user_email_count = mysqli_num_rows($user_email_query);

		if($user_email_count > 0){
			$user_email_result = mysqli_fetch_assoc($user_email_query);
			$user_id = $user_email_result['user_id'];
		}
		else{
			$user_email_explode = explode('@',$User_Email);
			$user_name = $user_email_explode[0];
			$name = ucfirst($user_name);

			mysqli_query($conn,"INSERT INTO ost_user SET name = '$name'");
			$user_id = mysqli_insert_id($conn);

			mysqli_query($conn,"INSERT INTO ost_user_email SET address = '$User_Email', user_id = '$user_id'");
			$user_email_id = mysqli_insert_id($conn);

			mysqli_query($conn,"UPDATE ost_user SET default_email_id = '$user_email_id' WHERE id = '$user_id'");

		}
	}

	return $user_id;
}

$messagess = false;
if(isset($_POST['import_csv'])){


	$fileName = $_FILES["csv_file"]["tmp_name"];

    if ($_FILES["csv_file"]["size"] > 0) {

		$csv = array_map("str_getcsv", file($fileName,FILE_SKIP_EMPTY_LINES));
		$keys = array_shift($csv);

		foreach ($csv as $i=> $row) {
			$csv[$i] = array_combine($keys, $row);
		}

		foreach($csv as $csv_data){
			//$csv_column_name  = $csv_data['Help Topic'];
			//$csv_column_value = $csv_data['Help Topic'];

			$source 	= 'Phone';
			$ticket_no 	= rand(100000,999999);
			$ip 		= $_SERVER['REMOTE_ADDR'];
			$dept_id 	= 1;
			$sla_id 	= 1;
			$staff_id 	= 1;

			$os_ticket_insert_query = "INSERT INTO ost_ticket SET number = '$ticket_no', source = '$source', ip_address = '$ip', lastupdate = NOW(), created = NOW(), updated = NOW(), dept_id = '$dept_id', sla_id = '$sla_id', staff_id = '$staff_id'";
			
			if(isset($csv_data['User']) && !empty($csv_data['User'])){
				$user = trim($csv_data['User']);
				$user_id = hwe_get_os_ticket_user_id($user);
				
				$os_ticket_insert_query .= ", user_id = '$user_id'";
			}
			
			if(isset($csv_data['Status']) && !empty($csv_data['Status'])){
				$ticket_status = trim($csv_data['Status']);
				$ticket_status = ucfirst($ticket_status);
				$ticket_status_query = mysqli_query($conn, "SELECT id FROM ost_ticket_status WHERE name = '$ticket_status'");
				if( mysqli_num_rows($ticket_status_query) > 0){
					$ticket_status_row = mysqli_fetch_assoc($ticket_status_query);
					$ticket_status_id = $ticket_status_row['id'];

					$os_ticket_insert_query .= ", status_id = '$ticket_status_id'";
				}
			}

			$topic_id = false;
			if(isset($csv_data['Help Topic']) && !empty($csv_data['Help Topic'])){
				$help_topic = trim($csv_data['Help Topic']);
				if(strpos($help_topic, '/') !== false){
					$explode_help_topic = explode('/',$help_topic);
					$help_topic = trim($explode_help_topic[1]);
				}
				
				$help_topic_query = mysqli_query($conn, "SELECT topic_id FROM ost_help_topic WHERE topic = '$help_topic'");
				if( mysqli_num_rows($help_topic_query) > 0){
					$help_topic_row = mysqli_fetch_assoc($help_topic_query);
					$topic_id = $help_topic_row['topic_id'];

					$os_ticket_insert_query .= ", topic_id = '$topic_id'";
				}
			}
						
			mysqli_query($conn, $os_ticket_insert_query);
			$ticket_id = mysqli_insert_id($conn);

			$subject = '';

			if(isset($csv_data['TID']) || isset($csv_data['Location Name'])){
				if(!empty($csv_data['TID']) && !empty($csv_data['Location Name'])){
					$subject = $csv_data['TID'].' '.$csv_data['Location Name'];
				}
				else if(!empty($csv_data['TID'])){
					$subject = $csv_data['TID'];
				}
				else if(!empty($csv_data['Location Name'])){
					$subject = $csv_data['Location Name'];
				}
			}
			
			$priority = 1;
			if(isset($csv_data['Priority']) && !empty($csv_data['Priority'])){
				$priority = trim($csv_data['Priority']);
			}

			mysqli_query($conn, "INSERT INTO ost_ticket__cdata SET ticket_id = '$ticket_id', subject = '$subject', priority = '$priority'");
			mysqli_query($conn, "INSERT INTO ost_thread SET object_id = '$ticket_id', 
															object_type = 'T', 
															lastresponse = NOW(), 
															lastmessage = NOW(), 
															created = NOW()");

			if($topic_id){

				$topic_form_query = mysqli_query($conn, "SELECT * FROM ost_help_topic_form WHERE topic_id = '$topic_id'");

				if(mysqli_num_rows($topic_form_query) > 0){

					while($topic_form_row = mysqli_fetch_assoc($topic_form_query)){
						$topic_form_id 	= $topic_form_row['form_id'];
						$topic_sort 	= $topic_form_row['sort'];
						$topic_extra 	= $topic_form_row['extra'];

						$entry_id = hwe_create_form_entry($topic_form_id, $ticket_id, 'T', $topic_sort, $topic_extra);
						$form_fields = hwe_get_form_field($topic_form_id);

						foreach($form_fields as $form_field_id => $form_field_data){
							if(isset($form_field_data['label']) && !empty($form_field_data['label'])){
								$form_field_label = $form_field_data['label'];
								$form_field_type = $form_field_data['type'];

								if(isset($csv_data[$form_field_label]) && !empty($csv_data[$form_field_label])){

									$field_value = $csv_data[$form_field_label];

									if($form_field_type == 'text'){
										hwe_create_form_entry_value($entry_id, $form_field_id, $field_value);
									}
									else if($form_field_type == 'datetime'){
										$date_timestamp = strtotime($csv_data[$form_field_label]);
										$datetime = date('Y-m-d',$date_timestamp);

										hwe_create_form_entry_value($entry_id, $form_field_id, $datetime);
									}
									else if($form_field_type == 'list-2'){

										$ost_list_query = mysqli_query($conn, "SELECT id FROM `ost_list` WHERE name = '$form_field_label'");
										$ost_list_row = mysqli_fetch_assoc($ost_list_query);
										$ost_list_id = $ost_list_row['id'];

										$ost_list_items_query = mysqli_query($conn, "SELECT id, value FROM `ost_list_items` WHERE value = '$field_value' AND list_id = '$ost_list_id'");
										$ost_list_items_row = mysqli_fetch_assoc($ost_list_items_query);
										$ost_list_item_id = $ost_list_items_row['id'];
										$ost_list_item_value = $ost_list_items_row['value'];

										$_field_value = array( $ost_list_item_id => $ost_list_item_value);
										$_new_field_value = json_encode($_field_value);


										hwe_create_form_entry_value($entry_id, $form_field_id, $_new_field_value);
									}
									else if($form_field_type == 'priority'){

										$ticket_priority_query = mysqli_query($conn, "SELECT priority_desc FROM `ost_ticket_priority` WHERE priority_id = '$field_value'");
										$ticket_priority_row = mysqli_fetch_assoc($ticket_priority_query);
										$ticket_priority_desc = $ticket_priority_row['priority_desc'];
										hwe_create_form_entry_value($entry_id, $form_field_id, $ticket_priority_desc);
									}
									else if($form_field_type == 'thread'){
										hwe_create_form_entry_value($entry_id, $form_field_id, $field_value);
									}
									else{
										hwe_create_form_entry_value($entry_id, $form_field_id, $field_value);
									}
								}
								else{
									hwe_create_form_entry_value($entry_id, $form_field_id, "");
								}
							}
						}
					}
				}
			}
		}

		$messagess = '<p class="hwemessage" style="color: green;text-align: center;">Import Successfull.</p>
		<script type="text/javascript">
		$(document).ready(function(){
			setTimeout(function(){
				$(".hwemessage").fadeOut();
			}, 3000);
		})
		</script>
		';
    }
}

require_once(STAFFINC_DIR.'header.inc.php');
?>
<a href="/osticket/scp/tickets.php" class="inline button">Back</a>
<?php
if($messagess){
	echo $messagess;
}

?>
<div style="margin: 35px;">
	<div style="width: 250px;margin: auto;">
        <form method="post" action="" enctype="multipart/form-data">
            <label style="margin-bottom: 24px;display: inline-block;">Upload CSV</label>
            <input type="file" name="csv_file" accept=".csv" required="required">
            <input type="hidden" name="__CSRFToken__" value="<?php echo $ost->getCSRFToken(); ?>">
            <input type="submit" name="import_csv" value="Import" style="margin-top: 7px;">
     	</form>
    </div>
</div>
<?php
require_once(STAFFINC_DIR.'footer.inc.php');


?>
