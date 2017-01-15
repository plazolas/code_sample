<?php

/**
 * Model Name: Crm_Agent
 * Description: Class to handle Exceleron Designs CRM Agents model
 * Version: 1.0
 * Author: Oswald Plazola
 * @since 09.27.2015
 *
 * */

class Crm_Agent extends CI_Model {

    /**
     * Constructor
     * 
     * Loads CI database module
     */
    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * get
     * 
     * Get Agent StdClass Object given agent ID
     * 
     * @access (public)
     * @param type int $id
     * @return type stdClass
     */
    public function get($id) {
        $this->db->select("*");
        $this->db->from("crm_agents");
        $this->db->where('id', $id);
        $query = $this->db->get();
        $row = $query->result();
        if ($row && count($row) > 0) {
            return $row[0];
        } else {
            return false;
        }
    }

    /**
     * get_by_email
     * 
     * Get Agent StdClass Object given agent's email
     *
     * @access (public) 
     * @param  type string $email
     * @return type stdClass
     *
     */
    public function get_by_email($email) {
        $this->db->select("*");
        $this->db->from("crm_agents");
        $this->db->where('email', $email);
        $query = $this->db->get();
        $row = $query->result();
        if ($row && count($row) > 0) {
            return $row[0];
        } else {
            return false;
        }
    }

    /**
     * get_active
     * 
     * Get ALL active Agents for give Client
     *
     * @access (public) 
     * @param  type int $client_id
     * @return type array of stdClass Objetcs
     *
     */
    public function get_active($client_id) {
        $this->db->select("*");
        $this->db->from("crm_agents");
        $this->db->where('client_id', $client_id);
        $this->db->where('active', 1);
        $this->db->order_by('id', 'asc');
        $query = $this->db->get();
        $rows = $query->result();
        if ($rows && count($rows) > 0) {
            return $rows[0];
        } else {
            return false;
        }
    }

    /**
     * get_all
     * 
     * Get ALL Agents for give Client
     *
     * @access (public) 
     * @param  type int $client_id
     * @return type array of stdClass Objetcs
     *
     */
    public function get_all($client_id) {
        $this->db->select("*");
        $this->db->from("crm_agents");
        $this->db->where('client_id', $client_id);
        $this->db->order_by('id');
        $query = $this->db->get();
        $rows = $query->result();
        if ($rows && count($rows) > 0) {
            return $rows[0];
        } else {
            return false;
        }
    }

    /**
     * set
     * 
     * Updates existing Agent info
     *
     * @access (public) 
     * @param  type stdClass $agent
     * @return type bool
     *      true:  agent updated success
     *      false: unable to update agent
     */
    public function set($agent) {
        if (!isset($agent->id) || $agent->id <= 0) {
            log_message('warning', __METHOD__ . ' No agent id provided: ' . $agent->id);
            return false;
        }
        $this->db->select("id");
        $this->db->from("crm_agents");
        $this->db->where('id', $agent->id);
        $query = $this->db->get();
        $row = $query->result();
        if (false == $row || count($row) == 0) {
            log_message('error', __METHOD__ . ' Agent to Update NOT FOUND with id: ' . $agent->id);
            return false;
        }
        $this->db->where('id', $agent->id);
        $this->db->where('client_id', $agent->client_id);
        $result = $this->db->update('crm_agents', $agent);
        if (false == $result) {
            log_message('error', __METHOD__ . ' Unable to update Agent with id: ' . $agent->id);
            return false;
        } else {
            return true;
        }
    }

    /**
     * delete
     * 
     * Deletes existing Agent
     *
     * @access (public) 
     * @param  type int $id (agent id)
     * @return type bool
     *      true:  agent updated success
     *      false: unable to update agent
     */
    public function delete($id) {
        if ($id == false || $id == '' || $id == 0) {
            return false;
        }
        $this->db->where('id', $id);
        $result = $this->db->delete('crm_agents');
        if (false == $result) {
            log_message('error', __METHOD__ . ' Unable to delete Agent with id: ' . $id);
            return false;
        } else {
            return true;
        }
    }
    /**
     * get_next_assignment_agent
     * 
     * Gets the next Agent id to be assingned secuentially
     *
     * @access (private) 
     * @param  type int $client_id (client id)
     * @return type int id of the agetnt to be assigned next
     *    
     */
    private function get_next_assignment_agent($client_id) {
        $max = (int) 0;
        $min = (int) PHP_INT_MAX;
        $assigned_agent_id = (int) 0;
        $agents = $this->get_active($client_id);
        if (count($agents) == 1) {
            return $agents->id;
        }
        foreach ($agents as $agent) {
            if ($agent->id > $max) {
                $max = $agent->id;
            }
            if ($agent->id < $min) {
                $min = $agent->id;
            }
        }
        $next = 0;
        $get_next = false;
        foreach ($agents as $agent) {
            if ($get_next == true) {
                $next = $agent->id;
                return $next;
            }
            if ($agent->last_assigned == 1) {
                $assigned_agent_id = $agent->id;
                $get_next = true;
            }
        }
        if ($assigned_agent_id == 0) {
            return 0;
        }
        if ($assigned_agent_id == $max) {
            return $min;
        }
        return $next;
    }

