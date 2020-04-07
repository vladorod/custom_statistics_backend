<?php

class CSModel
{

    private $connection;
    public $cs_database;

    public function __construct($db_name = null)
    {

        if (empty($db_name)) global $wpdb;

        if (isset($wpdb)) {

            $this->connection = $wpdb;

            $this->cs_database = DB_NAME;

        } else {

            $this->cs_database = $db_name;

            $this->connection = new wpdb('root', '', $this->cs_database, 'localhost');

            if (!empty($this->connection->error)) wp_die($this->connection->error);

        }

    }

    public function get_users_id()
    {

        $users_id = $this->connection->get_col("SELECT db.user_id FROM ".$this->cs_database.".app_usermeta AS db WHERE db.meta_value LIKE '%subscriber%'");

        if (count($users_id) > 0) return $users_id;
        else return false;

    }

    public function get_user_meta($user_id)
    {

        $usermeta = $this->connection->get_results("SELECT db.meta_key, db.meta_value FROM ".$this->cs_database.".app_usermeta as db WHERE db.user_id = '".$user_id."' AND db.meta_key != 'APP_capabilities'", ARRAY_A);

        if ($usermeta) {

            $result = [];

            foreach ($usermeta as $values) {
                
                $result[$values['meta_key']] = $values['meta_value'];

            }

        } else $result = false;

        return $result;

    }

    public function get_some_users_meta(array $users_id)
    {

        if (count($users_id) > 0) {

            $result = [];

            foreach ($users_id as $id) {
                
                $user_meta = $this->get_user_meta($id);

                if ($user_meta) $result[$id] = $user_meta;

            }

        } else $result = false;

        return $result;

    }

    public function get_positions(array $users_meta = [], array $prev_result = [])
    {

        if (!(count($users_meta) > 0)) {

            $users = $this->get_users_id();

            if ($users) $users_meta = $this->get_some_users_meta($users);

        }

        if ($users_meta) {

            $result = $prev_result;

            foreach ($users_meta as $id => $meta) {

                if (!isset($meta['position'])) continue;
                
                if (count($result) > 0) {

                    $compare = array_search($meta['position'], $result);

                    if ($compare === false) $result[] = $meta['position'];

                } else $result[] = $meta['position'];

            }

        } else $result = false;

        return $result;

    }

    public function get_geo(array $users_meta = [], array $prev_result = [])
    {

        if (!(count($users_meta) > 0)) {

            $users = $this->get_users_id();

            if ($users) $users_meta = $this->get_some_users_meta($users);

        }

        if ($users_meta) {

            $result = $prev_result;
            if (!isset($result['undef_country'])) $result['undef_country'] = [];

            foreach ($users_meta as $id => $meta) {
                
                if (!isset($meta['country']) && !isset($meta['city'])) continue;

                if (count($result) > 0) {

                    if (isset($meta['country'])) {

                        $result_countries = array_keys($result);

                        $compare = array_search($meta['country'], $result_countries);

                        if ($compare === false) $result[$meta['country']] = [];

                    }

                    if (isset($meta['city'])) {

                        if (isset($meta['country'])) {

                            $compare = array_search($meta['city'], $result[$meta['country']]);

                            if ($compare === false) $result[$meta['country']][] = $meta['city'];

                        } else {

                            foreach ($result as $country => $cities) {
                                
                                $compare = array_search($meta['city'], $result[$country]);

                                if ($compare !== false) break;

                            }

                            if ($compare === false) $result['undef_country'][] = $meta['city'];

                        }

                    }

                } else {

                    if (isset($meta['city'])) {

                        if (isset($meta['country'])) $result[$meta['country']][] = $meta['city'];
                        else $result['undef_country'][] = $meta['city'];

                    } elseif (isset($meta['country'])) $result[$meta['country']] = [];

                }

            }

            if (count($result['undef_country']) > 0) {

                foreach ($result['undef_country'] as $id => $city) {
                    
                    foreach ($result as $country => $cities) {
                        
                        if ($country == 'undef_country') continue;

                        $compare = array_search($city, $cities);

                        if ($compare !== false) break;

                    }

                    if ($compare !== false) unset($result['undef_country'][$id]);

                }

            }

            if (!(count($result['undef_country']) > 0)) unset($result['undef_country']);

        } else $result = false;

        return $result;

    }

    public function get_sessions(array $timelapse = ['datetime_start' => null, 'datetime_end' => null])
    {

        $timelapse_correct = false;

        if (!(empty($timelapse['datetime_start'])) && !(empty($timelapse['datetime_end']))) {

            $timestamp_start = $this->timestamper($timelapse['datetime_start']);
            $timestamp_end = $this->timestamper($timelapse['datetime_end']);

            if ($timestamp_end > $timestamp_start) $timelapse_correct = true;

        }

        $sessions = $this->connection->get_results("SELECT * FROM ".$this->cs_database.".custom_statistics_sessions", ARRAY_A);

        if ($sessions) {

            $result = [];

            foreach ($sessions as $values) {
                
                $entry = true;

                if ($timelapse_correct) {

                    $session_timestamp = $this->timestamper($values['time_start']);

                    if (!($session_timestamp >= $timestamp_start && $session_timestamp <= $timestamp_end)) $entry = false;

                }

                if ($entry) {
                
                    $result[$values['user_id']][$values['action_id']] = [
                        'time_start' => $values['time_start'],
                        'time_end' => $values['time_end'],
                        'platform' => $values['platform']
                    ];

                }

            }

        } else $result = false;

        return $result;

    }

