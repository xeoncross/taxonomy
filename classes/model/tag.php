<?php
class Model_Tag extends Database_ORM {

	
	/**
	 * Convert the a tag string into an array of tags. 
	 * 
	 * @param string $string the comma separated string of tags
	 * @return array
	 */
	public function process_tag_string($string = '')
	{
		// Don't allow tagging strings over 300 characters long
		if(mb_strlen($string) > 300)
		{
			return array();
		}
		
		// Replace multiple spaces and removing start/end space
		$string = trim(preg_replace('/\s\s+/u', ' ', $string));
		
		// Make the string lowercase (if posible)
		$string = mb_strtolower($string);
		
		// Split the text into an array
		$strings = explode(',', $string);
		
		$tags = array();
		foreach($strings as $string)
		{
			// If this is an empty string
			if( ! $string = trim($string))
				continue;
			
			// Convert underscores and spaces to dashes ( "-" for SEO!)
			$string = str_replace(array('_', ' '), '-', $string);
			
			// Remove all remaining non-word characters
			$string = preg_replace('/[^\w\.\-#+]+/u', '', $string);
		
			// If this is an empty string (or non-word character!)
			if( ! $string OR ! preg_match('/\w/u', $string))
				continue;
			
			// Must be a valid tag
			$tags[] = $string;
		}
		
		return $tags;
	}
	
	
	/**
	 * We only want one record per tag combo. So before we 
	 * allow any inserts, we must make sure that an entry 
	 * doesn't already exist!
	 */
	protected function insert(array $data = NULL)
	{
		// All we need is the tag
		$tag = $data['tag'];
		
		// Build the query
		$sql = 'SELECT id FROM '.$this->table.' WHERE tag = ?';
		
		// If an entry was not found
		if( ! $id = $this->_db->query($sql, array($tag))->fetchColumn())
		{
			// Create a one!
			$id = $this->_db->insert($this->table, array('tag' => $tag));
		}
		
		// Load the insert id as the primary key
		$this->_object[$this->_primary_key] = $id;
	
		// Object is now loaded and saved
		$this->loaded = $this->saved = TRUE;
	}
	
	
	/**
	 * Updating tag might result in duplicate tags in the 
	 * database with the same name. This would cause a loss
	 * of data integrity. Therefore, you can only create 
	 * and delete tags.
	 */
	protected function update(array $data = NULL)
	{
		throw new Exception('To maintain data integrity, you may not update tags.');
	}
	
}
