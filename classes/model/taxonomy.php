<?php defined('SYSTEM_PATH') or die('No direct access');
/**
 * Taxonomy Model
 *
 * Extend this with your models to allow tagging.
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
	 * Returns an array of similar tags ordered by weight
	 * 
	 * @param int $tag_id
	 * @return array
	 */
	public function similar_tags($tag_id)
	{
		
		// Build the SQL String
		$sql = 'SELECT o1.*, t1.*, COUNT( o1.'.$this->foreign_table_key.' ) AS similar'
		. ' FROM '.$this->table.' AS o1'
		. ' INNER JOIN tags AS t1 ON ( t1.id = o1.tag_id )'
		. ' INNER JOIN '.$this->table.' AS o2 '
		. ' ON ( o1.'.$this->foreign_table_key.' = o2.'.$this->foreign_table_key.' )'
		. ' INNER JOIN tags AS t2 ON ( t2.id = o2.tag_id )'
		. ' WHERE t2.id = ? AND t1.id != ?'
		. ' GROUP BY o1.tag_id'
		. ' ORDER BY similar DESC';
		
		// Run the query
		$statement = $this->_db->query($sql, array($tag_id, $tag_id));
		
		// Return an array of tags LIKE the tag given
		return $statement->results($this->tag_model, 'id');
	}
	
	
	/**
	 * Returns an array of object IDs that share similar tags.
	 * This is useful in finding "related" objects like "posts"
	 * which often use some of the same tags. The result is a
	 * 
	 * 
	 * @param int $object_id The ID of the object row
	 * @param array $tag_ids An array of tag ID's from the object row
	 * @param int $similar The number of tags they must share to be "similar"
	 * @return array
	 */
	public function similar_objects($object_id, array $tag_ids = NULL, $similar = 1)
	{
		// Get the foreign key to save space below
		$key = $this->foreign_table_key;
		
		// Build the SQL String
		$sql = 'SELECT '.$key.', COUNT(*) AS similar'
		. ' FROM '. $this->table
		. ' WHERE tag_id IN ('. implode(',', $tag_ids). ')'
		. ' AND '.$key.' != ?'
		. ' GROUP BY '.$key
		. ' HAVING similar >= ?'
		. ' ORDER BY similar DESC';
		
		// Run the query
		$statement = $this->_db->query($sql, array($object_id, $similar));
		
		// Fetch object IDs LIKE the object ID given
		if($results = $statement->results())
		{
			// Sort each array into ID = rating
			foreach($results as $result)
			{
				$related[$result[$key]] = $result['similar'];
			}
			
			// Return array
			return $related;
		}
	}
	

	/**
	 * Get the most popular tags (sorted by usage)
	 * 
	 * @param int $limit the max number of results
	 * @return array
	 */
	public function most_popular_tags($limit = 10)
	{
		// Build the query
		$sql = 'SELECT t2.*, t1.*, COUNT(*) as count'
		. ' FROM tags AS t1'
		. ' INNER JOIN '.$this->table.' AS t2 ON (t1.id = t2.tag_id)'
		. ' GROUP BY t1.id'
		. ' ORDER BY count DESC, t1.id ASC'
		. ' LIMIT '. $limit;
		
		// Compile the query
		$statement = $this->_db->query($sql);
		
		// Run the query
		$statement->execute();
		
		// Return the results
		return $statement->results($this->tag_model);
	}
	
	
	
	public function tag_cloud($url = '')
	{
		// Fetch the most popular tags
		if( ! $tags = $this->most_popular_tags())
			return;
		
		// Count the rows
		$count = count($tags);

		// Start the tag cloud
		$html = '<div class="tag_cloud">';

		// Get the URL to append
		$url = site_url($url);

		//Foreach tag found
		foreach ($tags as $tag)
		{
			// Difference
			$int = $tag->count/$count;
			
			//$class = ($int > .85 ? 'x-large' : ($int > .60 ? 'large' : ($int > .40 ? 'medium' : ($int > .20 ? 'small' : 'x-small')))))
			
			//If this tag is used very often
			if($int > 0.85)
			{
				$class="x-large";
			}
			elseif ($int > 0.60)
			{
				$class="large";
			}
			elseif ($int > 0.40)
			{
				$class="medium";
			}
			elseif ($int > 0.20)
			{
				$class="small";
			}
			else
			{
				$class="x-small";
			}
			
			// Values to replace into the URL
			$replace = array($tag->id, urlencode($tag->tag));
			
			// Plug this tags ID into the URL
			$tag_url = str_replace(array('[[id]]', '[[tag]]'), $replace, $url);

			// Build the link
			$html .= '<span><a class="tag-'.$class.'" href="'.$tag_url.'/">'.$tag->tag.'</a></span>';

		}

		//Return the tags
		return $html. '</div>';
		
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


