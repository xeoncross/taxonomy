<?php defined('SYSTEM_PATH') or die('No direct access');
/**
 * Taxonomy Model
 *
 * Extend this with your models to allow tagging. This tagging model is based
 * on the idea that you have a three-fold tag tracking system.
 * 
 * T = Tags
 * U = Users
 * O = Objects (e.g. "posts", "links", "books", etc..)
 * 
 * Most methods require that you specify one (or more) of T, U, or O so that
 * this model knows what columns to work from or on.
 *
 * @package		MicroMVC
 * @author		David Pennington
 * @copyright	(c) 2010 MicroMVC Framework
 * @license		http://micromvc.com/license
 ********************************** 80 Columns *********************************
 */
class Model_Taxonomy extends Database_ORM {

	// The name of the Tag class to use for results
	public $tag_model = 'Model_Tag';

	// The foreign_key name for the object table
	public $foreign_table_key = 'object_id';


	/**
	 * Clear the [Tag/User/Object] given of any relationships. (does not
	 * remove the actual [Tag/User/Object] though.
	 *
	 * @param string $type one of T/U/O (Tag/User/Object)
	 * @param int $id the $type's ID
	 * @return int
	 */
	public function clear_for($type = 'O', $id)
	{
		$fields = array(
			'T' => 'tag_id',  
			'U' => 'user_id',
			'O' => $this->foreign_table_key, //The "object_id" (e.g. "post_id")
		);
		
		// Remove all relations for this object
		$sql = 'DELETE FROM "'.$this->table.'" WHERE "'.$fields[$type].'" = ?';

		// Run the query
		$statement = $this->_db->query($sql, array($object->pk()));

		// Return number of rows removed
		return $statement->rowCount();
	}

	
	/**
	 * Returns an array of [tag/object/user] ids that match the pattern given.
	 * To allow many forms of tag results this method allows "patterns" to be
	 * given which will represent sorting order of the final SQL queries.
	 *
	 * T(U(i))	all tags of all users that have an item i
	 * T(U(t))	all tags of all users that have a tag t
	 * T(I(u))	all tags of all items of user u
	 * T(I(t))	all tags of all items that have tag t
	 *
	 * U(T(i))	all users of all tags of item i
	 * U(T(u))	all users of all tags of user u
	 * U(I(t))	all users of all items of tags t
	 * U(I(u))	all users of all items of user u
	 *
	 * I(U(t))	all items of all users of tag t
	 * I(U(i))	all items of all users of item i
	 * I(T(u))	all items of all tags of user u
	 * I(T(i))	all items of all tags of item i
	 *
	 * Even: T(U(T(u))) all tags of all users using any tags of user u!
	 * http://tagschema.com/blogs/tagschema/2005/10/many-dimensions-of-relatedness-in.html
	 *
	 * @param string $pattern the type of query to run
	 * @param int $id the starting [tag/user/object] id to base the query on
	 * @param int $limit the limit of ids to return
	 * @param int $offset the offset to start from
	 * @param bool $sort_by_usage (TRUE) sorts results by most repeated/used first
	 * @return array
	 */
	public function of($pattern, $id, $limit = 20, $offset = 0, $sort_by_usage = TRUE)
	{
		$fields = array(
			't' => 'tag_id',  
			'u' => 'user_id',
			'o' => $this->foreign_table_key, //The "object_id" (e.g. "post_id")
		);

		// Standard query
		$query = 'SELECT DISTINCT column FROM '.$this->table.' WHERE field clause';

		// Usage query
		$usage_query = 'SELECT COUNT(*) as "usage", column FROM '.$this->table.' WHERE field clause';

		// Clean, reverse, and convert to array
		$pattern = explode('(', str_replace(')', '', strtolower(strrev($pattern))));

		$sql = '';
		foreach($pattern as $key => $part)
		{
			// Indentation level
			$space = str_repeat("\t", count($pattern) - $key - 1);

			// Skip second element
			if($key === 1)
			continue;

			if($key === 0)
			{
				$search = array(
					'column' => $fields[$pattern[$key + 1]],
					'field' => $fields[$part],
					'clause' => '= ?'
					);
			}
			else
			{
				$search = array(
					'column' => $fields[$part],
					'field' => $fields[$pattern[$key - 1]],
					'clause' => "in \n$space(\n$sql\n$space)"
				);
			}

			// The last group gets similar added
			//if($sort_by_usage AND (empty($pattern[$key + 1]) OR ($key === 0 AND count($pattern) < 3)))
			if($sort_by_usage AND empty($pattern[$key + 1]))
			{
				//$search['column'] = '*';
				$search['clause'] .= ' AND '.$search['column'].' != ? GROUP BY '. $search['column']. ' ORDER BY "usage" DESC';
				$query = $usage_query;
			}

			// Build query
			$sql = $space. str_replace(array_keys($search), $search, $query);

		}

		// If a limit is given
		if($limit)
		{
			$sql .= "\nLIMIT ". ($offset ? $offset. ', ' : ''). $limit;
		}

		//print dump($sql);

		// Compile and execute the query using the ID given
		$statement = $this->_db->query($sql, array($id, $id));

		$ids = array();
		foreach($statement->results() as $row)
		{
			$ids[$row[$fields[$part]]] = $sort_by_usage ? $row['usage'] : NULL;
		}

		// Return the result ids
		return $ids;

	}


