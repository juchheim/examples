<?php
// Protect uploaded files with login.
require_once('wp-load.php');
require_once ABSPATH . WPINC . '/formatting.php';
require_once ABSPATH . WPINC . '/capabilities.php';
require_once ABSPATH . WPINC . '/user.php';
require_once ABSPATH . WPINC . '/meta.php';
require_once ABSPATH . WPINC . '/post.php';
require_once ABSPATH . WPINC . '/pluggable.php';
wp_cookie_constants();
ob_end_clean();
ob_end_flush();

if ( is_user_logged_in() ) {
    $fileName = $_GET['file'];
 //   $fileName = substr($fileName, 0, -4);
 //   $fileName = strtolower($fileName);
    $user = wp_get_current_user();
    $currentUser = $user->display_name;

    // get current file where file = $fileName and check if current user is assigned
    $podFile = new Pod('media');
    $podFile->findRecords('post_title ASC', -1);

    while ($podFile->fetchRecord()) {
        $fileTitle = $podFile->get_field('post_title');
     //   $slug = $podFile->get_field('slug');
        $fileID = $podFile->get_field('id');
        $fileURL = basename(wp_get_attachment_url( $fileID ));
        if ($fileURL == $fileName) {
            $assigned_to = $podFile->get_field('assigned_to');
            if (!empty($assigned_to)) {
                foreach ($assigned_to as $assigned_too) {
                    $theAssigned = $assigned_too['display_name'];
                    if ($theAssigned == $currentUser) {
                        list($basedir) = array_values(array_intersect_key(wp_upload_dir(), array('basedir' => 1))) + array(NULL);
                        $file = rtrim($basedir, '/') . '/' . str_replace('..', '', isset($_GET['file']) ? $_GET['file'] : '');
                        header('Content-Type: application/pdf');
                        readfile($file);
                    }
                }
            }
        }
    }
}
echo "You do not have access to this file.";
?>