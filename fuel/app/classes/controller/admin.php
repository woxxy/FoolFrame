<?php

class Controller_Admin extends Controller_Common
{

	protected $_views = null;
	private static $sidebar = array();
	private static $sidebar_dynamic = array();

    public function before()
    {
		parent::before();

		if(!Auth::has_access('maccess.user') && \Uri::segment(2) != 'auth')
			return Response::redirect('admin/auth/login');

		// returns the static sidebar array (can't use functions in )
		self::$sidebar = static::get_sidebar_values();

		// get the plugin sidebars
		self::$sidebar_dynamic = \Plugins::get_sidebar_elements('admin');
		
		// merge if there were sidebar elements added dynamically
		if (!empty(self::$sidebar_dynamic))
		{
			self::$sidebar = self::merge_sidebars(self::$sidebar, self::$sidebar_dynamic);
		}

		$this->_views['navbar'] = View::forge('admin/navbar');
		$this->_views['sidebar'] = View::forge('admin/sidebar', array('sidebar' => self::get_sidebar(self::$sidebar)));
	}

    public function action_index()
    {
		return Response::redirect('admin/preferences/theme');
    }


	public function action_404()
	{
		return Response::forge('404', 404);
	}


	/**
	 * Non-dynamic sidebar array.
	 * Permissions are set inside
	 *
	 * @return sidebar array
	 */
	private static function get_sidebar_values()
	{

		$sidebar = array();

		// load sidebars from modules and leave FoolFrame sidebar on bottom
		foreach(\Config::get('foolframe.modules.installed') as $module)
		{
			$module_sidebar = \Config::load($module.'::sidebar', $module.'_sidebar');
			if(is_array($module_sidebar))
			{
				$sidebar = array_merge($module_sidebar['sidebar'], $sidebar);
			}
		}

		$sidebar["auth"] = array(
			"name" => __("Account"),
			"level" => "user",
			"default" => "change_email",
			"content" => array(
				"profile" => array("level" => "user", "name" => __("Profile"), "icon" => 'icon-user'),
				"change_email_request" => array("level" => "user", "name" => __("Change Email"), "icon" => 'icon-envelope'),
				"change_password_request" => array("level" => "user", "name" => __("Change Password"), "icon" => 'icon-lock'),
				"delete_account_request" => array("level" => "user", "name" => __("Delete Account"), "icon" => 'icon-remove-circle'),
			)
		);

		$sidebar["users"] = array(
			"name" => __("Users"),
			"level" => "mod",
			"default" => "users",
			"content" => array(
				"manage" => array("alt_highlight" => array("member"),
					"level" => "mod", "name" => __("Manage"), "icon" => 'icon-user'),
			)
		);

		$sidebar["preferences"] = array(
			"name" => __("Preferences"),
			"level" => "admin",
			"default" => "general",
			"content" => array(
				"theme" => array("level" => "admin", "name" => __("Theme"), "icon" => 'icon-picture'),
				"registration" => array("level" => "admin", "name" => __("Registration"), "icon" => 'icon-book'),
				"advertising" => array("level" => "admin", "name" => __("Advertising"), "icon" => 'icon-lock'),
			)
		);

		$sidebar["system"] = array(
			"name" => __("System"),
			"level" => "admin",
			"default" => "system",
			"content" => array(
				"information" => array("level" => "admin", "name" => __("Information"), "icon" => 'icon-info-sign'),
				"preferences" => array("level" => "admin", "name" => __("Preferences"), "icon" => 'icon-check'),
				"upgrade" => array("level" => "admin", "name" => __("Upgrade") . ((Preferences::get('ff.cron_autoupgrade_version') && version_compare(\Config::get('foolframe.main.version'),
						Preferences::get('ff.cron_autoupgrade_version')) < 0) ? ' <span class="label label-success">' . __('New') . '</span>'
							: ''), "icon" => 'icon-refresh'),
			)
		);

		$sidebar["plugins"] = array(
			"name" => __("Plugins"),
			"level" => "admin",
			"default" => "manage",
			"content" => array(
				"manage" => array("level" => "admin", "name" => __("Manage"), "icon" => 'icon-gift'),
			)
		);

		$sidebar["meta"] = array(
			"name" => "Meta", // no gettext because meta must be meta
			"level" => "member",
			"default" => "http://ask.foolrulez.com",
			"content" => array(
				"https://github.com/FoOlRulez/FoOlFuuka/issues" => array("level" => "member", "name" => __("Bug tracker"), "icon" => 'icon-exclamation-sign'),
			)
		);

		return $sidebar;
	}


	/**
	 * Sets new sidebar elements, the array must match the defaults' structure.
	 * It can override the methods.
	 *
	 * @param array $array
	 */
	public static function add_sidebar_element($array)
	{
		if (is_null(static::$sidebar_dynamic))
		{
			static::$sidebar_dynamic = array();
		}

		static::$sidebar_dynamic[] = $array;
	}