	/**
	 * Fetch the most popular T/U/O (Tag/User/Object) based on the
	 * number of entries.
	 *
	 * @param string $type one of T/U/O (Tag/User/Object)
	 * @param int $limit the SQL limit
	 * @param int $offset the SQL offset
	 * @param string $where the SQL where
	 * @return array of ids
	 */
	public function popular($type = 'T', $limit = 10, $offset = 0, $where = '')
	{
		$fields = array(
			'T' => 'tag_id',  
			'U' => 'user_id',
			'O' => $this->foreign_table_key,
		);

		// Build SQL
		$sql = 'SELECT '. $fields[$type].', COUNT(*) as count FROM '. $this->table
		.' GROUP BY '. $fields[$type].' ORDER BY count DESC '. $where
		. ($limit ? "\nLIMIT ". ($offset ? $offset. ', ' : ''). $limit : '');

		// Compile and execute the query using the ID given
		$statement = $this->_db->query($sql);

		// Run query
		$statement->execute();

		$ids = array();
		foreach($statement->results() as $row)
		{
			$ids[$row[$fields[$type]]] = $row['count'];
		}

		return $ids;
	}



	/**
	 * Fetch the most recent T/U/O (Tag/User/Object) in the system.
	 *
	 * @param string $type one of T/U/O (Tag/User/Object)
	 * @param int $limit the SQL limit
	 * @param int $offset the SQL offset
	 * @param string $where the SQL where
	 * @return array of ids
	 */
	public function recent($type = 'T', $limit = 10, $offset = 0, $where = '')
	{
		$fields = array(
			'T' => 'tag_id',  
			'U' => 'user_id',
			'O' => $this->foreign_table_key,
		);

		// Build SQL
		$sql = 'SELECT '. $fields[$type].', date FROM '. $this->table
		.' GROUP BY '. $fields[$type].' ORDER BY date DESC '. $where
		. ($limit ? "\nLIMIT ". ($offset ? $offset. ', ' : ''). $limit : '');

		// Compile and execute the query using the ID given
		$statement = $this->_db->query($sql);

		// Run query
		$statement->execute();

		$ids = array();
		foreach($statement->results() as $row)
		{
			$ids[$row[$fields[$type]]] = $row['date'];
		}

		return $ids;
	}


	/**
	 * Creates an HTML tag cloud using font-size to display a tag's importance.
	 *
	 * @param string $url
	 * @param int $limit
	 * @return string
	 */
	public function tag_cloud($url = '', $limit = 30)
	{
		// Fetch the most popular tags
		if( ! $tag_ids = $this->popular('T', $limit))
		return;

		// Start the tag cloud
		$html = '<div class="tag_cloud">';

		// Get the URL to append
		$url = site_url($url);

		// Convert count to percent sizes
		$tag_ids = $this->calculate_size($tag_ids);

		//Foreach tag found
		foreach ($tag_ids as $tag_id => $size)
		{
			$tag = new Model_Tag($tag_id);

			// Values to replace into the URL
			$replace = array($tag->id, urlencode($tag->tag));

			// Plug this tags ID into the URL
			$tag_url = str_replace(array('[[id]]', '[[tag]]'), $replace, $url);

			// Build the link
			$html .= '<span style="font-size:'. $size.'%"><a class="tag" href="'.$tag_url.'/">'
					.$tag->tag."</a></span>\n";

		}

		return $html. '</div>';
	}

	
	/**
	 * Caculate the Tag/User/Object size based on max/min row usage.
	 *
	 * @param array $rows the array of id=>count
	 * @param int $max the max size
	 * @param int $min the min size
	 * @return array
	 */
	function calculate_size($rows, $max = 250, $min = 100)
	{
		$min_qty = min($rows);
		$max_qty = max($rows);

		// Must be at least 1
		if(($spread = $max_qty - $min_qty) === 0)
		{
			$spread = 1;
		}

		$step = ($max - $min) / $spread;

		foreach($rows as $id => $count)
		{
			// Do the math to slowly slope each tags size
			$rows[$id] = ceil($min + (($count - $min_qty) * $step));
		}

		return $rows;
	}


	/**
	 * We only want one record per user/item/tag combo. So
	 * before we allow any inserts, we must make sure that
	 * an entry doesn't already exist!
	 */
	protected function insert(array $data = NULL)
	{
		// Get the foreign key
		$key = $this->foreign_table_key;

		// Save the date of this tag
		$data['date'] = date('Y-m-d h:i:s');

		// If a user is logged in - try to auto-assign the tag to them!
		if( ! isset($data['user_id']) AND session('user_id'))
		{
			$data['user_id'] = session('user_id');
		}

		// Build the query
		$sql = 'SELECT id FROM '.$this->table.' WHERE tag_id = ? AND '.$key.' = ?';

		// Build the params
		$params = array($data['tag_id'], $data[$key]);

		// If a user is set - assign the tag to them!
		if( ! empty($data['user_id']))
		{
			$sql .= 'AND user_id = ?';
			$params[] = $data['user_id'];
		}

		// If an entry was not found
		if( ! $id = $this->_db->query($sql, $params)->fetchColumn())
		{
			// Create a one!
			$id = $this->_db->insert($this->table, $data);
		}

		// Load the insert id as the primary key
		$this->_object[$this->_primary_key] = $id;

		// Object is now loaded and saved
		$this->loaded = $this->saved = TRUE;
	}

}
