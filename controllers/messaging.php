<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This controller handles requests for SMS/ Email alerts
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @subpackage Controllers
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Messaging_Controller extends Main_Controller {
	
	public function __construct()
	{
		parent::__construct();
	}

    public function index()
    {

		// First, are we allowed to subscribe for alerts via web?
		if ( ! Kohana::config('settings.allow_alerts'))
		{
			url::redirect(url::site().'main');
		}

		$this->template->header->this_page = $this->themes->this_page = 'alerts';
		$this->template->content = new View('sms_filter/alerts');
		
		// Load the alert radius map view
		$alert_radius_view = new View('sms_filter/alert_radius_view');
		$alert_radius_view->show_usage_info = TRUE;
		$alert_radius_view->enable_find_location = FALSE;

		$this->template->content->alert_radius_view = $alert_radius_view;


		// Display Mobile Option?
		$this->template->content->show_mobile = TRUE;
		$settings = ORM::factory('settings', 1);

		if ( ! Kohana::config("settings.sms_provider"))
		{
			// Hide Mobile
			$this->template->content->show_mobile = FALSE;
		}

		// Retrieve default country, latitude, longitude
		$default_country = Kohana::config('settings.default_country');

		// Retrieve Country Cities
		$this->template->content->cities = $this->_get_cities($default_country);

		// Get all active top level categories
		$this->template->content->categories = $this->get_categories('foo');

		// Setup and initialize form field names
		$form = array (
			'alert_mobile' => '',
			'alert_mobile_yes' => '',
			'alert_email' => '',
			'alert_email_yes' => '',
			'alert_rss' => '',
			'alert_lat' => '',
			'alert_lon' => '',
			'alert_radius' => '',
			'alert_country' => '',
			'alert_confirmed' => '',
      'radius' => '',
			'sectors' => array()
		);

		if ($this->user)
		{
			$form['alert_email'] = $this->user->email;
		}

		// Get Countries
		$countries = array();
		foreach (ORM::factory('country')->orderby('country')->find_all() as $country)
		{
			// Create a list of all countries
			$this_country = $country->country;
			if (strlen($this_country) > 35)
			{
				$this_country = substr($this_country, 0, 35) . "...";
			}
			$countries[$country->id] = $this_country;
		}

		//Initialize default value for Alert confirmed hidden value

		$this->template->content->countries = $countries;

		// Copy the form as errors, so the errors will be stored with keys
		// corresponding to the form field names
		$errors = $form;
		$form_error = FALSE;
		$form_saved = FALSE;

		// If there is a post and $_POST is not empty
		if ($post = $this->input->post())
		{
			$alert_orm = new SMS_Filter_Model();
			if ($alert_orm->validate($post))
			{
				// Yes! everything is valid
				// Save alert and send out confirmation code

				if ( ! empty($post->alert_mobile))
				{
					alert::_send_mobile_alert($post, $alert_orm);
					$this->session->set('alert_mobile', $post->alert_mobile);
				}

				if ( ! empty($post->alert_email))
				{
					alert::_send_email_alert($post, $alert_orm);
					$this->session->set('alert_email', $post->alert_email);
				}

				if ( ! empty($post->alert_rss))
        {
          // We need the categories and the region / sector
          $url = 'messaging/rss?';

          if (!empty($post->radius) && $post->radius) {
            $url .= 'alert_radius='. $post->alert_radius .
              '&alert_lon='. $post->alert_lon .
              '&alert_lat='. $post->alert_lat;
          } else if (!empty($post->sectors)) {
            $url .= '&sectors='. $post->sectors;
          }

          if (!empty($post->alert_category)) {
            $url .= '&alert_category='. implode(',', $post->alert_category);
          }

          //$this->session->set('rss_url', $url);
          url::redirect($url);
				}

				// If a region was specified use that
        if (
          !empty($post->radius) && 
          !$post->radius &&
          isset($post->sectors)
        ) {
					$alert_region = new Alert_Region_Model();
					$alert_region->region_id = $post->sectors;
					$alert_region->alert_id = $alert_orm->id;
					$alert_region->save();
				}

				url::redirect('alerts/confirm');                    
            }
            // No! We have validation errors, we need to show the form again, with the errors
            else
            {
				// repopulate the form fields
				$form = arr::overwrite($form, $post->as_array());

				// populate the error fields, if any
				$errors = arr::overwrite($errors, $post->errors('alerts'));
        if (array_key_exists('alert_recipient', $post->errors('alerts'))) {
          $errors = array_merge($errors, $post->errors('alerts'));
        }
				$form_error = TRUE;
      }
    } else {
			$form['alert_lat'] = Kohana::config('settings.default_lat');
			$form['alert_lon'] = Kohana::config('settings.default_lon');
			$form['alert_radius'] = 20;
			$form['radius'] = 0;
			$form['alert_category'] = array();
        }
        
		$form['sectors'] = ORM::factory('region')->select_list('id', 'geometry_label');
		$this->template->content->alert_region_view = new View('sms_filter/alert_sector_view');
		$this->template->content->alert_region_view->form = $form;

		$this->template->content->form_error = $form_error;
		// Initialize Default Value for Hidden Field Country Name, just incase Reverse Geo coding yields no result
		$form['alert_country'] = $countries[$default_country];
		$this->template->content->form = $form;
		$this->template->content->errors = $errors;
		$this->template->content->form_saved = $form_saved;

		// Javascript Header
		$this->themes->map_enabled = TRUE;
		$this->themes->js = new View('sms_filter/messaging_js');
		$this->themes->treeview_enabled = TRUE;
		$this->themes->js->default_map = Kohana::config('settings.default_map');
		$this->themes->js->default_zoom = Kohana::config('settings.default_zoom');
		$this->themes->js->latitude = $form['alert_lat'];
		$this->themes->js->longitude = $form['alert_lon'];
		$this->themes->js->radius = $form['radius'];
		$this->themes->js->geometries_hash = $this->_get_js_geometries_hash();

		// Rebuild Header Block
		$this->template->header->header_block = $this->themes->header_block();
		$this->template->footer->footer_block = $this->themes->footer_block();
    }


	/**
	 * Alerts Confirmation Page
	 */
	function confirm()
	{
		$this->template->header->this_page = 'alerts';
		$this->template->content = new View('alerts_confirm');

		$this->template->content->alert_mobile = (isset($_SESSION['alert_mobile']) AND ! empty($_SESSION['alert_mobile']))
			? $_SESSION['alert_mobile']
			: "";

		$this->template->content->alert_email = (isset($_SESSION['alert_email']) AND ! empty($_SESSION['alert_email']))
			? $_SESSION['alert_email']
			: "";

		// Display Mobile Option?
		$this->template->content->show_mobile = TRUE;
		$settings = ORM::factory('settings', 1);

		//if ( ! Kohana::config("settings.sms_provider"))
		if ( empty($_SESSION['alert_mobile']))
		{
			// Hide Mobile
			$this->template->content->show_mobile = FALSE;
		}

		// Rebuild Header Block
		$this->template->header->header_block = $this->themes->header_block();
		$this->template->footer->footer_block = $this->themes->footer_block();
	}


	/**
	 * Verifies a previously sent alert confirmation code
	 */
	public function verify()
	{
		// Define error codes for this view.
		define("ER_CODE_VERIFIED", 0);
		define("ER_CODE_NOT_FOUND", 1);
		define("ER_CODE_ALREADY_VERIFIED", 3);

		$code = (isset($_GET['c']) AND !empty($_GET['c'])) ? $_GET['c'] : "";

		$email = (isset($_GET['e']) AND !empty($_GET['e'])) ? $_GET['e'] : "";

		// INITIALIZE the content's section of the view
		$this->template->content = new View('alerts_verify');
		$this->template->header->this_page = 'alerts';

		$filter = " ";
		$missing_info = FALSE;

		if ($_POST AND isset($_POST['alert_code']) AND ! empty($_POST['alert_code']))
		{
			if (isset($_POST['alert_mobile']) AND ! empty($_POST['alert_mobile']))
			{
				$filter = "alert.alert_type=1 AND alert_code='".strtoupper($_POST['alert_code'])."' AND alert_recipient='".$_POST['alert_mobile']."' ";
			}
			elseif (isset($_POST['alert_email']) AND ! empty($_POST['alert_email']))
			{
				$filter = "alert.alert_type=2 AND alert_code='".$_POST['alert_code']."' AND alert_recipient='".$_POST['alert_email']."' ";
			}
			else
			{
				$missing_info = TRUE;
			}
		}
		else
		{
			if (empty($code) OR empty($email))
			{
				$missing_info = TRUE;
			}
			else
			{
				$filter = "alert.alert_type=2 AND alert_code='".$code."' AND alert_recipient='".$email."' ";
			}
		}

		if ( ! $missing_info)
		{
			$alert_check = ORM::factory('alert')
								->where($filter)
								->find();

			// IF there was no result
			if ( ! $alert_check->loaded)
			{
				$this->template->content->errno = ER_CODE_NOT_FOUND;
			}
			elseif ($alert_check->alert_confirmed)
			{
				$this->template->content->errno = ER_CODE_ALREADY_VERIFIED;
			}
			else
			{
				// SET the alert as confirmed, and save it
				$alert_check->set('alert_confirmed', 1)->save();
				$this->template->content->errno = ER_CODE_VERIFIED;
			}
		}
		else
		{
			$this->template->content->errno = ER_CODE_NOT_FOUND;
		}

		// Rebuild Header Block
		$this->template->header->header_block = $this->themes->header_block();
		$this->template->footer->footer_block = $this->themes->footer_block();
	} // END function verify


	/**
	 * Unsubscribes alertee using alertee's confirmation code
	 *
	 * @param string $code
	 */
	public function unsubscribe($code = NULL)
	{
		$this->template->content = new View('alerts_unsubscribe');
		$this->template->header->this_page = 'alerts';
		$this->template->content->unsubscribed = FALSE;

		// XXX Might need to validate $code as well
		if ($code != NULL)
		{
			Alert_Model::unsubscribe($code);
			$this->template->content->unsubscribed = TRUE;
		}

		// Rebuild Header Block
		$this->template->header->header_block = $this->themes->header_block();
		$this->template->footer->footer_block = $this->themes->footer_block();
    }



	private function _get_js_geometries_hash() {
	// Database object
		$db = new Database();
		$sql = 'SELECT AsText(geometry) tGeometry, region.* from region';
		$query = $db->query($sql);

		$js_array = "var geometries = new Object();\n";
		foreach ($query as $region) {
			$js_array .= "geometries[". $region->id ."] = {\n".
			"'label': '". $region->geometry_label ."',\n".
			"'comment': '". $region->geometry_comment."',\n".
			"'color': '". $region->geometry_color."',\n".
			"'strokewidth': ". $region->geometry_strokewidth.",\n".
			"'approved': ". $region->approved.",\n".
			"'geometry': '". $region->tGeometry."'};\n";
		}
		return $js_array;
	}



	/**
	 * Retrieves Previously Cached Geonames Cities
	 */
	private function _get_cities()
	{
		$cities = ORM::factory('city')->orderby('city', 'asc')->find_all();
		$city_select = array('' => Kohana::lang('ui_main.alerts_select_city'));

		foreach ($cities as $city)
		{
			$city_select[$city->city_lon.",".$city->city_lat] = $city->city;
		}
		return $city_select;
	}

  public function rss($feedtype = 'rss2') {

      if ($feedtype != 'atom' AND $feedtype!= 'rss2')
      {
          throw new Kohana_404_Exception();
      }

      // How Many Items Should We Retrieve?
      $limit = ( isset($_GET['l']) AND !empty($_GET['l']) AND (int) $_GET['l'] <= 200)
          ? (int) $_GET['l'] : 20;

      // Start at which page?
      $page = ( isset($_GET['p']) AND ! empty($_GET['p']) AND (int) $_GET['p'] >= 1 )
          ? (int) $_GET['p'] 
          : 1;
          
      $page_position = ($page == 1) ? 0 : ( $page * $limit ) ; // Query position

      $site_url = url::base();

      // Cache the Feed with subdomain in the cache name if mhi is set

      $subdomain = '';
      if(substr_count($_SERVER["HTTP_HOST"],'.') > 1 AND Kohana::config('config.enable_mhi') == TRUE)
      {
          $subdomain = substr($_SERVER["HTTP_HOST"],0,strpos($_SERVER["HTTP_HOST"],'.'));
      }

      /* We are depending on 3rd party sw for caching 
      $cache = Cache::instance();
      $feed_items = $cache->get($subdomain.'_feed_'.$limit.'_'.$page);
      
      if ($feed_items == NULL)
      { // Cache is Empty so Re-Cache
       */ 

      // How are we recieving categories so that we can filter by them?
      $db = new Database();
      $sql = 'SELECT incident.*,location.* FROM incident '.
        'LEFT JOIN incident_category ON (incident.id= incident_category.incident_id) '.
        'LEFT JOIN location ON (location.id = incident.location_id) '.
        'WHERE incident.incident_active = 1 ';

      if (
        !empty($_GET['alert_category']) &&
        preg_match('/\d+(,\d+)*/', $_GET['alert_category'])
      ) {
        $sql .= 'AND incident_category.category_id IN ('.$_GET['alert_category'].')';
      }
      $sql .= ' GROUP BY incident.id ORDER BY incident_date DESC';

      $query = $db->query($sql);

      Kohana::log('info', $sql);
      foreach($query as $incident)
      {

        // Pass off the geometry filtering 
        if ($this->_filtered_geometry($incident)) {
          continue;
        }

        $categories = Array();
        $sql = 'SELECT category_title FROM category '.
        'LEFT JOIN incident_category on (incident_category.category_id = category.id) '.
        'LEFT JOIN incident ON (incident_category.incident_id = incident.id) '.
        'WHERE incident.id = '.  $incident->id;
        $subquery = $db->query($sql);
        foreach ($subquery AS $category)
        {
          $categories[] = (string)$category->category_title;
        }
      
        $item = array();
        $item['id'] = $incident->id;
        $item['title'] = $incident->incident_title;
        $item['link'] = $site_url.'reports/view/'.$incident->id;
        $item['description'] = $incident->incident_description;
        $item['date'] = $incident->incident_date;
        $item['categories'] = $categories;
        
        if($incident->location_id != 0
            AND $incident->longitude
            AND $incident->latitude)
        {
                $item['point'] = array($incident->latitude,
                                        $incident->longitude);
                $items[] = $item;
        }
      }

      //$cache->set($subdomain.'_feed_'.$limit.'_'.$page, $items, array('feed'), 3600); // 1 Hour
      $feed_items = $items;
      //}

      $feedpath = $feedtype == 'atom' ? 'feed/atom/' : 'feed/';

      //header("Content-Type: text/xml; charset=utf-8");
      $this->template = new View('feed_'.$feedtype);
      $this->template->feed_title = htmlspecialchars(Kohana::config('settings.site_name'));
      $this->template->site_url = $site_url;
      $this->template->georss = 1; // this adds georss namespace in the feed
      $this->template->feed_url = $site_url.$feedpath;
      $this->template->feed_date = gmdate("D, d M Y H:i:s T", time());
      $this->template->feed_description = htmlspecialchars(Kohana::lang('ui_admin.incident_feed').' '.Kohana::config('settings.site_name'));
      $this->template->items = $feed_items;
      $this->template->render(TRUE);
      Kohana::log('info', 'feed_'.$feedtype);
  }

  private function _filtered_geometry($incident) {
    // We need an incident with lat and lon
    if (
      empty($incident->location->latitude) ||
      !is_float($incident->location->latitude) ||
      empty($incident->location->longitude) ||
      !is_float($incident->location->longitude)
    ) {
      // Return false if we do not have the right args cause we assume we just
      // did not recieve them
      return false;
    }

    // We need either (alert_lat && alert_lon && alert_radius) || sectors
    if (
      !empty($_GET['alert_radius']) &&
      is_int($_GET['alert_radius']) &&
      !empty($GET['alert_lat']) &&
      is_float($GET['alert_lat']) &&
      !empty($GET['alert_lon']) &&
      is_float($GET['alert_lon']) 
    ) {
      $distance = (string) new Distance(
        $_GET['alert_lat'], 
        $_GET['alert_lon'], 
        $incident->location->latitude, 
        $incident->location->longitude);
      return !($distance <= $_GET['alert_radius']);
    } else if (
      !empty($_GET['sectors']) &&
      is_int($_GET['sectors'])
    ) {
      return !Sector::incidentInRegion($incident->id, $_GET['sectors']);
    }
  }

}