	/**
	 * Merges without destroying twi sidebars, where $array2 overwrites values of
	 * $array1.
	 *
	 * @param array $array1 sidebar array to be merged into
	 * @param array $array2 sidebar array with elements to merge
	 * @return array resulting sidebar
	 */
	public static function merge_sidebars($array1, $array2)
	{
		// there's a numbered index on the outside!
		foreach ($array2 as $key_top => $item_top)
		{
			foreach($item_top as $key => $item)
			{
				// are we inserting in an already existing method?
				if (isset($array1[$key]))
				{
					// overriding the name
					if (isset($item['name']))
					{
						$array1[$key]['name'] = $item['name'];
					}

					// overriding the permission level
					if (isset($item['level']))
					{
						$array1[$key]['level'] = $item['level'];
					}

					// overriding the default url to reach
					if (isset($item['default']))
					{
						$array1[$key]['default'] = $item['default'];
					}

					// overriding the default url to reach
					if (isset($item['icon']))
					{
						$array1[$key]['icon'] = $item['icon'];
					}

					// adding or overriding the inner elements
					if (isset($item['content']))
					{
						if (isset($array1[$key]['content']))
						{
							$array1[$key]['content'] = self::merge_sidebars($array1[$key]['content'], $item);
						}
						else
						{
							$array1[$key]['content'] = self::merge_sidebars(array(), $item);
						}
					}
				}
				else
				{
					// the element doesn't exist at all yet
					// let's trust the plugin creator in understanding the structure
					// extra control: allow him to put the plugin after or before any function
					if (isset($item['position']) && is_array($item['position']))
					{
						$before = $item['position']['beforeafter'] == 'before' ? TRUE : FALSE;
						$element = $item['position']['element'];

						$array_temp = $array1;
						$array1 = array();
						foreach ($array_temp as $subkey => $temp)
						{
							if ($subkey == $element)
							{
								if ($before)
								{
									$array1[$key] = $item;
									$array1[$subkey] = $temp;
								}
								else
								{
									$array1[$subkey] = $temp;
									$array1[$key] = $item;
								}

								unset($array_temp[$subkey]);

								// flush the rest
								foreach ($array_temp as $k => $t)
								{
									$array1[$k] = $t;
								}

								break;
							}
							else
							{
								$array1[$subkey] = $temp;
								unset($array_temp[$subkey]);
							}
						}
					}
					else
					{
						$array1[$key] = $item;
					}
				}
			}
		}

		return $array1;
	}


	/**
	 * Returns the sidebar array
	 *
	 * @todo comment this
	 */
	public static function get_sidebar($array)
	{
		// not logged in users don't need the sidebar
		if (Auth::member('guest'))
			return array();

		$result = array();
		foreach ($array as $key => $item)
		{
			if (\Auth::has_access('maccess.' . $item['level']) && !empty($item))
			{
				$subresult = $item;

				// segment 2 contains what's currently active so we can set it lighted up
				if (Uri::segment(2) == $key)
				{
					$subresult['active'] = TRUE;
				}
				else
				{
					$subresult['active'] = FALSE;
				}

				// we'll cherry-pick the content next
				unset($subresult['content']);

				// recognize plain URLs
				if ((substr($item['default'], 0, 7) == 'http://') ||
					(substr($item['default'], 0, 8) == 'https://'))
				{
					// nothing to do here, just copy the URL
					$subresult['href'] = $item['default'];
				}
				else
				{
					// else these are internal URIs
					// what if it uses more segments or is even an array?
					if (!is_array($item['default']))
					{
						$default_uri = explode('/', $item['default']);
					}
					else
					{
						$default_uri = $item['default'];
					}
					array_unshift($default_uri, 'admin', $key);
					$subresult['href'] = Uri::create(implode('/', $default_uri));
				}

				$subresult['content'] = array();

				// cherry-picking subfunctions
				foreach ($item['content'] as $subkey => $subitem)
				{
					$subsubresult = array();
					$subsubresult = $subitem;
					if (\Auth::has_access('maccess.' . $subitem['level']))
					{
						if ($subresult['active'] && (Uri::segment(3) == $subkey ||
							(
							isset($subitem['alt_highlight']) &&
							in_array(Uri::segment(3), $subitem['alt_highlight'])
							)
							))
						{
							$subsubresult['active'] = TRUE;
						}
						else
						{
							$subsubresult['active'] = FALSE;
						}

						// recognize plain URLs
						if ((substr($subkey, 0, 7) == 'http://') ||
							(substr($subkey, 0, 8) == 'https://'))
						{
							// nothing to do here, just copy the URL
							$subsubresult['href'] = $subkey;
						}
						else
						{
							// else these are internal URIs
							// what if it uses more segments or is even an array?
							if (!is_array($subkey))
							{
								$default_uri = explode('/', $subkey);
							}
							else
							{
								$default_uri = $subkey;
							}
							array_unshift($default_uri, 'admin', $key);
							$subsubresult['href'] = Uri::create(implode('/', $default_uri));
						}

						$subresult['content'][] = $subsubresult;
					}
				}

				$result[] = $subresult;
			}
		}
		return $result;
	}


}