    /**
     * get_currently_assigned_agent
     * 
     * Gets the next Agent id to be assingned secuentially
     *
     * @access (private) 
     * @param  type int $client_id (client id)
     * @return type int $id of the agent to be assigned next
     *    
     **/
    private function get_currently_assigned_agent($client_id) {
        $assigned_agent_id = (int) 0;
        $agents = $this->get_active($client_id);
        foreach ($agents as $agent) {
            if ($agent->last_assigned == 1) {
                $assigned_agent_id = $agent->id;
                break;
            }
        }
        if ($assigned_agent_id === 0) {
        // Case when no agents have ever assigned yet (new client)
            $agent = $agents[0];
            $assigned_agent_id = $agent->id;
            $agent->last_assigned = 1;
            $this->set($agent);          
        }
        return $assigned_agent_id;
    }

    /**
     * assign_next_agent
     * 
     * Assigns new user to agent
     *
     * @access (public) 
     * @param  type string $user_email
     * @param  type int    $client_id
     * 
     * @return type stdClass $agent (object of agent who was assigned)
     *    
     **/
    public function assign_next_agent($user_email, $client_id) {
        $current_agent_id = $this->get_currently_assigned_agent($client_id);
        $next_agent_id    = $this->get_next_assignment_agent($client_id);

        if ($current_agent_id == 0 || false == $current_agent_id) {
            log_message('error', __METHOD__ . ' Unable to get currently assigned agent for client: ' . $client_id);
            return false;
        }
        if ($next_agent_id == 0 || false == next_agent_id) {
            log_message('error', __METHOD__ . ' Unable to get next agent to be assiged ' . $client_id);
            return false;
        }

        $current_agent = $this->get($current_agent_id);
        $next_agent    = $this->get($next_agent_id);

        if ($next_agent === FALSE) {
            log_message('error', __METHOD__ . ' ERROR ASSIGNING AGENT TO USER ' . $user_email);
            return false;
        }

        $current_agent->last_assigned = 0;
        $next_agent->last_assigned = 1;

        $this->db->where('id', $current_agent->id);
        $result = $this->db->update('crm_agents', $current_agent);
        if ($result === FALSE || $result == 0) {
            log_message('error', __METHOD__ . ' ERROR current agent update ' . $current_agent->email);
            return false;
        }
        $this->db->where('id', $next_agent->id);
        $result = $this->db->update('crm_agents', $next_agent);
        if ($result === FALSE || $result == 0) {
            log_message('error', __METHOD__ . ' next agent updated ' . $next_agent->email);
            return false;
        }
        return $next_agent;
    }

    /**
     * set_agent_to_user
     * 
     * Assigns agent to user
     *
     * @access (public) 
     * @param  type stdClass $agent_obj
     * @param  type string   $user_email
     * 
     * @return type boolean
     *    
     **/
    public function set_agent_to_user($agent_obj, $user_email) {
        if (!is_object($agent_obj)) {
            log_message('error', __METHOD__ . ' Bad input param, expecting stdClass object for agent. ');
            return false;
        }
        if ($user_email == '') {
            log_message('error', __METHOD__ . ' Bad input param, user email string, null given. ');
            return false;
        }
        $this->db->select("*");
        $this->db->from("rets_leads");
        $this->db->where('user_email', $user_email);
        $this->db->order_by('date', 'desc');
        $query = $this->db->get();
        $user_arr = $query->result();

        $user_arr[0]->owner = $agent_obj->email;
        $rets_lead = $user_arr[0];

        $this->db->where('id', $rets_lead->id);
        $result = $this->db->update('rets_leads', $rets_lead);
        if ($result === FALSE) {
            return false;
        } else if ($result == 0) {
            return false; // No error, but no rows were updated.
        } else {
            return true; // Updated the rows.
        }
    }

}
