<?php defined('SYSPATH') or die('No direct script access.');

/**
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     March-Hare Communications Collective <info@march-hare.org> 
 * @subpackage Models
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Alert_Region_Model extends ORM
{
	protected $belongs_to = array('alert', 'region');
	
	// Database table name
	protected $table_name = 'alert_region';
}
