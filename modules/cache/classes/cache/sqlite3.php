<?php defined('SYSTEM_PATH') or die('No direct access');
/**
 * Cache Class
 *
 * This class handles caching of objects using the SQLite 3 database which is 
 * built into PHP 5.3 and uses a single database file in the site directory. 
 * For more information @see http://us2.php.net/manual/en/book.sqlite.php
 *
 * @package		MicroMVC
 * @author		David Pennington
 * @copyright	(c) 2010 MicroMVC Framework
 * @license		http://micromvc.com/license
 ********************************** 80 Columns *********************************
 */
class cache
{

	// SQLite database instance
	protected static $db;
	protected static $expires;

	
	/**
	 * Tests that the storage location is a directory and is writable.
	 */
	public function __construct($config)
	{
		// Make sure the cache directory is writable
		if ( ! is_dir(CACHE_PATH) OR ! is_writable(CACHE_PATH))
		{
			throw new Exception('cache directory is not writable');
		}
		
		// Location of the cache 
		$file = CACHE_PATH. 'cache.sqlite3';
		
		// Create the cache table in the database if it doesn't exist
		if ( ! is_file($file))
		{
			$install = 'CREATE TABLE caches(id VARCHAR(40) PRIMARY KEY,tags VARCHAR(255),expiration INTEGER,cache TEXT);';
		}
		
		// Open up an instance of the database
		self::$db = new PDO("sqlite:$file");
		
		// How long do caches last by default?
		self::$expires = $config['expires'];
		
		// Create the table
		if(isset($install))
		{
			self::$db->exec($install);
		}
		
		// Remove old cache files
		self::delete_expired();
	}

	
	/**
	 * Checks if a cache id is already set.
	 *
	 * @param string $id of the cache
	 * @return boolean
	 */
	public static function exists($id)
	{
		return (self::$db->query('SELECT COUNT(*) FROM caches WHERE id = \''. sha1($id). '\'')->fetchColumn() > 0);
	}
	

	/**
	 * Sets a cache item to the given data, tags, and lifetime.
	 *
	 * @param string $id of the cache
	 * @param mixed $data to store
	 * @param integer $lifetime of cache (0 = forever, NULL = default life, or # in seconds)
	 * @param array $tags of cache
	 * @return bool
	 */
	public static function set($id, $data, $lifetime = NULL, array $tags = NULL)
	{
		// Serialize and escape the data
		$data = self::$db->quote(serialize($data));

		// Escape the tags, adding brackets so the tag can be explicitly matched
		$tags = $tags ? self::$db->quote('<'.implode('>,<', $tags).'>') : '\'\'';

		// Cache Sqlite driver expects unix timestamp
		if($lifetime === NULL)
		{
			$lifetime = time() + self::$expires;
		}
		elseif ($lifetime !== 0)
		{
			$lifetime += time();
		}

		$query = self::exists($id)
			? "UPDATE caches SET tags = $tags, expiration = '$lifetime', cache = $data WHERE id = '". sha1($id). '\''
			: "INSERT INTO caches VALUES('". sha1($id). "', $tags, '$lifetime', $data)";

		// Run the query
		return (bool) self::$db->exec($query);
	}

	
	/**
	 * Finds an array of caches for a given tag.
	 *
	 * @param string $tag name
	 * @return array of caches that match the tag
	 */
	public static function find($tag)
	{
		$statement = self::$db->query("SELECT id,cache FROM caches WHERE tags LIKE '%<{$tag}>%'");

		// An array will always be returned
		$results = array();

		// Add each cache to the array
		while($row = $Statement->fetch(PDO::FETCH_CLASS))
		{
			$results[$row->id] = unserialize($row->cache);
		}

		return $results;
	}
	

	/**
	 * Fetches a cache item. This will delete the item if it is expired or if
	 * the hash does not match the stored hash.
	 *
	 * @param string $id of cache
	 * @return mixed|NULL
	 */
	public static function get($id)
	{
		// If found, return the cache
		if($query = self::$db->query('SELECT cache FROM caches WHERE id = \''. sha1($id). '\' LIMIT 1'))
		{
			return unserialize($query->fetchColumn());
		}
	}
	

	/**
	 * Deletes a cache item by id or tag
	 *
	 * @param  string  cache id or tag, or TRUE for "all items"
	 * @param  bool    delete a tag
	 * @return bool
	 */
	public static function delete($id = FALSE, $tag = FALSE)
	{
		// We are either deleting a cache by ID or by tag
		$where = $id ? 'id = \''. sha1($id). '\'' : "tags LIKE '%<{$tag}>%'";
		
		return self::$db->exec('DELETE FROM caches WHERE '.$where);
	}

	
	/**
	 * Deletes all cache files that are older than the current time.
	 */
	public function delete_expired()
	{
		return self::$db->exec('DELETE FROM caches WHERE expiration != 0 AND expiration <= '.time());
	}

}