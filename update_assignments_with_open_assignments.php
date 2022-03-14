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

# iterate users array pulled from wp, constructing assignment objects
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
        $requested = array();
        $assignment->set_file_name($podFile->get_field('post_title'));
        if(in_array($podFile->get_field('post_title'), $viewed)) {
            $assignment->set_viewed(1);
        }
        if(in_array($podFile->get_field('post_title'), $requested)) {
            $assignment->set_printout_requested(1);
        }

        // iterate user pod to further build assignment object
        $podUsers = new Pod('user');
        $podUsers->findRecords('user_login ASC', -1, "user_login = '". $value ."'");

        $views = array();
        $printout_requests = array();
        while($podUsers->fetchRecord()) {
            # get user info and add to current assignment object
            $id_user = $podUsers->get_field('id');
            $assignment->set_id_user($id_user);
            $first_name = $podUsers->get_field('first_name');
            $assignment->set_user_first_name($first_name[0]);
            # $assignment->set_user_first_name($first_name);
            $last_name = $podUsers->get_field('last_name');
            $assignment->set_user_last_name($last_name[0]);
            # $assignment->set_user_last_name($last_name);
            $has_logged_in = $podUsers->get_field('has_logged_in');
            $assignment->set_has_logged_in($has_logged_in);

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

            # get all of this user's printout requests
            $printout_requested = $podUsers->get_field('printout_requested');
            # put requests in array
            foreach($printout_requested as $request) {
                array_push($printout_requests, $request[post_title]);
            }
            # iterate requests array, if request == current file namem set assignments object requested to 1
            if(!empty($printout_requests)) {
                foreach ($printout_requests as $printout_request) {
                    if($printout_request == $assignment->get_file_name()) {
                        $assignment->set_printout_requested(1);
                    }
                }
            }

            # remove any completed assignments
            if($assignment->viewed == 1 and $assignment->printout_requested == 0)
            {
                $skipped_count++;
            } else {
                # add assignment object to array
                array_push($assignments, $assignment);
                $completed_count++;
            }

        }
    }
} # all assignment objects are now constructed and in assignments array

