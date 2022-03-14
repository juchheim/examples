<?php

define('WP_USE_THEMES', false); // don't load wp theme files
require_once('wp-load.php');    // load what's required from wp to get users

# compiles data and notifies one employee. to run every minute via CRON
# conditionals: employee hasn't been notified in the past week
#               employee has not viewed at least one file

# get interval from manager settings
$interval = get_option( 'manager_settings_employee_email_notification_recurrence' ); // manager_settings is the pod, employee_email_notification_recurrence is the field

# connection info
$mysqli = new mysqli("depaemployees.sftp.wpengine.com","depaemployees","x5rSIg_g6uBAVqqZ5Qnq","wp_depaemployees");

# Check connection
if ($mysqli -> connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
    exit();
}

$users = array();

# get id_user from assignments where matches id in ass_users and not viewed and not last notified
# get id, login, first_name, last_name, has_logged_in from ass_users
$result = $mysqli -> query("
    SELECT 
        ass_viewed.id_user, 
        ass_viewed.id_file, 
        ass_users.id, 
        ass_users.login, 
        ass_users.first_name, 
        ass_users.last_name, 
        ass_users.has_logged_in
    FROM 
        ass_viewed, 
        ass_users
    WHERE 
        ass_viewed.id_user = ass_users.id 
    AND 
        ass_viewed.viewed = 0 
    AND 
        ( ass_users.last_notified IS NULL OR (ass_users.last_notified < (NOW() - INTERVAL 1 " . $interval ."))  
    LIMIT 1
    ");

# if result exists
if ($result -> num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        # get file id, user id, login, first, last, logged in
        $ass_id_file = $row["id_file"];
        $ass_login = $row["login"];
        $ass_first_name = $row["first_name"];
        $ass_last_name = $row["last_name"];
        $ass_has_logged_in = $row["has_logged_in"];
        $ass_user_id = $row["id_user"];

        # get file_name from ass_media where id == id_file
        $result = $mysqli->query("SELECT file_name FROM ass_media WHERE id = " . $ass_id_file . " ");
        $ass_file_names = array();
        # if exists, add file name to array of file names
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $ass_file_name = $row["file_name"];
                array_push($ass_file_names, $ass_file_name);
            }
        }
    }
} else {
    die("no results in assignments");
}

echo "result found, emailing: ". $ass_login ."<br>";

$result = $mysqli->query("UPDATE ass_users SET last_notified = CURRENT_TIMESTAMP() WHERE login = '". $ass_login ."' ");
if ($mysqli->query($result) === TRUE) {
echo $ass_login .": last_notified updated, sending email<br>";
}

# email headers
$to = $ass_login;
// $to = "trip@hammons.com";

$subject = "You have files that required viewing at employees.deltaepa.com";

# email body
$message = "
<html>
<head>
    <title>employees.deltaepa.com Employee files require viewing</title>
</head>
<body>
You are receiving this message because you have one or more documents awaiting your review on <a href='https://employees.deltaepa.com'>employees.deltaepa.com</a>.<br><br>
";

// $message .= $ass_first_name ." ". $ass_last_name ."</a> has unviewed files:<br>";

// foreach ($ass_file_names as $ass_file_name) {
//    $message .= $ass_file_name ."<br>";
// }

$message .= "<br>Please view and check the box confirming that you've viewed the file(s).";

# email headers and send action
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <no-reply@deltaepa.com>' . "\r\n";
$headers .= 'Bcc: trip@hammons.com' . "\r\n";

mail($to,$subject,$message,$headers);

mysqli_close($mysqli);

die("email sent");