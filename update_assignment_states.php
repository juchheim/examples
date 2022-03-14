<?php

# include required WP stuff
define('WP_USE_THEMES', false); // don't load wp theme files
require_once('wp-load.php');    // load what's required from wp to get users


// get logins for all employees (subscribers-only)
// $user_logins = wp_list_pluck(get_users(['role__in' => ['subscriber']]), 'user_login');

# connection info
$mysqli = new mysqli("depaemployees.sftp.wpengine.com","depaemployees","x5rSIg_g6uBAVqqZ5Qnq","wp_depaemployees");

# Check connection
if ($mysqli -> connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
    exit();
}

# UPDATE PRINTOUT REQUESTS
# get all assignments from table where printout_requested == 1

$sql = "SELECT * FROM ass_viewed WHERE printout_requested = 1";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    # for each of these
    while($row = $result->fetch_assoc()) {
        # get id, id_user, id_file, printout_requested
        $ass_id = $row["id"];
        $ass_id_user = $row["id_user"];
        $ass_id_file = $row["id_file"];
        $ass_viewed = $row["viewed"];
        $ass_printout_requested = $row["printout_requested"];
        echo "id: " . $ass_id . ", id_user: " . $ass_id_user . ", id_file: " . $ass_id_file . ", viewed: " . $ass_viewed . ", printout_requested: " . $ass_printout_requested . "<br>";

        # for each of these get user login from ass_users where id == id_user
        $sql = "SELECT login FROM ass_users WHERE id = '".$ass_id_user."'";
        $results_from_users = $mysqli->query($sql);

        if ($results_from_users->num_rows > 0) {
            # for each of these
            while ($row = $results_from_users->fetch_assoc()) {
                $user_login = $row["login"];
                echo "user login: " . $user_login . "<br>";

                # get files from media with open printout request for specified user
                $podFile = new Pod('media');
                $podFile->findRecords('post_title ASC', -1, "printout_requested_by.user_login = '". $user_login ."'");

                # iterate MEDIA for files with open printout requests matching this user
                $i = 0;
                while($podFile->fetchRecord()) {
                    $media_id = $podFile->get_field('id');

                    # if media id == ass_id_file, open printout request confirmed
                    if ($media_id == $ass_id_file) {
                        $i++;
                        echo $media_id . " has ". $i ." open request by ". $user_login ." and will remain in queue<br>";
                    }
                }

                if ($i == 0) {
                    # update assignment because printout request has been fulfilled
                    echo "request has been fulfilled, updating assignment <br>";
                    # set printout_request to 0
                    $sql_update_printout_request = "UPDATE ass_viewed SET printout_requested = 0 WHERE id_user = '" . $ass_id_user . "' AND id_file =  '" . $ass_id_file . "'";
                    if ($mysqli->query($sql_update_printout_request) === TRUE) {
                        echo $ass_id .": updated existing assignment for completed printout request<br>";
                    }
                }
            }
        }
    }
    # printout request updates complete
} else {
    echo "no open printout requests found";
}

# UPDATE VIEWED
# get all assignments from table where viewed == 0 (get all unviewed)

$sql = "SELECT * FROM ass_viewed WHERE viewed = 0";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    # for each of these unviewed assignments, get assignment data
    while($row = $result->fetch_assoc()) {
        # get id, id_user, id_file, printout_requested
        $ass_id = $row["id"];
        $ass_id_user = $row["id_user"];
        $ass_id_file = $row["id_file"];
        $ass_viewed = $row["viewed"];
        $ass_printout_requested = $row["printout_requested"];
        echo "id: " . $ass_id . ", id_user: " . $ass_id_user . ", id_file: " . $ass_id_file . ", viewed: " . $ass_viewed . ", printout_requested: " . $ass_printout_requested . "<br>";

        # for each of unviewed assignments get user login from ass_users where id == id_user
        $sql = "SELECT login FROM ass_users WHERE id = '".$ass_id_user."'";
        $results_from_users = $mysqli->query($sql);

        if ($results_from_users->num_rows > 0) {
            # for each of these
            while ($row = $results_from_users->fetch_assoc()) {
                $user_login = $row["login"];
                echo "user login: " . $user_login . "<br>";

                # get files from media viewed by specified user
                $podFile = new Pod('media');
                $podFile->findRecords('post_title ASC', -1, "viewed_by.user_login = '". $user_login ."'");

                # iterate MEDIA for files viewed by this user
                $i = 0;
                while($podFile->fetchRecord()) {
                    $media_id = $podFile->get_field('id');

                    # if media id == ass_id_file, file has been viewed by user and does not require further notification
                    if ($media_id == $ass_id_file) {
                        $i++;
                        echo $media_id . " has been viewed by ". $user_login ." and will be removed from the queue<br>";
                        # update assignment because file has been viewed by user, setting viewed to 1
                        $sql_update_viewed = "UPDATE ass_viewed SET viewed = 1 WHERE id_user = '" . $ass_id_user . "' AND id_file =  '" . $ass_id_file . "'";
                        if ($mysqli->query($sql_update_viewed) === TRUE) {
                            echo $ass_id .": updated existing assignment as having been viewed by current user<br>";
                        }
                    }
                }

                if ($i == 0) {
                    # do nothing because file has not been viewed


                }
            }
        }
    }
    # printout request updates complete
} else {
    echo "no unviewed files found";
}



$mysqli->close();




