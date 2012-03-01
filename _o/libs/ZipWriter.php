<?php

/**
 * This class can generate zip files fast by using store instead of deflate
 * compression or using a local cache file with pre compressed items.
 */
class Moxiecode_ZipWriter {
	private $compressionLevel, $entries, $cache, $entryLookup;

	/**
	 * Constructs a new ZipWriter instance.
	 */
	public function __construct() {
		$this->compressionLevel = 9;
		$this->entries = array();
		$this->cache = array();
		$this->entryLookup = array();
	}

	/**
	 * Sets the compression level 0 equals simple storage and 9 is the maximum compression.
	 */
	public function setCompressionLevel($level) {
		$this->compressionLevel = $level;
	}

	/**
	 * Builds up a cache of the specified directory into the specified cache file. If the cache file already exists
	 * it will simply load it into memory and use it to boost performance.
	 */
	public function buildCache($path, $cache_file) {
		// Load cache file
		if (file_exists($cache_file)) {
			$this->cache = unserialize(file_get_contents($cache_file));
			return;
		}

		$cacheFile = fopen($cache_file, "wb");
		if ($cacheFile) {
			if (flock($cacheFile, LOCK_EX)) {
				// Build cache file
				$files = $this->listTree($path);

				foreach ($files as $file) {
					if (is_file($file)) {
						$data = file_get_contents($file);
						$size = strlen($data);
						$crc32 = crc32($data);
						$data = @gzdeflate($data, $this->compressionLevel);

						$this->cache[$file] = array(
							$size,
							$crc32,
							filemtime($file),
							$data
						);
					} else {
						$this->cache[$file] = filemtime($file);
					}
				}

				fwrite($cacheFile, serialize($this->cache));
				flock($cacheFile, LOCK_UN);
			}

			fclose($cacheFile);
		}
	}

	/**
	 * Adds a file to the zip.
	 */
	public function addFile($zip_path, $path) {
		if (isset($this->cache[$path])) {
			if (!is_array($this->cache[$path])) {
				$this->addDirectory($zip_path, $path);
				return;
			}
		} else {
			if (is_dir($path)) {
				$this->addDirectory($zip_path, $path);
				return;
			}
		}

		$this->addEntry($zip_path, array("file" => $path));
	}

	/**
	 * Adds a directory to the zip.
	 */
	public function addDirectory($zip_path, $path = null) {
		if ($path) {
			// List files from cache
			if (isset($this->cache[$path])) {
				foreach (array_keys($this->cache) as $file) {
					if (strpos($file, $path) === 0) {
						$this->addEntry($this->combine($zip_path, substr($file, strlen($path))), array("file" => $file));
					}
				}

				return;
			}

			// List files from disk
			$files = $this->listTree($path);

			foreach ($files as $file) {
				$this->addEntry($this->combine($zip_path, substr($file, strlen($path))), array("file" => $file));
			}
		} else {
			$this->addEntry($zip_path);
		}
	}

