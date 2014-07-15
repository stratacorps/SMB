<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Icewind\SMB;

class NativeServer extends Server {
	/**
	 * @var resource
	 */
	protected $state;

	protected function connect() {
		if ($this->state and is_resource($this->state)) {
			return;
		}
		$user = $this->getUser();
		$workgroup = null;
		if (strpos($user, '/')) {
			list($workgroup, $user) = explode($user, '/');
		}
		NativeShare::registerErrorHandler();
		$this->state = smbclient_state_new();
		$result = smbclient_state_init($this->state, $workgroup, $user, $this->getPassword());
		NativeShare::restoreErrorHandler();
		if (!$result) {
			throw new ConnectionError();
		}
	}

	/**
	 * @return \Icewind\SMB\IShare[]
	 * @throws \Icewind\SMB\AuthenticationException
	 * @throws \Icewind\SMB\InvalidHostException
	 */
	public function listShares() {
		$this->connect();
		$shares = array();
		NativeShare::registerErrorHandler();
		$dh = smbclient_opendir($this->state, 'smb://' . $this->getHost());
		while ($share = smbclient_readdir($this->state, $dh)) {
			if ($share['type'] === 'file share') {
				$shares[] = $this->getShare($share['name']);
			}
		}
		NativeShare::restoreErrorHandler();
		smbclient_closedir($this->state, $dh);
		return $shares;
	}

	/**
	 * @param string $name
	 * @return \Icewind\SMB\IShare
	 */
	public function getShare($name) {
		return new NativeShare($this, $name);
	}

	public function __destruct() {
		if ($this->state and is_resource($this->state)) {
			NativeShare::registerErrorHandler();
			smbclient_state_free($this->state);
			NativeShare::restoreErrorHandler();
		}
		unset($this->state);
	}
}
