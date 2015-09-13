<?php 
namespace Perrich;

/**
 * Some helpers
 */
class Helper
{
	/**
	 * Is haystack ends with needle?
	 *
	 * @param $haystack the string
	 * @param $needle the part to search
	 */
	public static function endsWith($haystack, $needle)
	{
		// search forward starting from end minus needle length characters
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
	}
}