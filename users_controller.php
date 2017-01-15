<?php
/**
 * Controller Name: Users
 * Description: Class Users Controller
 * Version: 1.0
 * Author: Oswald Plazola
 * @since 09.27.2015
 *
 * 
 * */
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

error_reporting(E_STRICK);
ini_set("display_errors", 0);

class Users extends CI_Controller {

    protected $_data;
    
    /**
     * Constructor
     * 
     * Description: Loads CI database module
     *              Sets permission levels according to member ACL
     * 
     **/

    public function __construct() {
        parent::__construct();
        ini_set('memory_limit', '1024M');
        
        $this->load->library("Aauth");
        $this->load->model('Crm');
        $this->load->model('Gravatar');
        $this->load->model('Agents');
        $this->load->model('Activity');
        $this->load->model('Client');
        $this->load->helper('url');
        $this->config->load('crm');

        if (!($this->aauth->is_member('Admin') || $this->aauth->is_member('Manager') || $this->aauth->is_member('Agent'))) {
            redirect('index/login', 'refresh');
        }
        if (($this->aauth->is_member('Admin') == TRUE)) {
            $level = "admin";
        }
        if (($this->aauth->is_member('Manager') == TRUE)) {
            $level = "manager";
        }
        if (($this->aauth->is_member('Agent') == TRUE)) {
            $level = "agent";
        }
        $this->_data = array();
        $this->_data['menu'] = $this->Crm->menu();
        $this->_data['level'] = $level;
        $user = $this->Aauth->get_user();
        foreach ($user as $key => $value) {
            $this->_data['user'][$key] = $value;
        }
        $this->_data['client'] = $this->_data['user']['client_id'];
        $this->_data['user']['gravatar'] = $this->Gravatar->buildGravatarURL($user->email);
        $this->_data['client_info'] = $this->Client->get($this->_data['client']);
        $this->_data['user']['gid'] = $this->get_user_groupid_by_title($this->_data['user']['title']);
    }

    /**
     * index
     * 
     * Description: This is the index controller for Users
     *              Sets permission levels according to member ACL
     * 
     **/
    public function index() {
        $this->_data['page'] = 'users';
        $sql = "
            SELECT aauth_users.*,aauth_groups.id as gid,aauth_groups.name as gname ,crm_clients.name as client_name
            FROM   aauth_users
            INNER JOIN aauth_user_to_group  ON aauth_users.id = aauth_user_to_group.user_id
            INNER JOIN aauth_groups ON aauth_groups.id = aauth_user_to_group.group_id
            INNER JOIN crm_clients ON aauth_users.client_id = crm_clients.client_id
            WHERE aauth_users.client_id = " . $this->_data['user']['client_id'];

        $query = $this->db->query($sql);
        $this->_data['results'] = $query->result();

        $i = 0;
        foreach ($this->_data['results'] as $user) {
            if ($this->_data['user']['title'] != 'Agent') {
                $sql = "SELECT id FROM aauth_groups                   
                     WHERE aauth_groups.name = '" . $user->title . "'";
                $query = $this->db->query($sql);
                $group_id_arr = $query->result();
                $group_id = $group_id_arr[0]->id;
                if ($group_id < $this->_data['user']['gid']) {
                    unset($this->_data['results'][$i]);
                }
            } else {
                if ($user->email != $this->_data['user']['email']) {
                    unset($this->_data['results'][$i]);
                }
            }
            $i++;
        }
        $this->load->template('admin/users/list', $this->_data);
    }

    public function id() {
        $this->_data['page'] = 'lead';
        $this->_data['result'] = $this->aauth->get_user($this->uri->segment(4));
        $this->load->template('admin/users/add', $this->_data);
    }

    /**
     *  swicth_client
     * 
     *  Description: This function was writen to accomodate Managers who handle several clients
     *               so that they don't need to re-login
     * 
     *  @var $client_id type int contains client to be swicthed to
     */
    public function swicth_client() {
        $client_id = $this->uri->segment(4);
        $this->_data['client'] = $client_id;
        $this->_data['user']['client_id'] = $client_id;
        $sql = "UPDATE aauth_users SET client_id = " . $client_id . ' WHERE id = ' . $this->_data['user']['id'];
        $this->db->query($sql);
        redirect('admin', 'refresh');
    }

    public function add() {
        $this->_data['page'] = 'user';
        $this->load->template('admin/users/add', $this->_data);
    }

    public function update() {
        $this->_data['page'] = 'user';
        $data = $this->_data;
        $params = $this->uri->uri_to_assoc();
        $id = $params['update'];
        $data['aauser'] = $this->Aauth->get_user($id);
        $this->load->template('admin/users/update', $data);
    }

    public function delete() {
        $this->_data['page'] = 'user';
        $params = $this->uri->uri_to_assoc();
        $id = $params['delete'];
        if ($id == '' || $id == NULL) {
            echo "UNABLE TO DELETE USER INPUT PARAM ERROR";
            log_message('error', __METHOD__ . ' Unable to delete user with id: no user id param given.');
            return false;
        }
        $result = $this->Aauth->delete_user($id);
        if(false == $result){
            log_message('error', __METHOD__ . ' Unable to delete user with id: ' . $id);
            return false;
        }
        redirect('/admin/users/', 'refresh');
    }

