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

class SMS_Filter_Model extends Alert_Model {

	/**
	 * Model Validation
	 * 
	 * @param array $array values to check
	 * @param boolean $save save[Optional] the record when validation succeeds
	 * @return bool TRUE when validation succeeds, FALSE otherwise
	 */
	public function validate(array & $post, $save = FALSE)
  {
    $postarg = $post;
		// Initialise the validation library and setup some rules
		$post = Validation::factory($post)
			->pre_filter('trim')
			->add_rules('alert_mobile', 'numeric', 'length[6,20]')
			->add_rules('alert_email', 'email', 'length[3,64]')
			->add_rules('alert_lat', 'required', 'between[-90,90]')
			->add_rules('alert_lon', 'required', 'between[-180,180]')
			->add_rules('alert_radius','required','in_array[1,5,10,20,50,100]');
				
		// TODO Callbacks to check for duplicate alert subscription - same
		// subscriber for the same lat/lon
		//$post->add_callbacks('alert_mobile', array($this, '_mobile_check'));
		//$post->add_callbacks('alert_email', array($this, '_email_check'));

		// Check if a recipient mobile phone no. or email address has been
		// specified	
    if (
      empty($post->alert_mobile) AND 
      empty($post->alert_email) AND 
      empty($post->alert_rss)
    )
    {
			$post->add_rules('alert_recipient', 'required');
		}

		//return parent::validate($postarg, $save);
		return ORM::validate($post, $save);

		
	} // END function validate
}
