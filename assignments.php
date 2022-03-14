<?php


namespace assignments;


class Assignment {
    public $id_file;
    public $file_name;
    public $id_user;
    public $user_login;
    public $user_first_name;
    public $user_last_name;
    public $viewed = 0;
    public $printout_requested = 0;
    public $has_logged_in;

    function set_id_file($val) {
        $this->id_file = $val;
    }

    function set_file_name($val) {
        $this->file_name = $val;
    }

    function set_id_user($val) {
        $this->id_user = $val;
    }

    function set_user_login($val) {
        $this->user_login = $val;
    }

    function set_user_first_name($val) {
        $this->user_first_name = $val;
    }

    function set_user_last_name($val) {
        $this->user_last_name = $val;
    }

    function set_viewed($val) {
        $this->viewed = $val;
    }

    function set_has_logged_in($val) {
        $this->has_logged_in = $val;
    }

    function set_printout_requested($val) {
        $this->printout_requested = $val;
    }

    function get_printout_requested() {
        return $this->printout_requested;
    }

    function get_id_user() {
        return $this->id_user;
    }

    function get_file_name() {
        return $this->file_name;
    }

    function get_id_file() {
        return $this->id_file;
    }

    function get_user_first_name() {
        return $this->user_first_name;
    }

    function get_user_last_name() {
        return $this->user_last_name;
    }

    function get_viewed() {
        return $this->viewed;
    }


    function get_has_logged_in() {
        return $this->has_logged_in;
    }

}