    public function get_session_info($action_id)
    {

        $session = $this->connection->get_results("SELECT db.time_start, db.time_end, db.platform FROM ".$this->cs_database.".custom_statistics_sessions AS db WHERE db.action_id = '".$action_id."'", ARRAY_A);

        if ($session) {

            $result = [
                'time_start' => $session['0']['time_start'],
                'time_end' => $session['0']['time_end'],
                'platform' => $session['0']['platform']
            ];

        } else $result = false;

        return $result;

    }

    public function get_user_sessions($user_id, array $timelapse = ['datetime_start' => null, 'datetime_end' => null])
    {

        $timelapse_correct = false;

        if (!(empty($timelapse['datetime_start'])) && !(empty($timelapse['datetime_end']))) {

            $timestamp_start = $this->timestamper($timelapse['datetime_start']);
            $timestamp_end = $this->timestamper($timelapse['datetime_end']);

            if ($timestamp_end > $timestamp_start) $timelapse_correct = true;

        }

        $sessions = $this->connection->get_results("SELECT db.time_start, db.time_end, db.action_id, db.platform FROM ".$this->cs_database.".custom_statistics_sessions AS db WHERE db.user_id = '".$user_id."'", ARRAY_A);

        if ($sessions) {

            $result = [];

            foreach ($sessions as $values) {

                $entry = true;

                if ($timelapse_correct) {

                    $session_timestamp = $this->timestamper($values['time_start']);

                    if (!($session_timestamp >= $timestamp_start && $session_timestamp <= $timestamp_end)) $entry = false;

                }

                if ($entry) {
                
                    $result[$values['action_id']] = [
                        'time_start' => $values['time_start'],
                        'time_end' => $values['time_end'],
                        'platform' => $values['platform']
                    ];

                }

            }

        } else $result = false;

        return $result;

    }

    public function get_session_actions($action_id)
    {

        $actions = $this->connection->get_results("SELECT db.id, db.time, db.action, db.action_discription FROM ".$this->cs_database.".custom_statistics_actions AS db WHERE db.action_id = '".$action_id."'", ARRAY_A);

        if ($actions) {

            $result = [];

            foreach ($actions as $values) {
                
                $result[$values['action']][$values['id']] = [
                    'action_id' => $action_id,
                    'time' => $values['time'],
                    'description' => $values['action_discription']
                ];

            }

        } else $result = false;

        return $result;

    }

    public function get_actions($user_id = null)
    {

        if (isset($user_id)) $where = " WHERE db.user_id = '".$user_id."'";
        else $where = "";

        $actions = $this->connection->get_results("SELECT db.id, db.action_id, db.time, db.action, db.action_discription FROM ".$this->cs_database.".custom_statistics_actions AS db".$where);

        if ($actions) {

            $result = [];

            foreach ($actions as $values) {
                
                $result[$values['action']][$values['id']] = [
                    'action_id' => $values['action_id'],
                    'time' => $values['time'],
                    'description' => $values['action_discription']
                ];

            }

        } else $result = false;

        return $result;

    }

    public function calculate_session_depth($action_id)
    {

        $session_actions = $this->get_session_actions($action_id);

        if ($session_actions) {

            $result = 0;

            foreach ($session_actions as $action => $data) {
                
                $result += count($data);

            }

        } else $result = false;

        return $result;

    }

    public function check_deny($action_id)
    {

        $session = $this->get_session_info($action_id);

        if ($session) {

            if (($this->timestamper($session['time_end']) - $this->timestamper($session['time_start'])) < 15) $result = true;
            else $result = false;

        } else $result = false;

        return $result;

    }

    private function timestamper($datetime)
    {

        if (empty($datetime)) $timestamp = 0;
        else {

            $datetime_explode = explode(" ", $datetime);

            $date_explode = explode("-", $datetime_explode['0']);
            $time_explode = explode(":", $datetime_explode['1']);

            if (substr($date_explode['1'], 0, 1) == '0') $date_explode['1'] = substr($date_explode['1'], 1);

            if (substr($date_explode['2'], 0, 1) == '0') $date_explode['2'] = substr($date_explode['2'], 1);

            if (substr($time_explode['0'], 0, 1) == '0') $time_explode['0'] = substr($time_explode['0'], 1);

            if (substr($time_explode['1'], 0, 1) == '0') $time_explode['1'] = substr($time_explode['1'], 1);

            if (substr($time_explode['2'], 0, 1) == '0') $time_explode['2'] = substr($time_explode['2'], 1);

            $timestamp = mktime($time_explode['0'], $time_explode['1'], $time_explode['2'], $date_explode['1'], $date_explode['2'], $date_explode['0']);

        }

        return $timestamp;

    }

}
