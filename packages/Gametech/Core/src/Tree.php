<?php

namespace Gametech\Core;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

class Tree {

    /**
     * Contains tree item
     *
     * @var array
     */
	public $items = [];

    /**
     * Contains acl roles
     *
     * @var array
     */
	public $roles = [];

    /**
     * Contains current item route
     *
     * @var string
     */
	public $current;

    /**
     * Contains current item key
     *
     * @var string
     */
	public $currentKey;

    /**
     * Contains current item key
     *
     * @var string
     */
    public $currentName;

    /**
     * Contains current item key
     *
     * @var string
     */
    public $currentRoute;

    /**
     * Create a new instance.
     *
     * @return void
     */
	public function __construct()
	{
		$this->current = Request::url();
	}

    /**
     * Shortcut method for create a Config with a callback.
     * This will allow you to do things like fire an event on creation.
     *
     * @param null $callback Callback to use after the Config creation
     * @return object
     */
	public static function create($callback = null)
	{
		$tree = new Tree();

		if ($callback) {
			$callback($tree);
		}

		return $tree;
	}

    /**
     * Add a Config item to the item stack
     *
     * @param array $item
     * @param string $type
     * @return void
     */
	public function add(array $item, $type = '')
	{
        $item['children'] = [];

		if ($type == 'menu') {
            $item['url'] = route($item['route'], $item['params'] ?? []);

            if (strcmp($this->current, $item['url']) === 0) {
                $this->currentKey = $item['key'];
                $this->currentName = $item['name'];
                $this->currentRoute = $item['key'];
            }

            $keys = explode('.', $this->currentKey);
            if(isset($keys) && isset($keys[1])){
                $this->currentRoute = $keys[1];
//                $this->currentKey = $keys[1];
            }

		} elseif ($type == 'acl') {
//			$item['name'] = $item['name'];

			$this->roles[$item['route']] = $item['key'];
		}

		$children = str_replace('.', '.children.', $item['key']);

		core()->array_set($this->items, $children, $item);
	}

	/**
	 * Method to find the active links
	 *
	 * @param  array  $item
	 * @return string|void
	 */
	public function getActive(array $item)
	{
		$url = trim($item['url'], '/');

        if ((strcmp($this->current, $url) === 0) || (strcmp($this->currentKey, $item['key']) === 0)) {
            return 'active';
        }

//        $keys = explode('.', $this->currentKey);
//        if(isset($keys) && !empty($keys[0]) && !empty($keys[1])){
//            if ((strcmp($this->current, $url) === 0) && (strpos($keys[1], $item['key']) === 0)) {
//                return 'active';
//            }
//        } elseif (isset($keys) && !empty($keys[0])){
//            if ((strcmp($this->current, $url) !== 0) && (strpos($keys[0], $item['key']) === 0)) {
//                return 'active';
//            }
//        } else {
//            if ((strcmp($this->current, $url) !== 0) && (strcmp($this->currentKey, $item['key']) === 0)) {
//                return 'active';
//            }
//        }

	}

    public function getActives(array $item)
    {
        $url = trim($item['url'], '/');

        if ((strcmp($this->current, $url) === 0) || (strcmp($this->currentKey, $item['key']) === 0)) {
            return 'menu-open';
        }


    }

    public function getFrontActive($route)
    {
        $name = Route::currentRouteName();

        if($route === $name){
            return 'active';
        }

    }


}
