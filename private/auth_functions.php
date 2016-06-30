<?php

  // Will perform all actions necessary to log in the user
  // Also protects user from session fixation.
  function log_in_user($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['last_login'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    return true;
  }

  // A one-step function to destroy the current session
  function destroy_current_session() {
    session_unset();
    session_destroy();
  }

  // Performs all actions necessary to log out a user
  function log_out_user() {
    unset($_SESSION['user_id']);
    destroy_current_session();
    return true;
  }

  // Determines if the request should be considered a "recent"
  // request by comparing it to the user's last login time.
  function last_login_is_recent() {
    $recent_limit = 60 * 60 * 24 * 1; // 1 day
    if(!isset($_SESSION['last_login'])) { return false; }
    return (($_SESSION['last_login'] + $recent_limit) >= time());
  }

  // Checks to see if the user-agent string of the current request
  // matches the user-agent string used when the user last logged in.
  function user_agent_matches_session() {
    if(!isset($_SERVER['HTTP_USER_AGENT'])) { return false; }
    if(!isset($_SESSION['user_agent'])) { return false; }
    return ($_SERVER['HTTP_USER_AGENT'] === $_SESSION['user_agent']);
  }

  // Inspects the session to see if it should be considered valid.
  function session_is_valid() {
    if(!last_login_is_recent()) { return false; }
    if(!user_agent_matches_session()) { return false; }
    return true;
  }

  // is_logged_in() contains all the logic for determining if a
  // request should be considered a "logged in" request or not.
  // It is the core of require_login() but it can also be called
  // on its own in other contexts (e.g. display one link if a user
  // is logged in and display another link if they are not)
  function is_logged_in() {
    // Having a user_id in the session serves a dual-purpose:
    // - Its presence indicates the user is logged in.
    // - Its value tells which user for looking up their record.
    if(!isset($_SESSION['user_id'])) { return false; }
    if(!session_is_valid()) { return false; }
    return true;
  }

  // Call require_login() at the top of any page which needs to
  // require a valid login before granting acccess to the page.
  function require_login() {
    if(!is_logged_in()) {
      destroy_current_session();
      redirect_to(url_for('/staff/login.php'));
    } else {
      // Do nothing, let the rest of the page proceed
    }
  }

  // increments the failed login count and updates the last attempt time,
  // or creates a new record
  function record_failed_login($username) {
    $sql_date = date("Y-m-d H:i:s");

    $fl_result = find_failed_login($username);
    $failed_login = db_fetch_assoc($fl_result);

    if(!$failed_login) {
      $failed_login = [
        'username' => $username,
        'count' => 1,
        'last_attempt' => $sql_date
      ];
      insert_failed_login($failed_login);
    } else {
      $failed_login['count'] = $failed_login['count'] + 1;
      $failed_login['last_attempt'] = $sql_date;
      update_failed_login($failed_login);
    }
    return true;
  }

  // returns the lockout minutes remaining for a user, or 0 if they have not
  // reached the lockout threshold
  function throttle_time($username) {
    $threshold = 5;
    $lockout = 60 * 5; // in seconds
    $fl_result = find_failed_login($username);
    $failed_login = db_fetch_assoc($fl_result);
    if(!isset($failed_login)) { return 0; }
    if($failed_login['count'] < $threshold) { return 0; }
    $last_attempt = strtotime($failed_login['last_attempt']);
    $since_last_attempt = time() - $last_attempt;
    $seconds_remaining = $lockout - $since_last_attempt;
    $minutes_remaining = ceil($seconds_remaining/60);
    if($seconds_remaining <= 0) {
      reset_failed_login($username);
      return 0;
    } else {
      return $minutes_remaining;
    }
  }

  // hashes a given password using bcrypt with an optionally specified. emulates password_hash
  function my_password_hash($password, $cost=10) {
    $hash_format = "$2y" . $cost . "$";
    $salt = make_salt();
    return crypt($password, $hash_format.$salt);
  }

  // verifies that the bcrypt hash of a given password matches a given hash
  function my_password_verify($password, $hashed_password) {
    return crypt($password, $hashed_password) === $hashed_password;
  }

  // returns a random string of an optionally given length
  function random_string($length=22) {
    // random_bytes requires an integer larger than 1
    $length = max(1, (int) $length);
    // generates a longer string than needed
    $rand_str = base64_encode(random_bytes($length));
    // substr cuts it to the correct size
    return substr($rand_str, 0, $length);
  }

  // creates a salt appropriate for the bcrypt encryption algorithm
  function make_salt() {
    $rand_str = random_string(22);
    // bcrypt doesn't like '+'
    $salt = strtr($rand_str, '+', '.');
    return $salt;
  }

  // creates a password of upper and lower case characters, numbers, and symbols
  function generate_strong_password($length=12) {
    $possible_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*?';
    $password = '';
    $max = strlen($possible_chars) - 1;
    while (!has_valid_password_format($password)) {
      $password = '';
      for ($i = 0; $i < $length; $i++) {
          $password .= $possible_chars[random_int(0, $max)];
      }
    }
    return $password;
  }
?>
