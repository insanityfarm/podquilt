<?php

// Helper class for utility functions that don't fit anywhere else

class Helper {

	// generate SimpleXMLElements from an associative array (invokes itself as it recurses through the array)
	public function arrayToSimpleXml($array, $rootNodeString, &$simpleXml = null) {

		// function does not return anything while recursing, only for the top-level invocation
		$doReturn = false;

		// good way to determine if this is the top-level invocation
		if($simpleXml == null)
		{
			$simpleXml = new SimpleXMLElement($rootNodeString);
			$doReturn = true;
		}

		// convert the array structure into SimpleXMLElements
		foreach($array as $key => $value)
		{
			if(is_array($value))
			{
				if(!is_numeric($key))
				{
					$subnode = $simpleXml->addChild("$key");
				}
				else
				{
					$subnode = $simpleXml->addChild("item");
				}
				// recurse!
				Helper::arrayToSimpleXml($value, $rootNodeString, $subnode);
			}
			else
			{
				$simpleXml->addChild("$key",htmlspecialchars("$value"));
			}
		}

		if($doReturn)
		{
			return $simpleXml;
		}

	}

}