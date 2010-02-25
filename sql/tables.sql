--
-- Table structure for table `tags`
--

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

--
-- Table structure for table `table_name_tags`
--

CREATE TABLE IF NOT EXISTS `[table_name]_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_id` int(10) unsigned NOT NULL,
  `[column_name]_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;

/* 
 * Duplicate this table each type of item you are tracking.
 * 
 * posts = post_tags
 * videos = video_tags
 * users = user_tags
 *
 * Each of these tables will still share the ONE tags table.
 */