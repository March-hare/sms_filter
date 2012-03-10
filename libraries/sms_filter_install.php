<?php
/**
 * Performs install/uninstall methods for the sms_filter plugin
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   March-Hare Communicationsd Collective <info@march-hare.org> 
 * @module	   SMS Filter Installer
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Sms_Filter_Install {

	/**
	 * Constructor to load the shared database library
	 */
	public function __construct()
	{
		$this->db = Database::instance();
	}

	/**
	 * Creates the required database tables for the sectors plugin
	 */
	public function run_install()
	{
		// Create the database tables.
		// Also include table_prefix in name
    $this->db->query(
      "CREATE TABLE IF NOT EXISTS `".Kohana::config('database.default.table_prefix')."alert_region` (".
      "`id` INT NOT NULL AUTO_INCREMENT,".
      "`alert_id` INT NOT NULL ,".
      "`region_id` INT NOT NULL ,".
      "PRIMARY KEY (`id`) ".
    ") ENGINE = MYISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");

    // Add an entry for the custom scheduler which replaces the builtin alert
    // scheduler
    $this->db->update('scheduler', array('scheduler_active' => 0), array('scheduler_name' => 'Alerts'));
    $s_alert = new Scheduler_Model();
    $s_alert->scheduler_name = 'SMS_Filter';
    $s_alert->scheduler_weekday = 
      $s_alert->scheduler_day =
      $s_alert->scheduler_hour =
      $s_alert->scheduler_minute = -1;
    $s_alert->scheduler_controller = 's_smsfilter';
    $s_alert->scheduler_active = 1;
    $s_alert->save();
	}

	/**
	 * Deletes the database tables for the sms_filter module
	 */
	public function uninstall()
  {
		$this->db->query('DROP TABLE `'.Kohana::config('database.default.table_prefix').'alert_region`');
    ORM::factory('scheduler')->where('scheduler_name', 'SMS_Filter')->delete();
    $this->db->update('scheduler', array('scheduler_active' => 1), array('scheduler_name' => 'Alerts'));
  }

}
