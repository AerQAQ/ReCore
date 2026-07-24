<?php

/*
 *
 * ____       ____
 * |  _ \ ___ / ___|___  _ __ ___
 * | |_) / _ \ |   / _ \| '__/ _ \
 * |  _ <  __/ |__| (_) | | |  __/
 * |_| \_\___|\____\___/|_|  \___|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author ReCoreTeam
 * @link https://github.com/AerQAQ/ReCore
 *
 *
*/

namespace pocketmine;

class CompatibleClassLoader extends \BaseClassLoader{

	/**
	 * @deprecated
	 */
	public function add($namespace, $paths){
		$this->addPath(array_shift($paths));
	}
}