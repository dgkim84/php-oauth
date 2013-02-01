<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OAuth;

use \RestService\Utils\Config as Config;
use \BrowserIDVerifier as BrowserIDVerifier;

class BrowserIDResourceOwner implements IResourceOwner
{
    private $_config;
    private $_verifier;
    private $_resourceOwnerIdHint;

    public function __construct(Config $c)
    {
        $this->_c = $c;

        $bPath = $this->_c->getSectionValue('BrowserIDResourceOwner', 'browserIDPath') . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'BrowserIDVerifier.php';
        if (!file_exists($bPath) || !is_file($bPath) || !is_readable($bPath)) {
            throw new BrowserIDResourceOwnerException("invalid path to php-browserid");
        }
        require_once $bPath;

        $this->_verifier = new BrowserIDVerifier($this->_c->getSectionValue('BrowserIDResourceOwner', 'verifierAddress'));
    }

    public function setHint($resourceOwnerIdHint = NULL)
    {
        $this->_resourceOwnerIdHint = $resourceOwnerIdHint;
    }

    public function getAttributes()
    {
        $attributesFile = $this->_c->getSectionValue('BrowserIDResourceOwner', 'attributesFile');
        $fileContents = @file_get_contents($attributesFile);
        if (FALSE === $fileContents) {
            throw new BrowserIDResourceOwnerException("unable to read attributes file");
        }
        $attributes = json_decode($fileContents, TRUE);
        if (is_array($attributes) && array_key_exists($this->getResourceOwnerId(), $attributes)) {
            return $attributes[$this->getResourceOwnerId()];
        }

        return array();
    }

    public function getAttribute($key)
    {
        $attributes = $this->getAttributes();
        if (array_key_exists($key, $attributes)) {
            return $attributes[$key];
        }

        // "cn" is a special attribute which is used in the OAuth consent
        // dialog, if it is not available from the file just use the BrowserID
        // email address
        if ("cn" === $key) {
            return array($this->getResourceOwnerId());
        }

        return NULL;
    }

    public function getResourceOwnerId()
    {
        return $this->_verifier->authenticate($this->_resourceOwnerIdHint);
    }

    /* FIXME: DEPRECATED */
    public function getEntitlement()
    {
        return $this->getAttribute("eduPersonEntitlement");
    }

}