	/**
	 * Returns the zip as a string that can be stored or streamed.
	 */
	public function toString() {
		$zipData = "";
		$entries = $this->entries;
		$cache = $this->cache;

		foreach ($entries as &$entry) {
			$compressionMethod = $size = $compressedSize = $crc32 = 0;
			$data = "";

			if (isset($entry["file"])) {
				$localPath = $entry["file"];

				if (isset($this->cache[$localPath])) {
					$cachedItem = $this->cache[$localPath];

					if (is_array($cachedItem)) {
						$isFile = true;
						$size = $cachedItem[0];
						$crc32 = $cachedItem[1];
						$modificationDate = $cachedItem[2];
						$data = $cachedItem[3];
						$compressedSize = strlen($data);
						$compressionMethod = 0x0008;
					} else {
						$isFile = false;
						$modificationDate = $cachedItem;
					}

					$modificationDate = $cachedItem[0];

					// Clear the cache to redunce memory needs
					$this->cache[$localPath] = null;
				} else {
					$isFile = is_file($localPath);
					$modificationDate = filemtime($localPath);

					if ($isFile) {
						$data = file_get_contents($localPath);
						$size = strlen($data);
						$crc32 = crc32($data);

						if ($this->compressionLevel > 0) {
							$data = @gzdeflate($data, $this->compressionLevel);
							$compressionMethod = 0x0008;
						} else
							$compressionMethod = 0x0000; // Store

						$compressedSize = strlen($data);
					}
				}
			} else {
				$modificationDate = time();
				$isFile = false;
			}

			// Convert unix time to dos time
			$date = getdate($modificationDate);
			$mtime = ($date['hours'] << 11) + ($date['minutes'] << 5) + $date['seconds'] / 2;
			$mdate = (($date['year'] - 1980) << 9) + ($date['mon'] << 5) + $date['mday'];

			// Setup filename
			$fileName = $entry["path"];

			if (!$isFile)
				$entry["path"] = $fileName = $fileName . "/";

			$fileNameLength = strlen($fileName);

			// Setup extra field
			$extra = $entry["extra"];
			$extraLength = strlen($extra);
			$entry["offset"] = strlen($zipData);

			// Write local file header
			$zipData .= pack("VvvvvvVVVvv",
				0x04034b50,					// Local File Header Signature 
				0x0014,						// Version needed to extract
				0x0002,						// General purpose bit flag 
				$compressionMethod,			// Compression method (deflate)
				$mtime,						// Last mod file time (MS-DOS)
				$mdate,						// Last mod file date (MS-DOS)
				$crc32,						// CRC-32
				$compressedSize,			// Compressed size
				$size,						// Uncompressed size
				$fileNameLength,			// Filename length
				$extraLength				// Extra field length
			);

			// Write variable data
			$zipData .= $fileName;
			$zipData .= $extra;
			$zipData .= $data;

			$entry["cmethod"] = $compressionMethod;
			$entry["mtime"] = $mtime;
			$entry["mdate"] = $mdate;
			$entry["crc32"] = $crc32;
			$entry["csize"] = $compressedSize;
			$entry["size"] = $size;
			$entry["eattr"] = $isFile ? 0x00000020 : 0x00000030;
		}

		$startOffset = strlen($zipData);
		$centralDirSize = 0;

		// Write central directory information
		foreach ($entries as &$entry) {
			// Add central directory file header
			$zipData .= pack("VvvvvvvVVVvvvvvVV",
				0x02014b50,						// Central file header signature
				0x0014,							// Version made by
				0x0014,							// Version extracted
				0x0002,							// General purpose bit flag 
				$entry["cmethod"],				// Compression method (deflate)
				$entry["mtime"],				// Last mod file time (MS-DOS)
				$entry["mdate"],				// Last mod file date (MS-DOS)
				$entry["crc32"],				// CRC-32
				$entry["csize"],				// Compressed size
				$entry["size"],					// Uncompressed size
				strlen($entry["path"]),			// Filename length
				strlen($entry["extra"]),		// Extra field length
				strlen($entry["comment"]),		// Comment length
				0,								// Disk
				0,								// Internal file attributes
				$entry["eattr"],				// External file attributes
				$entry["offset"]				// Relative offset of local file header
			);

			// Write filename, extra field and comment
			$zipData .= $entry["path"];
			$zipData .= $entry["extra"];
			$zipData .= $entry["comment"];

			// Central directory info size + file name length + extra field length + comment length
			$centralDirSize += 46 + strlen($entry["path"]) + strlen($entry["extra"]) + strlen($entry["comment"]);
		}

		$comment = "";
		$commentLength = 0;

		// Write end of central directory record
		$zipData .= pack("VvvvvVVv",
			0x06054b50,					// End of central directory signature
			0,							// Number of this disk
			0,							// Disk where central directory starts
			count($entries),			// Number of central directory records on this disk
			count($entries),			// Total number of central directory records
			$centralDirSize,			// Size of central directory (bytes)
			$startOffset,				// Offset of start of central directory, relative to start of archive
			$commentLength				// Zip file comment length
		);

		// Write comment
		$zipData .= $comment;

		return $zipData;
	}

	private function addEntry($zip_path, $entry = array()) {
		$entry["path"] = $zip_path;
		$entry["extra"] = "";
		$entry["comment"] = "";
		$this->entries[] = $entry;
	}

	/**
	 * Combines two paths into one path.
	 *
	 * @param String $path1 Path to be on the left side.
	 * @param String $path2 Path to be on the right side.
	 * @return String Combined path string.
	 */
	private function combine($path1, $path2) {
		$path1 = preg_replace('/\[\/]$/', '', str_replace(DIRECTORY_SEPARATOR, '/', $path1));

		if (!$path2)
			return $path1;

		$path2 = preg_replace('/^\\//', '', str_replace(DIRECTORY_SEPARATOR, '/', $path2));

		return $path1 . '/' . $path2;
	}

	private function listTree($path) {
		$files = array();
		$files[] = $path;

		if ($dir = opendir($path)) {
			while (false !== ($file = readdir($dir))) {
				if ($file == "." || $file == "..")
					continue;

				$file = $path . "/" . $file;

				if (is_dir($file)) {
					$files = array_merge($files, $this->listTree($file));
				} else {
					$files[] = $file;
				}
			}

			closedir($dir);
		}

		return $files;
	}
}

?>