echo $completed_count ." assignments skipped (either file has been viewed or printout request is not pending)<br>";

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
    $printout_requested = $assignments[$i]->printout_requested;

    # get object data to insert into users table
    $user_login = $assignments[$i]->user_login;
    $user_first_name = addslashes($assignments[$i]->user_first_name);
    $user_last_name = addslashes($assignments[$i]->user_last_name);
    $has_logged_in = $assignments[$i]->has_logged_in;


    # connection info
    $mysqli = new mysqli("depaemployees.sftp.wpengine.com","depaemployees","x5rSIg_g6uBAVqqZ5Qnq","wp_depaemployees");

    # Check connection
    if ($mysqli -> connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
        exit();
    }

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
        $sql = "INSERT INTO ass_media (id, file_name, open_assignments)
            VALUES ('".$id_file."', '".$file_name."', open_assignments = open_assignments + 1)";

        if ($mysqli->query($sql) === TRUE) {
            echo $id_file .": file added to media table<br>";
        }
    }

    $i++;



    # get all assignments with current id
    if ($result = $mysqli -> query("SELECT * FROM ass_viewed WHERE id = '" .$id."'")) {
        # if assignment isn't in assignments and isn't viewed
        if($result -> num_rows == 0 and $assignment->get_viewed() == 0) {
            $sql = "INSERT INTO ass_viewed (id, id_user, id_file, viewed)
            VALUES ('" .$id."', '".$id_user."', '".$id_file."', '".$assignment->get_viewed()."')";

            if ($mysqli->query($sql) === TRUE) {
                echo $id .": new unviewed assignment added.<br>";
                # add 1 to open assignments in users where id_user
                $sql = "UPDATE ass_users SET open_assignments = open_assignments + 1 WHERE id = '". $assignment->get_id_user() ."'";
                if ($mysqli->query($sql) === TRUE) {
                    echo $assignment->get_id_user() .": assignment + 1<br>";
                }

            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }
        # if assignment isn't in assignments and no printout requested
        if ($result -> num_rows == 0 and $assignment->get_printout_requested() == 1) {
            $sql = "INSERT INTO ass_viewed (id, id_user, id_file, printout_requested)
            VALUES ('" .$id."', '".$id_user."', '".$id_file."', '".$assignment->get_printout_requested()."')";

            if ($mysqli->query($sql) === TRUE) {
                echo $id .": new printout request assignment added<br>";

                # add 1 to open assignments in media where id_file
                $sql = "UPDATE ass_media SET open_assignments = open_assignments + 1 WHERE id = '". $assignment->get_id_file() ."'";
                if ($mysqli->query($sql) === TRUE) {
                    echo "open assignment + 1<br>";
                }

            }
        }
        # if assignment has printout request and was already in assignments and not already requested
        $result = $mysqli -> query("SELECT * FROM ass_viewed WHERE id = '" .$id."' AND printout_requested = 0");

        if ($assignment->get_printout_requested() == 1 and $result -> num_rows == 1 ) {
            $sql = "UPDATE ass_viewed SET printout_requested = 1 WHERE id = '" . $id . "' AND printout_requested = 0";
            if ($mysqli->query($sql) === TRUE) {
                echo $id .": updated existing assignment with new printout request<br>";

                # add 1 to open assignments in media where id_file
                $sql = "UPDATE ass_media SET open_assignments = open_assignments + 1 WHERE id = '". $assignment->get_id_file() ."'";
                if ($mysqli->query($sql) === TRUE) {
                    echo "open_assignment + 1<br>";
                   # echo $assignment->get_file_id() .": assignment + 1<br>";
                }
            }
        }
        # if is in assignments table and had printout request, but no longer has printout request
        $result = $mysqli -> query("SELECT * FROM ass_viewed WHERE id = '" .$id."' AND printout_requested = 1");
        if ($assignment->get_printout_requested() == 0 and $result -> num_rows == 1) {
            $sql = "UPDATE ass_viewed SET printout_requested = 0 WHERE id = '" . $id . "'";
            if ($mysqli->query($sql) === TRUE) {
                echo $id  .": updated for newly fulfilled printout request<br>";
                # subtract 1 to open assignments in media where id_file
                $sql = "UPDATE ass_media SET open_assignments = open_assignments - 1 WHERE id = '". $assignment->get_id_file() ."'";
                if ($mysqli->query($sql) === TRUE) {
                    echo "open assignment - 1<br>";
                }

            }
        }
        # if is in assignments table and had not be viewed, but now has been viewed
        $result = $mysqli -> query("SELECT * FROM ass_viewed WHERE id = '" .$id."' AND viewed = 0");
        if ($assignment->get_viewed() == 1 and $result -> num_rows == 1) {
            $sql = "UPDATE ass_viewed SET viewed = 1 WHERE id = '" . $id . "' AND viewed = 0";
            if ($mysqli->query($sql) === TRUE) {
                echo "updated for newly viewed assignment<br>";
                # subtract 1 to open assignments in users where id_file
                $sql = "UPDATE ass_users SET open_assignments = GREATEST(open_assignment - 1, 0) WHERE id = '". $assignment->get_id_user() ."'";
             #   $sql .= "UPDATE ass_users SET open_assignment = GREATEST(open_assignment, 0)";
                if ($mysqli->query($sql) === TRUE) {
                    echo $assignment->get_id_user() .": assignment - 1<br>";
                }

            }
        }


        // Free result set
        $result -> free_result();
    }






}

# remove any completed assignments where viewed == 1 and printout requested == 0
$result = $mysqli -> query("DELETE FROM ass_viewed WHERE viewed = 1 and printout_requested = 0");
if ($mysqli->query($sql) === TRUE) {
    echo $mysqli->affected_rows ." assignments deleted<br>";
}

# remove any media where open_assignment < 1
$result = $mysqli -> query("DELETE FROM ass_media WHERE open_assignments = 0");
if ($mysqli->query($sql) === TRUE) {
    echo $mysqli->affected_rows ." media deleted<br>";
}

# remove any users where open_assignment < 1
$result = $mysqli -> query("DELETE FROM ass_users WHERE open_assignments = 0");
if ($mysqli->query($sql) === TRUE) {
    echo $mysqli->affected_rows ." user(s) deleted<br>";
}


$mysqli -> close();

echo "it is finished.";

// Close connection
// mysqli_close($conn);

?>