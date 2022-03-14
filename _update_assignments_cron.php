<?php

#   Trip Juchheim
#   8/19/20
#
#   runs every two minutes via CRON events in WordPress admin -> Tools -> cron events
#   1.  constructs assignment objects for all open assignments
#           a. for each user,
#               create assignment object for each instance where:
#                   viewed == 0,
#                   printout_requested == 1,
#                   has_logged_in == NULL
#
#   2.  syncs assignments table with assignment objects
#
#   3.  removes (completed) assignments by checking the following:
#           type == viewed == 1,
#           type == printout_requested == 0,
#           has_logged_in == NOT NULL

#   use assignments class
include "assignments.php";
use assignments\Assignment as Assignment;

define('WP_USE_THEMES', false); // don't load wp theme files
require_once('wp-load.php');    // load what's required from wp to get users

// array of assignments
$assignments = array();
$skipped_count = 0;
$completed_count = 0;

#   get all subscribers
$query = new WP_User_Query(array(
    'role' => 'Subscriber'
));
#   as user data via get_results()
$users = $query->get_results();     # WP function

#   get id, user_login, first_name, last_name from each user
foreach ($users as $user) {
    $user_info = get_userdata($user->ID);     # WP function
    $user_id = $user->id;
    $user_login = $user_info->user_login;
    $user_first_name = $user_info->first_name;
    $user_last_name = $user_info->last_name;

    echo "<br><br>" . $user_login . "<br>";

    // iterate media pod for each user
    $podFile = new Pod('media');
    $podFile->findRecords('post_title ASC', -1, "assigned_to.user_login = '". $user_login ."'");

    # for every file assigned to this user, construct assignment object
    while($podFile->fetchRecord()) {
        $assignment = new Assignment;
        $id_file = $podFile->get_field('id');
        $assignment->set_id_file($id_file);
        $assignment->set_user_login($user_login);
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
        $podUsers->findRecords('user_login ASC', -1, "user_login = '". $user_login ."'");

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
            # iterate viewed array, if viewed == current file name, delete object
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
            # iterate requests array, if request == current file name set assignments object requested to 1
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
}














