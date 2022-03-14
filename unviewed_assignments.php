<?php

include "assignments.php";
use assignments\Assignment as Assignment;

# constructs assignment objects
# update media, users, assignments tables

define('WP_USE_THEMES', false); // don't load wp theme files
require_once('wp-load.php');    // load what's required from wp to get users


// get logins for all employees (subscribers-only)
$user_logins = wp_list_pluck(get_users(['role__in' => ['subscriber']]), 'user_login');

// array of assignments
$assignments = array();

$skipped_count = 0;
$completed_count = 0;

# connection info
$mysqli = new mysqli("depaemployees.sftp.wpengine.com","depaemployees","x5rSIg_g6uBAVqqZ5Qnq","wp_depaemployees");

# Check connection
if ($mysqli -> connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
    exit();
}

$delete_table = $mysqli -> query("DELETE FROM ass_viewed");
$mysqli->query($delete_table);

# iterate users array pulled from wp, constructing assignment objects that have not been viewed
foreach ($user_logins as $key => $value) {
    // iterate media pod for each user
    $podFile = new Pod('media');
    $podFile->findRecords('post_title ASC', -1, "assigned_to.user_login = '". $value ."'");

    # for every file assigned to this user, construct assignment object
    while($podFile->fetchRecord()) {
        $assignment = new Assignment;
        $id_file = $podFile->get_field('id');
        $assignment->set_id_file($id_file);
        $assignment->set_user_login($value);
        $viewed = array();
        $assignment->set_file_name($podFile->get_field('post_title'));
        if(in_array($podFile->get_field('post_title'), $viewed)) {
            $assignment->set_viewed(1);
        }

        // iterate user pod to further build assignment object
        $podUsers = new Pod('user');
        $podUsers->findRecords('user_login ASC', -1, "user_login = '". $value ."'");

        $views = array();
        while($podUsers->fetchRecord()) {
            # get user info and add to current assignment object
            $id_user = $podUsers->get_field('id');
            $assignment->set_id_user($id_user);
            $first_name = $podUsers->get_field('first_name');
            $assignment->set_user_first_name($first_name[0]);
            # $assignment->set_user_first_name($first_name);
            $last_name = $podUsers->get_field('last_name');
            $assignment->set_user_last_name($last_name[0]);

            # get all of this user's viewed documents
            $viewed_documents = $podUsers->get_field('viewed_documents');
            # put viewed into array
            foreach($viewed_documents as $view) {
                array_push($views, $view[post_title]);
            }
            # iterate viewed array, if viewed == current file name, set assignments object viewed to 1
            if(!empty($views)) {
                foreach ($views as $file_view) {
                    if($file_view == $assignment->get_file_name()) {
                        $assignment->set_viewed(1);
                    }
                }
            }

            # skip any viewed assignments
            if($assignment->viewed == 1)
            {
                $skipped_count++;
            } else {
                # add assignment object to array
                array_push($assignments, $assignment);
                $completed_count++;
            }

        }
    }
} # all unviewed assignment objects are now constructed and in assignments array

echo $completed_count ." assignments exist (file has not been viewed)<br>";
echo $skipped_count ." assignments skipped (file has been viewed)<br>";

# use assignments array of assignment objects to update media, users, assignments tables

$i = 0;
foreach($assignments as $assignment) {
    # get id's
    $id_user = $assignments[$i]->id_user;
    $id_file = $assignments[$i]->id_file;

    # combine id's into assignment id
    $id = "user". $id_user ."file". $id_file;

    # get other object data
    $has_viewed = $assignments[$i]->viewed;

    # get object data to insert into users table
    $user_login = $assignments[$i]->user_login;
    $user_first_name = addslashes($assignments[$i]->user_first_name);
    $user_last_name = addslashes($assignments[$i]->user_last_name);



    # add to users table if not exists
    $result = $mysqli -> query("SELECT * FROM ass_users WHERE id = '".$id_user."'");
    if ($result -> num_rows == 0) {
        # insert into users
        $sql = "INSERT INTO ass_users (id, login, first_name, last_name)
            VALUES ('".$id_user."', '".$user_login."', '".$user_first_name."', '".$user_last_name."')";

        if ($mysqli->query($sql) === TRUE) {
            echo $id_user .": user added to user table<br>";
        }
    }

    # get object data to insert into media table
    $file_name = $assignments[$i]->file_name;

    # add to media table if not exists
    $result = $mysqli -> query("SELECT * FROM ass_media WHERE id = '".$id_file."'");
    if ($result -> num_rows == 0) {
        # insert into media
        $sql = "INSERT INTO ass_media (id, file_name)
            VALUES ('".$id_file."', '".$file_name."')";

        if ($mysqli->query($sql) === TRUE) {
            echo $id_file .": file added to media table<br>";
        }
    }

    $i++;



    # get all assignments with current id
    if ($result = $mysqli -> query("SELECT * FROM ass_viewed WHERE id = '".$id."'")) {
        # if assignment isn't in assignments and isn't viewed
        if($result -> num_rows == 0 and $assignment->get_viewed() == 0) {
            $sql = "INSERT INTO ass_viewed (id, id_user, id_file, viewed)
            VALUES ('".$id."', '".$id_user."', '".$id_file."', '".$assignment->get_viewed()."')";

            if ($mysqli->query($sql) === TRUE) {
                echo $id .": new unviewed assignment added.<br>";
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }

        # if is in assignments table and had not be viewed, but now has been viewed
        $result = $mysqli -> query("SELECT * FROM ass_viewed WHERE id = '".$id."' AND viewed = 0");
        if ($assignment->get_viewed() == 1 and $result -> num_rows == 1) {
            $sql = "UPDATE ass_viewed SET viewed = 1 WHERE id = '" . $id . "' AND viewed = 0";
            if ($mysqli->query($sql) === TRUE) {
                echo "updated for newly viewed assignment<br>";
            }
        }

        // Free result set
        $result -> free_result();

    }
}


$mysqli -> close();

echo "finished updating unviewed assignments";

// Close connection
// mysqli_close($conn);

?>