    public function create() {
        $this->_data['page'] = 'user';

        $email = $this->input->post('email');
        $name  = $this->input->post('name');
        $pass  = $this->input->post('password');
        $group_id = $this->input->post('type');

        if ($group_id == '' || $group_id == NULL) {
            log_message('error', __METHOD__ . ' Unable to create user with id: no group id found');
            return false;
        }

        $client_id = $this->_data['user']['client_id'];

        switch ($group_id) {
            case '1':
                $group_par = 'Admin';
                break;
            case '2':
                $group_par = 'Manager';
                break;
            case '3':
                $group_par = 'Agent';
                break;
            case '4':
                $group_par = 'Member';
                break;
        }

        $user_id = $this->Aauth->create_user($email, $pass, $name, $client_id);
        if ($user_id !== false) {
            $result_up = $this->Aauth->update_user($user_id, $email, $pass, $name);
            if ($result_up !== false) {
                $result_add = $this->Aauth->add_member($user_id, $group_par);
                $this->Aauth->update($user_id, 'title', $group_par);
                if ($result_add === false) {
                    echo "<h1>unable to add user to group!</h1> Please contact sysadmin@excelerondesigns.com";
                    log_message('error', __METHOD__ . ' Unable add user to group with id: ' . $user_id);
                }
            } else {
                echo "<h1>unable to update/create user!</h1> Please contact sysadmin@excelerondesigns.com";
                log_message('error', __METHOD__ . ' Unable to update/create user : ' . $user_id);
                return false;
            }
        } else {
            echo "<h1>unable to create user!</h1> Please contact sysadmin@excelerondesigns.com";
            log_message('error', __METHOD__ . ' Unable to update/create user');
            return false;
        }
        redirect('/admin/users/', 'refresh');
    }

    public function update_user() {
        $this->_data['page'] = 'user';
        $id = $this->input->post('myid');
        if ($id == '' || $id == NULL) {
            echo "UNABLE TO UPDATE USER. Contact sysadmin@excelerondesigns";
            log_message('error', __METHOD__ . ' Unable to update user: no id found ');
            return false;
        }
        $email = $this->input->post('email');
        $name = $this->input->post('firstname');
        $pass = $this->input->post('password');
        $group_id = $this->input->post('type');
        $client_id = $this->_data['user']['client_id'];

        switch ($group_id) {
            case '1':
                $group_par = 'Admin';
                break;
            case '2':
                $group_par = 'Manager';
                break;
            case '3':
                $group_par = 'Agent';
                break;
            case '4':
                $group_par = 'Member';
                break;
            default:
                $group_par = false;
        }

        $user_id = ($id) ? $id : FALSE;
        $group_par = ($group_par) ? $group_par : FALSE;
        $pass = ($pass) ? $pass : FALSE;
        $email = ($email) ? $email : FALSE;
        $name = ($name) ? $name : FALSE;

        $this->Aauth->update_user($user_id, $email, $pass, $name);
        redirect('/admin/users/', 'refresh');
    }

    private function get_user_groupid_by_title($title_str) {
        $title = trim($title_str);
        $sql = "SELECT id FROM aauth_groups WHERE aauth_groups.name = '" . $title . "'";
        $query = $this->db->query($sql);
        $group_id_arr = $query->result();
        $group_id = $group_id_arr[0]->id;
        return $group_id;
    }

    public function assign_agent() {
        $agents = $this->load->Agents();
        $agents->assign_next_agent($this->_data['client']);
        $this->_data['page'] = 'agents';
        $this->_data['agents'] = $agents->get_all($this->_data['client']);
        $this->_data['client_url'] = $this->_data['client_info']['website'];
        $data = $this->_data;
        $this->load->template('admin/users/agents', $data);
    }

    public function get_rets_leads_id($id) {
        $this->db->select("rets_leads.*, o.name AS o_name, u.first_name  AS u_first_name, u.last_name  AS u_last_name, u.phone  AS u_phone, u.email  AS u_email");
        $this->db->from("rets_leads");
        $this->db->where('rets_leads.id', $id);
        $this->db->order_by("date", "desc");
        $this->db->join("crm_users as u", "u.email = rets_leads.user_email", 'left');
        $this->db->join("aauth_users as o", "o.email = rets_leads.owner", 'left');
        $query = $this->db->get()->result_array();
        $result = $query[0];
        if (isset($result['activity'])) {
            $activities = array();
            foreach (explode(',', $result['activity']) as $activity) {
                $activities[] = $this->Activity->retrieve($activity);
            }
            $result['activities'] = $activities;
        }
        return $result;
    }

    public function get_all_rets_leads($client) {
        $this->db->select("rets_leads.*, o.name AS o_name, u.first_name  AS u_first_name, u.last_name  AS u_last_name");
        $this->db->from("rets_leads");
        if ((isset($client)) && ($client != '')) {
            $this->db->where('rets_leads.client_id', $client);
        }
        $this->db->order_by("date", "desc");
        $this->db->join("crm_users as u", "u.email = rets_leads.user_email", 'left');
        $this->db->join("aauth_users as o", "o.email = rets_leads.owner", 'left');
        $query = $this->db->get()->result_array();
        return $query;
    }

}
