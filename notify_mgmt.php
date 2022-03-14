<?php

# notifies james kenwright of unviewed files

# connection info
$mysqli = new mysqli("depaemployees.sftp.wpengine.com","depaemployees","x5rSIg_g6uBAVqqZ5Qnq","wp_depaemployees");

# Check connection
if ($mysqli -> connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
    exit();
}

#   email headers
# $to = "trip@hammons.com"; # jkenwright@deltaepa.coop
$to = "jkenwright@deltaepa.coop"; # trip@hammons.com

$subject = "Employee report for employees.deltaepa.com";

#   email body
$message = "
	<html>
	<head>
	<title>employees.deltaepa.com Management report</title>
	</head>
	<body>
	Employee report for <a href='https://employees.deltaepa.com'>employees.deltaepa.com</a>:<br><br>
	";

#   get all users with unviewed assignments
$result = $mysqli -> query("SELECT id, login, first_name, last_name 
                            FROM ass_users 
                            WHERE id IN 
                                (SELECT id_user FROM ass_viewed)");
while ($row = $result->fetch_assoc()) {
    $id = $row["id"];
    $login = $row["login"];
    $first_name = $row["first_name"];
    $last_name = $row["last_name"];
    $message .= "<h4 style='padding:24px 0 4px 0; margin:0'>
                " . $first_name . " " . $last_name . ": (<a href='mailto:'" . $login . "''>" . $login . "</a>) 
                has unviewed files
                </h4>";

    $unviewed_files = $mysqli->query("SELECT id_user, id_file FROM ass_viewed WHERE id_user = '" . $id . "'");
    while ($row = $unviewed_files->fetch_assoc()) {
        $id_user = $row["id_user"];
        $id_file = $row["id_file"];
        $media = $mysqli->query("SELECT id, file_name FROM ass_media WHERE id = '" . $id_file . "'");
        while ($row = $media->fetch_assoc()) {
            $file_name = $row["file_name"];
            $message .= "<h4 style='padding:10px 0 4px 0; margin:0'>
                " . $file_name . "
                </h4>";
        }
    }
}

# email headers and send action
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <no-reply@deltaepa.com>' . "\r\n";
$headers .= 'Bcc: trip@hammons.com' . "\r\n";

mail($to,$subject,$message,$headers);

mysqli_close($conn);

die($message);

